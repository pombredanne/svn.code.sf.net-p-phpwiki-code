<?php rcs_id('$Id: stdlib.php,v 1.21.2.12.2.2 2005-01-07 13:59:59 rurban Exp $');

   /*
      Standard functions for Wiki functionality
         ExitWiki($errormsg)
         LinkExistingWikiWord($wikiword, $linktext) 
         LinkUnknownWikiWord($wikiword, $linktext) 
         LinkURL($url, $linktext)
         LinkImage($url, $alt)
         RenderQuickSearch($value)
         RenderFullSearch($value)
         RenderMostPopular()
         CookSpaces($pagearray) 
         class Stack (push(), pop(), cnt(), top())
         SetHTMLOutputMode($newmode, $depth)
         UpdateRecentChanges($dbi, $pagename, $isnewpage) 
         ParseAndLink($bracketlink)
         ExtractWikiPageLinks($content)
         LinkRelatedPages($dbi, $pagename)
	 GeneratePage($template, $content, $name, $hash)
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

   function LinkImage($url, $alt='[External Image]') {
      global $ScriptUrl;
      if(ereg('[<>"]', $url)) {
         return "<b><u>BAD URL -- remove all of &lt;, &gt;, &quot;</u></b>";
      }
      return "<img src=\"$url\" ALT=\"$alt\">";
   }

   
   function RenderQuickSearch($value = '') {
      global $ScriptUrl;
      return "<form action=\"$ScriptUrl\">\n" .
	     "<input type=text size=30 name=search value=\"$value\">\n" .
	     "<input type=submit value=\"". gettext("Search") .
	     "\"></form>\n";
   }

   function RenderFullSearch($value = '') {
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
	 $head = str_replace('_', ' ', $matches[2]);
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


   // I couldn't move this to lib/config.php because it wasn't declared yet.
   $stack = new Stack;

   /* 
      Wiki HTML output can, at any given time, be in only one mode.
      It will be something like Unordered List, Preformatted Text,
      plain text etc. When we change modes we have to issue close tags
      for one mode and start tags for another.

      $tag ... HTML tag to insert
      $tagtype ... ZERO_LEVEL - close all open tags before inserting $tag
		   NESTED_LEVEL - close tags until depths match
      $level ... nesting level (depth) of $tag
		 nesting is arbitrary limited to 10 levels
   */

   function SetHTMLOutputMode($tag, $tagtype, $level)
   {
      global $stack;
      $retvar = '';

      if ($level > 10) {
	  // arbitrarily limit tag nesting
	  //ExitWiki(gettext ("Nesting depth exceeded in SetHTMLOutputMode"));
	  // Now, instead of crapping out when we encounter a deeply
	  // nested list item, we just clamp the the maximum depth.
	  $level = 10;
      }
      
      if ($tagtype == ZERO_LEVEL) {
         // empty the stack until $level == 0;
         if ($tag == $stack->top()) {
            return; // same tag? -> nothing to do
         }
         while ($stack->cnt() > 0) {
            $closetag = $stack->pop();
            $retvar .= "</$closetag>\n";
         }
   
         if ($tag) {
            $retvar .= "<$tag>\n";
            $stack->push($tag);
         }


      } elseif ($tagtype == NESTED_LEVEL) {
         if ($level < $stack->cnt()) {
            // $tag has fewer nestings (old: tabs) than stack,
	    // reduce stack to that tab count
            while ($stack->cnt() > $level) {
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
   
         } elseif ($level > $stack->cnt()) {
	    // Test for and close top level elements which are not allowed to contain
	    // other block-level elements.
	    if ($stack->cnt() == 1 and
	        preg_match('/^(p|pre|h\d)$/i', $stack->top()))
	    {
	       $closetag = $stack->pop();
	       $retvar .= "</$closetag>";
	    }

	    // we add the diff to the stack
	    // stack might be zero
	    if ($stack->cnt() < $level) {
	       while ($stack->cnt() < $level - 1) {
		  // This is a bit of a hack:
		  //
		  // We're not nested deep enough, and have to make up
		  // some kind of block element to nest within.
		  //
		  // Currently, this can only happen for nested list
		  // element (either <ul> <ol> or <dl>).  What we used
		  // to do here is to open extra lists of whatever
		  // type was requested.  This would result in invalid
		  // HTML, since and list is not allowed to contain
		  // another list without first containing a list
		  // item.  ("<ul><ul><li>Item</ul></ul>" is invalid.)
		  //
		  // So now, when we need extra list elements, we use
		  // a <dl>, and open it with an empty <dd>.

		  $retvar .= "<dl><dd>";
		  $stack->push('dl');
	       }

	       $retvar .= "<$tag>\n";
	       $stack->push($tag);
            }
   
         } else { // $level == $stack->cnt()
            if ($tag == $stack->top()) {
               return; // same tag? -> nothing to do
            } else {
	       // different tag - close old one, add new one
               $closetag = $stack->pop();
               $retvar .= "</$closetag>\n";
               $retvar .= "<$tag>\n";
               $stack->push($tag);
            }
         }

   
      } else { // unknown $tagtype
         ExitWiki ("Passed bad tag type value in SetHTMLOutputMode");
      }

      return $retvar;
   }
   // end SetHTMLOutputMode



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
      } elseif (preg_match("#^\d+$#", $URL)) {
         $link['type'] = "reference-$linktype";
	 $link['link'] = $URL;
      } else {
	 $link['type'] = "wiki-unknown-$linktype";
         $link['link'] = LinkUnknownWikiWord($URL, $linkname);
      }

      return $link;
   }


   function ExtractWikiPageLinks($content)
   {
      global $WikiNameRegexp, $AllowedProtocols;

      $wikilinks = array();
      $numlines = count($content);
      for($l = 0; $l < $numlines; $l++)
      {
	 // remove escaped '['
         $line = str_replace('[[', ' ', $content[$l]);

	 // bracket links (only type wiki-* is of interest)
	 $numBracketLinks = preg_match_all("/\[\s*([^\]|]+\|)?\s*(.+?)\s*\]/", $line, $brktlinks);
	 for ($i = 0; $i < $numBracketLinks; $i++) {
	    $link = ParseAndLink($brktlinks[0][$i]);
	    if (preg_match("#^wiki#", $link['type']))
	       $wikilinks[$brktlinks[2][$i]] = 1;

            $brktlink = preg_quote($brktlinks[0][$i]);
            $line = preg_replace("|$brktlink|", '', $line);
	 }

	 // Remove URLs (think about "http:a.b.com/WikiWords").
	 $line = preg_replace("/!?\b($AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]/",
			      ' ', $line);
	 
         // BumpyText old-style wiki links
         if (preg_match_all("/!?$WikiNameRegexp/", $line, $link)) {
            for ($i = 0; isset($link[0][$i]); $i++) {
               if($link[0][$i][0] <> '!')
                  $wikilinks[$link[0][$i]] = 1;
	    }
         }
      }
      return $wikilinks;
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
            if($i>0) {
               $txt .= ", ";
            }
	    $txt .= LinkExistingWikiWord($name) . " ($score)";
         }
      }

      $txt .= "\n<br><b>";
      $txt .= sprintf (gettext ("%d best outgoing links:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['out'][$i])) {
            list($name, $score) = $links['out'][$i];
	    if(IsWikiPage($dbi, $name))
               if($i>0) {
                  $txt .= ", ";
               }
	       $txt .= LinkExistingWikiWord($name) . " ($score)";
         }
      }

      $txt .= "\n<br><b>";
      $txt .= sprintf (gettext ("%d most popular nearby:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['popular'][$i])) {
            list($name, $score) = $links['popular'][$i];
            if($i>0) {
               $txt .= ", ";
            }
	    $txt .= LinkExistingWikiWord($name) . " ($score)";
         }
      }
      
      return $txt;
   }

   
   # GeneratePage() -- takes $content and puts it in the template $template
   # this function contains all the template logic
   #
   # $template ... name of the template (see config.php for list of names)
   # $content ... html content to put into the page
   # $name ... page title
   # $hash ... if called while creating a wiki page, $hash points to
   #           the $pagehash array of that wiki page.

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
?>