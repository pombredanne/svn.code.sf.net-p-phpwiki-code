<!-- $Id: stdlib.inc,v 1.2 2000-10-07 17:47:20 wainstead Exp $ -->
<?php
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
   */


   function LinkRelatedPages($dbi, $pagename)
   {
      // currently not supported everywhere
      if(!function_exists('GetWikiPageLinks'))
         return '';

      $links = GetWikiPageLinks($dbi, $pagename);

      $txt = "<b>" . NUM_RELATED_PAGES . " best incoming links:</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['in'][$i])) {
            list($name, $score) = $links['in'][$i];
	    $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }

      $txt .= "\n<br><b>" . NUM_RELATED_PAGES . " best outgoing links:</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['out'][$i])) {
            list($name, $score) = $links['out'][$i];
	    if(IsWikiPage($dbi, $name))
	       $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }

      $txt .= "\n<br><b>" . NUM_RELATED_PAGES . " most popular nearby:</b>\n";
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
      global $datetimeformat, $dbi, $logo;

      if (!is_array($hash))
         unset($hash);

      $page = join('', file($templates[$template]));
      $page = str_replace('###', "#$FieldSeparator#", $page);

      // valid for all pagetypes
      $page = str_replace("#$FieldSeparator#SCRIPTURL#$FieldSeparator#",
			$ScriptUrl, $page);
      $page = str_replace("#$FieldSeparator#PAGE#$FieldSeparator#",
			htmlspecialchars($name), $page);
      $page = str_replace("#$FieldSeparator#ALLOWEDPROTOCOLS#$FieldSeparator#",
			$AllowedProtocols, $page);
      $page = str_replace("#$FieldSeparator#LOGO#$FieldSeparator#",
                        $logo, $page);

      // invalid for messages (search results, error messages)
      if ($template != 'MESSAGE') {
         $page = str_replace("#$FieldSeparator#PAGEURL#$FieldSeparator#",
			rawurlencode($name), $page);
         $page = str_replace("#$FieldSeparator#LASTMODIFIED#$FieldSeparator#",
			date($datetimeformat, $hash['lastmodified']), $page);
         $page = str_replace("#$FieldSeparator#LASTAUTHOR#$FieldSeparator#",
			$hash['author'], $page);
         $page = str_replace("#$FieldSeparator#VERSION#$FieldSeparator#",
			$hash['version'], $page);
	 if (strstr($page, "#$FieldSeparator#HITS#$FieldSeparator#")) {
            $page = str_replace("#$FieldSeparator#HITS#$FieldSeparator#",
			GetHitCount($dbi, $name), $page);
	 }
	 if (strstr($page, "#$FieldSeparator#RELATEDPAGES#$FieldSeparator#")) {
            $page = str_replace("#$FieldSeparator#RELATEDPAGES#$FieldSeparator#",
			LinkRelatedPages($dbi, $name), $page);
	 }
      }

      // valid only for EditLinks
      if ($template == 'EDITLINKS') {
	 for ($i = 1; $i <= NUM_LINKS; $i++)
	    $page = str_replace("#$FieldSeparator#R$i#$FieldSeparator#",
			$hash['refs'][$i], $page);
      }

      if ($hash['copy']) {
	 $page = str_replace("#$FieldSeparator#IFCOPY#$FieldSeparator#",
			'', $page);
      } else {
	 $page = ereg_replace("#$FieldSeparator#IFCOPY#$FieldSeparator#[^\n]*",
			'', $page);
      }

      $page = str_replace("#$FieldSeparator#CONTENT#$FieldSeparator#",
			$content, $page);
      print $page;
   }


   function LinkExistingWikiWord($wikiword) {
      global $ScriptUrl;
      $enc_word = rawurlencode($wikiword);
      $wikiword = htmlspecialchars($wikiword);
      return "<a href=\"$ScriptUrl?$enc_word\">$wikiword</a>";
   }

   function LinkUnknownWikiWord($wikiword) {
      global $ScriptUrl;
      $enc_word = rawurlencode($wikiword);
      $wikiword = htmlspecialchars($wikiword);
      return "<u>$wikiword</u><a href=\"$ScriptUrl?edit=$enc_word\">?</a>";
   }

   function LinkURL($url) {
      global $ScriptUrl;
      if(ereg("[<>\"]", $url)) {
         return "<b><u>BAD URL -- remove all of &lt;, &gt;, &quot;</u></b>";
      }
      $enc_url = htmlspecialchars($url);
      return "<a href=\"$url\">$enc_url</a>";
   }


   function LinkImage($url) {
      global $ScriptUrl;
      if(ereg("[<>\"]", $url)) {
         return "<b><u>BAD URL -- remove all of &lt;, &gt;, &quot;</u></b>";
      }
      return "<img src=\"$url\">";
   }

   
   function RenderQuickSearch() {
      global $value, $ScriptUrl;
      $formtext = "<form action='$ScriptUrl'>\n<input type='text' size='40' name='search' value='$value'>\n</form>\n";
      return $formtext;
   }

   function RenderFullSearch() {
      global $value, $ScriptUrl;
      $formtext = "<form action='$ScriptUrl'>\n<input type='text' size='40' name='full' value='$value'>\n</form>\n";
      return $formtext;
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

   // converts spaces to tabs
   function CookSpaces($pagearray) {
      return preg_replace("/ {3,8}/", "\t", $pagearray);
   }


   class Stack {
      var $items;
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
         return $this->items[$this->size - 1];
      }  

   }  
   // end class definition


   // I couldn't move this to lib/config.inc because it 
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
                  echo "Stack bounds exceeded in SetHTMLOutputMode\n";
                  exit();
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
         echo "Passed bad tag depth value in SetHTMLOutputMode\n";
         exit();
      }

      return $retvar;

   }
   // end SetHTMLOutputMode



   // The Recent Changes file is solely handled here
   function UpdateRecentChanges($dbi, $pagename, $isnewpage) {

      global $remoteuser; // this is set in the config
      global $dateformat;
      global $ScriptUrl;
      global $WikiPageStore;

      $recentchanges = RetrievePage($dbi, "RecentChanges", $WikiPageStore);

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
      for ($i = 0; $i < ($numlines + 1); $i++) {
         if (preg_match("/^____/",
                        $recentchanges["content"][$i])) {
            break;
         } else {
            $newpage[$k++] = $recentchanges["content"][$i];
         }
      }

      // if it's a new date, insert it, else add the updated page's
      // name to the array

      if ($isNewDay) {
         $newpage[$k++] = "____$today\r";
         $newpage[$k++] = "\r";
      } else {
         $newpage[$k++] = $recentchanges["content"][$i++];
      }
      if($isnewpage) {
         $newpage[$k++] = "\t* [$pagename] (new) ..... $remoteuser\r";
      } else {
	 $diffurl = "$ScriptUrl?diff=" . rawurlencode($pagename);
         $newpage[$k++] = "\t* [$pagename] ([diff|$diffurl]) ..... $remoteuser\r";
      }

      // copy the rest of the page into the new array
      $pagename = preg_quote($pagename);
      for (; $i < ($numlines + 1); $i++) {
         // skip previous entry for $pagename
         if (preg_match("|\[$pagename\]|", $recentchanges["content"][$i])) {
            continue;
         } else {
            $newpage[$k++] = $recentchanges["content"][$i];
         }
      }

      $recentchanges["content"] = $newpage;

      InsertPage($dbi, "RecentChanges", $recentchanges);
   }



   function ParseAndLink($bracketlink) {
      global $dbi, $AllowedProtocols;

      // $bracketlink will start and end with brackets; in between
      // will be either a page name, a URL or both separated by a pipe.

      // strip brackets and leading space
      preg_match("/(\[\s*)(.+?)(\s*\])/", $bracketlink, $match);
      $linkdata = $match[2];

      // send back links that are only numbers (they are references)
      if (preg_match("/^\d+$/", $linkdata)) {
         $link['type'] = 'ref';
	 $link['link'] = $bracketlink;
         return $link;
      }

      // send back escaped ([[) bracket sets
      if (preg_match("/^\[/", $linkdata)) {
         $link['type'] = 'none';
	 $link['link'] = htmlspecialchars(substr($bracketlink, 1));
         return $link;
      }

      // match the contents 
      preg_match("/([^|]+)(\|)?([^|]+)?/", $linkdata, $matches);


      // if $matches[3] is set, this is a link in the form of:
      // [some link name | http://blippy.com/]

      if (isset($matches[3])) {
         $URL = trim($matches[3]);
         $linkname = htmlspecialchars(trim($matches[1]));
         // assert proper URL's
         if (preg_match("#^($AllowedProtocols):#", $URL)) {
            $link['type'] = 'url-named';
	    $link['link'] = "<a href=\"$URL\">$linkname</a>";
         } else {
            $link['type'] = 'url-bad';
            $link['link'] = "<b><u>BAD URL -- links have to start with one" . 
                   "of $AllowedProtocols followed by ':'</u></b>";
         }
	 return $link;
      }


      // otherwise this is just a Wiki page like this: [page name]
      // or a URL in brackets: [http://foo.com/]

      if (isset($matches[1])) {
         $linkname = trim($matches[1]);
         if (IsWikiPage($dbi, $linkname)) {
            $link['type'] = 'wiki';
            $link['link'] = LinkExistingWikiWord($linkname);
         } elseif (preg_match("#^($AllowedProtocols):#", $linkname)) {
            // if it's an image, embed it; otherwise, it's a regular link
            if (preg_match("/jpg$|png$|gif$/i", $linkname)) {
	       $link['type'] = 'url-image';
               $link['link'] = LinkImage($linkname);
            } else {
	       $link['type'] = 'url-simple';
               $link['link'] = LinkURL($linkname);
            }
	 } else {
	    $link['type'] = 'wiki-unknown';
            $link['link'] = LinkUnknownWikiWord($linkname);
         }

	 return $link;
      }


      $link['type'] = 'unknown';
      $link['link'] = $bracketlink;
      return $link;
   }


   function ExtractWikiPageLinks($content)
   {
      $wikilinks = array();

      $numlines = count($content);
      for($l = 0; $l < $numlines; $l++)
      {
         $line = $content[$l];
	 $numBracketLinks = preg_match_all("/\[\s*(.+?)\s*\]/", $line, $brktlinks);
	 for ($i = 0; $i < $numBracketLinks; $i++) {
	    $link = ParseAndLink($brktlinks[0][$i]);
	    if($link['type'] == 'wiki' || $link['type'] == 'wiki-unknown')
	       $wikilinks[$brktlinks[1][$i]]++;

            $brktlink = preg_quote($brktlinks[0][$i]);
            $line = preg_replace("|$brktlink|", '', $line);
	 }

         if (preg_match_all("#!?\b(([A-Z][a-z]+){2,})\b#", $line, $link)) {
            for ($i = 0; $link[0][$i]; $i++) {
               if(!strstr($link[0][$i], '!'))
                  $wikilinks[$link[0][$i]]++;
	    }
         }
      }

      return $wikilinks;
   }      
?>
