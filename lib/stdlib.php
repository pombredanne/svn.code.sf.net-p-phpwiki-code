<?php
   rcs_id('$Id: stdlib.php,v 1.18 2001-01-01 23:34:57 ahollosi Exp $');
   /*
      Standard functions for Wiki functionality
         LinkRelatedPages($dbi, $pagename)
	 GeneratePage($template, $content, $name, $hash)
         LinkExistingWikiWord($wikiword) 
         LinkUnknownWikiWord($wikiword) 
         LinkURL($url)
         LinkImage($url)
         RenderQuickSearch() 
         RenderFullSearch() 
         RenderMostPopular()
         CookSpaces($pagearray) 
         class Stack
         SetHTMLOutputMode($newmode, $depth)
         UpdateRecentChanges($dbi, $pagename, $isnewpage) 
         ParseAndLink($bracketlink)
         ExtractWikiPageLinks($content)
         ExitWiki($errormsg)
   */


   function ExitWiki($errormsg)
   {
      static $exitwiki = 0;
      global $dbi;

      if($exitwiki)		// just in case CloseDataBase calls us
         exit();
      $exitwiki = 1;

      CloseDataBase($dbi);

      if($errormsg <> '') {
         print "<P><hr noshade><h2>" . gettext("WikiFatalError") . "</h2>\n";
         print $errormsg;
         print "\n</BODY></HTML>";
      }
      exit;
   }


   function LinkRelatedPages($dbi, $pagename)
   {
      // currently not supported everywhere
      if(!function_exists('GetWikiPageLinks'))
         return '';

      $links = GetWikiPageLinks($dbi, $pagename);

      $txt = "<b>";
      $txt .= sprintf (gettext ("%d best incoming links:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['in'][$i])) {
            list($name, $score) = $links['in'][$i];
	    $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }

      $txt .= "\n<br><b>";
      $txt .= sprintf (gettext ("%d best outgoing links:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['out'][$i])) {
            list($name, $score) = $links['out'][$i];
	    if(IsWikiPage($dbi, $name))
	       $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }

      $txt .= "\n<br><b>";
      $txt .= sprintf (gettext ("%d most popular nearby:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['popular'][$i])) {
            list($name, $score) = $links['popular'][$i];
	    $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }
      
      return $txt;
   }

   
   function GeneratePage($template, $content, $name, $hash)
   {
      global $ScriptUrl, $AllowedProtocols, $templates;
      global $datetimeformat, $dbi, $logo, $FieldSeparator;

      if (!is_array($hash))
         unset($hash);

      function _dotoken ($id, $val, &$page) {
	 global $FieldSeparator;
         $page = str_replace("$FieldSeparator#$id$FieldSeparator#",
				$val, $page);
      }

      function _iftoken ($id, $condition, &$page) {
         global $FieldSeparator;

	 // line based IF directive
	 $lineyes = "$FieldSeparator#IF $id$FieldSeparator#";
	 $lineno = "$FieldSeparator#IF !$id$FieldSeparator#";
         // block based IF directive
	 $blockyes = "$FieldSeparator#IF:$id$FieldSeparator#";
	 $blockyesend = "$FieldSeparator#ENDIF:$id$FieldSeparator#";
	 $blockno = "$FieldSeparator#IF:!$id$FieldSeparator#";
	 $blocknoend = "$FieldSeparator#ENDIF:!$id$FieldSeparator#";

	 if ($condition) {
	    $page = str_replace($lineyes, '', $page);
	    $page = str_replace($blockyes, '', $page);
	    $page = str_replace($blockyesend, '', $page);
	    $page = preg_replace("/$blockno(.*?)$blocknoend/s", '', $page);
	    $page = ereg_replace("${lineno}[^\n]*\n", '', $page);
         } else {
	    $page = str_replace($lineno, '', $page);
	    $page = str_replace($blockno, '', $page);
	    $page = str_replace($blocknoend, '', $page);
	    $page = preg_replace("/$blockyes(.*?)$blockyesend/s", '', $page);
	    $page = ereg_replace("${lineyes}[^\n]*\n", '', $page);
	 }
      }

      $page = join('', file($templates[$template]));
      $page = str_replace('###', "$FieldSeparator#", $page);

      // valid for all pagetypes
      _iftoken('COPY', isset($hash['copy']), $page);
      _iftoken('LOCK',	(isset($hash['flags']) &&
			($hash['flags'] & FLAG_PAGE_LOCKED)), $page);
      _iftoken('ADMIN', defined('WIKI_ADMIN'), $page);

      _dotoken('SCRIPTURL', $ScriptUrl, $page);
      _dotoken('PAGE', htmlspecialchars($name), $page);
      _dotoken('ALLOWEDPROTOCOLS', $AllowedProtocols, $page);
      _dotoken('LOGO', $logo, $page);
      
      // invalid for messages (search results, error messages)
      if ($template != 'MESSAGE') {
         _dotoken('PAGEURL', rawurlencode($name), $page);
         _dotoken('LASTMODIFIED',
			date($datetimeformat, $hash['lastmodified']), $page);
         _dotoken('LASTAUTHOR', $hash['author'], $page);
         _dotoken('VERSION', $hash['version'], $page);
	 if (strstr($page, "$FieldSeparator#HITS$FieldSeparator#")) {
            _dotoken('HITS', GetHitCount($dbi, $name), $page);
	 }
	 if (strstr($page, "$FieldSeparator#RELATEDPAGES$FieldSeparator#")) {
            _dotoken('RELATEDPAGES', LinkRelatedPages($dbi, $name), $page);
	 }
      }

      // valid only for EditLinks
      if ($template == 'EDITLINKS') {
	 for ($i = 1; $i <= NUM_LINKS; $i++) {
            $ref = isset($hash['refs'][$i]) ? $hash['refs'][$i] : '';
	    _dotoken("R$i", $ref, $page);
         }
      }

      _dotoken('CONTENT', $content, $page);
      print $page;
   }


   function LinkExistingWikiWord($wikiword, $linktext='') {
      global $ScriptUrl;
      $enc_word = rawurlencode($wikiword);
      if(empty($linktext))
         $linktext = htmlspecialchars($wikiword);
      return "<a href=\"$ScriptUrl?$enc_word\">$linktext</a>";
   }

   function LinkUnknownWikiWord($wikiword, $linktext='') {
      global $ScriptUrl;
      $enc_word = rawurlencode($wikiword);
      if(empty($linktext))
         $linktext = htmlspecialchars($wikiword);
      return "<u>$linktext</u><a href=\"$ScriptUrl?edit=$enc_word\">?</a>";
   }

   function LinkURL($url, $linktext='') {
      global $ScriptUrl;
      if(ereg("[<>\"]", $url)) {
         return "<b><u>BAD URL -- remove all of &lt;, &gt;, &quot;</u></b>";
      }

      if(empty($linktext))
         $linktext = htmlspecialchars($url);
      return "<a href=\"$url\">$linktext</a>";
   }


   function LinkImage($url, $alt="[External Image]") {
      global $ScriptUrl;
      if(ereg("[<>\"]", $url)) {
         return "<b><u>BAD URL -- remove all of &lt;, &gt;, &quot;</u></b>";
      }
      return "<img src=\"$url\" ALT=\"$alt\">";
   }

   
   function RenderQuickSearch($value = "") {
      global $ScriptUrl;
      return "<form action=\"$ScriptUrl\">\n" .
	     "<input type=text size=30 name=search value=\"$value\">\n" .
	     "<input type=submit value=\"". gettext("Search") .
	     "\"></form>\n";
   }

   function RenderFullSearch($value = "") {
      global $ScriptUrl;
      return "<form action=\"$ScriptUrl\">\n" .
	     "<input type=text size=30 name=full value=\"$value\">\n" .
	     "<input type=submit value=\"". gettext("Search") .
	     "\"></form>\n";
   }

   function RenderMostPopular() {
      global $ScriptUrl, $dbi;
      
      $query = InitMostPopular($dbi, MOST_POPULAR_LIST_LENGTH);
      $result = "<DL>\n";
      while ($qhash = MostPopularNextMatch($dbi, $query)) {
	 $result .= "<DD>$qhash[hits] ... " . LinkExistingWikiWord($qhash['pagename']) . "\n";
      }
      $result .= "</DL>\n";
      
      return $result;
   }

   function ParseAdminTokens($line) {
      global $ScriptUrl;
      
      while (preg_match("/%%ADMIN-INPUT-(.*?)-(\w+)%%/", $line, $matches)) {
	 $head = str_replace("_", " ", $matches[2]);
         $form = "<FORM ACTION=\"$ScriptUrl\" METHOD=POST>"
		."$head: <INPUT NAME=$matches[1] SIZE=20> "
		."<INPUT TYPE=SUBMIT VALUE=\"" . gettext("Go") . "\">"
		."</FORM>";
	 $line = str_replace($matches[0], $form, $line);
      }
      return $line;
   }

   // converts spaces to tabs
   function CookSpaces($pagearray) {
      return preg_replace("/ {3,8}/", "\t", $pagearray);
   }


   class Stack {
      var $items = array();
      var $size = 0;

      function push($item) {
         $this->items[$this->size] = $item;
         $this->size++;
         return true;
      }  
   
      function pop() {
         if ($this->size == 0) {
            return false; // stack is empty
         }  
         $this->size--;
         return $this->items[$this->size];
      }  
   
      function cnt() {
         return $this->size;
      }  

      function top() {
         if($this->size)
            return $this->items[$this->size - 1];
         else
            return '';
      }  

   }  
   // end class definition


   // I couldn't move this to lib/config.php because it 
   // wasn't declared yet.
   $stack = new Stack;

   /* 
      Wiki HTML output can, at any given time, be in only one mode.
      It will be something like Unordered List, Preformatted Text,
      plain text etc. When we change modes we have to issue close tags
      for one mode and start tags for another.
   */

   function SetHTMLOutputMode($tag, $tagdepth, $tabcount) {
      global $stack;
      $retvar = "";
   
      if ($tagdepth == SINGLE_DEPTH) {
         if ($tabcount < $stack->cnt()) {
            // there are fewer tabs than stack,
	    // reduce stack to that tab count
            while ($stack->cnt() > $tabcount) {
               $closetag = $stack->pop();
               if ($closetag == false) {
                  //echo "bounds error in tag stack";
                  break;
               }
               $retvar .= "</$closetag>\n";
            }

	    // if list type isn't the same,
	    // back up one more and push new tag
	    if ($tag != $stack->top()) {
	       $closetag = $stack->pop();
	       $retvar .= "</$closetag><$tag>\n";
	       $stack->push($tag);
	    }
   
         } elseif ($tabcount > $stack->cnt()) {
            // we add the diff to the stack
            // stack might be zero
            while ($stack->cnt() < $tabcount) {
               #echo "<$tag>\n";
               $retvar .= "<$tag>\n";
               $stack->push($tag);
               if ($stack->cnt() > 10) {
                  // arbitrarily limit tag nesting
                  ExitWiki(gettext ("Stack bounds exceeded in SetHTMLOutputMode"));
               }
            }
   
         } else {
            if ($tag == $stack->top()) {
               return;
            } else {
               $closetag = $stack->pop();
               #echo "</$closetag>\n";
               #echo "<$tag>\n";
               $retvar .= "</$closetag>\n";
               $retvar .= "<$tag>\n";
               $stack->push($tag);
            }
         }
   
      } elseif ($tagdepth == ZERO_DEPTH) {
         // empty the stack for $depth == 0;
         // what if the stack is empty?
         if ($tag == $stack->top()) {
            return;
         }
         while ($stack->cnt() > 0) {
            $closetag = $stack->pop();
            #echo "</$closetag>\n";
            $retvar .= "</$closetag>\n";
         }
   
         if ($tag) {
            #echo "<$tag>\n";
            $retvar .= "<$tag>\n";
            $stack->push($tag);
         }
   
      } else {
         // error
         ExitWiki ("Passed bad tag depth value in SetHTMLOutputMode");
      }

      return $retvar;

   }
   // end SetHTMLOutputMode



   // The Recent Changes file is solely handled here
   function UpdateRecentChanges($dbi, $pagename, $isnewpage) {

      global $remoteuser; // this is set in the config
      global $dateformat;
      global $WikiPageStore;

      $recentchanges = RetrievePage($dbi, gettext ("RecentChanges"), 
      	$WikiPageStore);

      // this shouldn't be necessary, since PhpWiki loads 
      // default pages if this is a new baby Wiki
      if ($recentchanges == -1) {
         $recentchanges = array(); 
      }

      $now = time();
      $today = date($dateformat, $now);

      if (date($dateformat, $recentchanges["lastmodified"]) != $today) {
         $isNewDay = TRUE;
         $recentchanges["lastmodified"] = $now;
      } else {
         $isNewDay = FALSE;
      }

      $numlines = sizeof($recentchanges["content"]);
      $newpage = array();
      $k = 0;

      // scroll through the page to the first date and break
      // dates are marked with "____" at the beginning of the line
      for ($i = 0; $i < $numlines; $i++) {
         if (preg_match("/^____/",
                        $recentchanges["content"][$i])) {
            break;
         } else {
            $newpage[$k++] = $recentchanges["content"][$i];
         }
      }

      // if it's a new date, insert it, else add the updated page's
      // name to the array

      $newpage[$k++] = $isNewDay ? "____$today\r"
				 : $recentchanges["content"][$i++];
      if($isnewpage) {
         $newpage[$k++] = "* [$pagename] (new) ..... $remoteuser\r";
      } else {
	 $diffurl = "phpwiki:?diff=" . rawurlencode($pagename);
         $newpage[$k++] = "* [$pagename] ([diff|$diffurl]) ..... $remoteuser\r";
      }
      if ($isNewDay)
         $newpage[$k++] = "\r";

      // copy the rest of the page into the new array
      $pagename = preg_quote($pagename);
      for (; $i < $numlines; $i++) {
         // skip previous entry for $pagename
         if (preg_match("|\[$pagename\]|", $recentchanges["content"][$i])) {
            continue;
         } else {
            $newpage[$k++] = $recentchanges["content"][$i];
         }
      }

      $recentchanges["content"] = $newpage;

      InsertPage($dbi, gettext ("RecentChanges"), $recentchanges);
   }



   function ParseAndLink($bracketlink) {
      global $dbi, $ScriptUrl, $AllowedProtocols, $InlineImages;

      // $bracketlink will start and end with brackets; in between
      // will be either a page name, a URL or both separated by a pipe.

      // strip brackets and leading space
      preg_match("/(\[\s*)(.+?)(\s*\])/", $bracketlink, $match);
      // match the contents 
      preg_match("/([^|]+)(\|)?([^|]+)?/", $match[2], $matches);

      if (isset($matches[3])) {
         // named link of the form  "[some link name | http://blippy.com/]"
         $URL = trim($matches[3]);
         $linkname = htmlspecialchars(trim($matches[1]));
	 $linktype = 'named';
      } else {
         // unnamed link of the form "[http://blippy.com/] or [wiki page]"
         $URL = trim($matches[1]);
	 $linkname = '';
	 $linktype = 'simple';
      }

      if (IsWikiPage($dbi, $URL)) {
         $link['type'] = "wiki-$linktype";
         $link['link'] = LinkExistingWikiWord($URL, $linkname);
      } elseif (preg_match("#^($AllowedProtocols):#", $URL)) {
        // if it's an image, embed it; otherwise, it's a regular link
         if (preg_match("/($InlineImages)$/i", $URL)) {
	    $link['type'] = "image-$linktype";
            $link['link'] = LinkImage($URL, $linkname);
         } else {
	    $link['type'] = "url-$linktype";
            $link['link'] = LinkURL($URL, $linkname);
	 }
      } elseif (preg_match("#^phpwiki:(.*)#", $URL, $match)) {
	 $link['type'] = "url-wiki-$linktype";
	 if(empty($linkname))
	    $linkname = htmlspecialchars($URL);
	 $link['link'] = "<a href=\"$ScriptUrl$match[1]\">$linkname</a>";
      } else {
	 $link['type'] = "wiki-unknown-$linktype";
         $link['link'] = LinkUnknownWikiWord($URL, $linkname);
      }

      return $link;
   }


   function ExtractWikiPageLinks($content)
   {
      global $WikiNameRegexp;

      $wikilinks = array();
      $numlines = count($content);
      for($l = 0; $l < $numlines; $l++)
      {
         $line = str_replace('[[', ' ', $content[$l]);  // remove escaped '['
	 $numBracketLinks = preg_match_all("/\[\s*([^\]|]+\|)?\s*(.+?)\s*\]/", $line, $brktlinks);
	 for ($i = 0; $i < $numBracketLinks; $i++) {
	    $link = ParseAndLink($brktlinks[0][$i]);
	    if (preg_match("#^wiki#", $link['type']))
	       $wikilinks[$brktlinks[2][$i]] = 1;

            $brktlink = preg_quote($brktlinks[0][$i]);
            $line = preg_replace("|$brktlink|", '', $line);
	 }

         if (preg_match_all("/!?$WikiNameRegexp/", $line, $link)) {
            for ($i = 0; isset($link[0][$i]); $i++) {
               if($link[0][$i][0] <> '!')
                  $wikilinks[$link[0][$i]] = 1;
	    }
         }
      }
      return $wikilinks;
   }      
?>
