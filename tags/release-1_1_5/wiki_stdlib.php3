<!-- $Id: wiki_stdlib.php3,v 1.11 2000-06-07 10:50:01 ahollosi Exp $ -->
<?
   /*
      Standard functions for Wiki functionality
         WikiToolBar() 
         WikiHeader($pagename) 
         WikiFooter() 
         GetCurrentDate()
         LinkExistingWikiWord($wikiword) 
         LinkUnknownWikiWord($wikiword) 
         LinkURL($url)
         RenderQuickSearch() 
         RenderFullSearch() 
         CookSpaces($pagearray) 
         class Stack
         SetHTMLOutputMode($newmode, $depth)
         UpdateRecentChanges($dbi, $pagename, $isnewpage) 
         SaveCopyToArchive($pagename, $pagehash) 
         ParseAndLink($bracketlink)
   */

   // render the Wiki toolbar at bottom of page
   function WikiToolBar() {
      global $ScriptUrl, $pagename, $pagehash;
      $enc_name = rawurlencode($pagename);

      $retval  = "<hr>\n" .
		 "<a href=\"$ScriptUrl?edit=$enc_name\">EditText</a>\n" .
		 " of this page\n";
      if (is_array($pagehash)) {
         $retval .= " (last edited " . $pagehash["date"] . ")\n";
      }
      $retval .= "<br>\n" .
		 "<a href=\"$ScriptUrl?FindPage\">" .
		 "FindPage</a> by browsing or searching\n";
      return $retval;
   }

   // top of page
   function WikiHeader($pagename) {
      global $LogoImage, $ScriptUrl;
      return "<html>\n<head>\n<title>" . htmlspecialchars($pagename) .
	     "</title>\n</head>\n<body>\n";
   }

   function WikiFooter() {
      return "</body>\n</html>\n";
   }

   function GetCurrentDate() {
      // format is like December 13, 1999
      return date("F j, Y");
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


   // I couldn't move this to wiki_config.php3 because it 
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

      $recentchanges = RetrievePage($dbi, "RecentChanges");

      // this shouldn't be necessary, since PhpWiki loads 
      // default pages if this is a new baby Wiki
      if ($recentchanges == -1) {
         $recentchanges = array(); 
      }

      $currentdate = GetCurrentDate();

      if ($recentchanges["date"] != $currentdate) {
         $isNewDay = TRUE;
         $recentchanges["date"] = $currentdate;
      } else {
         $isNewDay = FALSE;
      }

      $numlines = sizeof($recentchanges["text"]);
      $newpage = array();
      $k = 0;

      // scroll through the page to the first date and break
      for ($i = 0; $i < ($numlines + 1); $i++) {
         if (preg_match("/^\w\w\w+ \d\d?, \d\d\d\d\r$/",
                        $recentchanges["text"][$i])) {
            break;
         } else {
            $newpage[$k++] = $recentchanges["text"][$i];
         }
      }

      // if it's a new date, insert it, else add the updated page's
      // name to the array

      if ($isNewDay) {
         $newpage[$k++] = "$currentdate\r";
      } else {
         $newpage[$k++] = $recentchanges["text"][$i++];
      }
      if($isnewpage) {
         $newpage[$k++] = "\t* [$pagename] (new) ..... $remoteuser\r";
      } else {
         $newpage[$k++] = "\t* [$pagename] ..... $remoteuser\r";
      }

      // copy the rest of the page into the new array
      $pagename = preg_quote($pagename);
      for (; $i < ($numlines + 1); $i++) {
         // skip previous entry for $pagename
         if (preg_match("/\[$pagename\]/", $recentchanges["text"][$i])) {
            continue;
         } else {
            $newpage[$k++] = $recentchanges["text"][$i];
         }
      }

      $recentchanges["text"] = $newpage;

      InsertPage($dbi, "RecentChanges", $recentchanges);
   }


   // for archiving pages to a seperate dbm
   function SaveCopyToArchive($pagename, $pagehash) {
      global $ArchiveDataBase;

      $adbi = OpenDataBase($ArchiveDataBase);
      $newpagename = $pagename;
      InsertPage($adbi, $newpagename, $pagehash);
   }


   function ParseAndLink($bracketlink) {
      global $dbi, $AllowedProtocols;

      // $bracketlink will start and end with brackets; in between
      // will be either a page name, a URL or both seperated by a pipe.

      // strip brackets and leading space
      preg_match("/(\[\s*)(.+?)(\s*\])/", $bracketlink, $match);
      $linkdata = $match[2];

      // send back links that are only numbers (they are references)
      if (preg_match("/^\d+$/", $linkdata)) {
         return $bracketlink;
      }

      // send back escaped ([[) bracket sets
      if (preg_match("/^\[/", $linkdata)) {
         return htmlspecialchars($linkdata) . "]";
      }

      // match the contents 
      preg_match("/([^|]+)(\|)?([^|]+)?/", $linkdata, $matches);

      if (isset($matches[3])) {
         $URL = trim($matches[3]);
         $linkname = htmlspecialchars(trim($matches[1]));
         // assert proper URL's
         if (preg_match("#^($AllowedProtocols):#", $URL)) {
            return "<a href=\"$URL\">$linkname</a>";
         } else {
            return "<b><u>BAD URL -- links have to start with one of " . 				                "$AllowedProtocols followed by ':'</u></b>";
         }
      }

      if (isset($matches[1])) {
         $linkname = trim($matches[1]);
         if (IsWikiPage($dbi, $linkname)) {
            return LinkExistingWikiWord($linkname);
         } elseif (preg_match("#^($AllowedProtocols):#", $linkname)) {
            return LinkURL($linkname);
         } elseif ($linkname == 'Search') {
	    return RenderQuickSearch();
	 } elseif ($linkname == 'Fullsearch') {
	    return RenderFullSearch();
	 } else {
            return LinkUnknownWikiWord($linkname);
         }
      }

      return $bracketlink;

   }

?>
