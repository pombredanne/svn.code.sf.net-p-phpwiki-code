<!-- $Id: wiki_stdlib.php3,v 1.23.2.1 2000-07-21 18:29:07 dairiki Exp $ -->
<?
   /*
      Standard functions for Wiki functionality
	 GeneratePage($template, $content, $name, $hash)
         WikiURL ($pagename, $action)
         LinkExistingWikiWord($wikiword) 
         LinkUnknownWikiWord($wikiword) 
	 LinkWikiWord($wikiword)
	 LinkExternal($text [,$url [,$inline]])
         RenderQuickSearch() 
         RenderFullSearch() 
         RenderMostPopular()
         CookSpaces($pagearray) 
         UpdateRecentChanges($dbi, $pagename, $isnewpage) 
         SaveCopyToArchive($pagename, $pagehash)

	 strip_magic_quotes_gpc($string)
   */

   require('wiki_renderlib.php3');

   function GeneratePage ($template, $content, $name, $hash)
   {
      $start = utime();
      $gen = new WikiPageGenerator($name, $hash);
      print $gen->generate($template, $content);
      printf("<hr><b>Page generation took %f seconds</b>\n", utime() - $start);
   }
      

   function WikiURL ($pagename, $action = false) {
      global $ScriptUrl;

      $pagename = rawurlencode($pagename);
      if ($action == 'browse')
	  unset($action);
      else
	  $action = rawurlencode($action);
      
      if (WIKI_PAGENAME_IN_PATHINFO) {
	 $url = "$ScriptUrl/$pagename";
	 if ($action)
	     $url .= "?action=$action";
      }
      else {
	 $url = "$ScriptUrl?";
	 if ($action)
	    $url .= "$action=";
	 $url .= $pagename;
      }
      return $url;
   }

   function LinkExistingWikiWord($wikiword) {
      return sprintf('<a href="%s">%s</a>',
		     WikiURL($wikiword), htmlspecialchars($wikiword));
   }

   function LinkUnknownWikiWord($wikiword) {
      return sprintf('<u>%s</u><a href="%s">?</a>',
		     htmlspecialchars($wikiword),
		     WikiURL($wikiword, 'edit'));
   }

   function LinkWikiWord ($page) {
      global $dbi;
      return ( IsWikiPage($dbi, $page)
	       ? LinkExistingWikiWord($page)
	       : LinkUnknownWikiWord($page) );
   }

   function LinkExternal ($text, $url = false, $inline = false) {
     if ( ! $url)
	 $url = $text;
      
     if (!preg_match('/^' . SAFE_URL_REGEXP . '$/', $url))
       {
	 // Illegal URL
	 if ($url != $text)
	     $text .= "($url)";
	 return htmlspecialchars($text);
       }

     if ($inline)
	 $fmt = "<img src=\"%s\" alt=\"%s\">";
     else
	 $fmt = "<a href=\"%s\">%s</a>";

     return sprintf($fmt, $url, htmlspecialchars($text));
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
      
      $query = InitMostPopular($dbi, 20);
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


   // The Recent Changes file is solely handled here
   function UpdateRecentChanges($dbi, $pagename, $isnewpage) {

      global $remoteuser; // this is set in the config
      global $dateformat;
      global $ScriptUrl;

      $recentchanges = RetrievePage($dbi, "RecentChanges");

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


   // for archiving pages to a seperate dbm
   function SaveCopyToArchive($pagename, $pagehash) {
      global $ArchiveDataBase;

      $adbi = OpenDataBase($ArchiveDataBase);
      $newpagename = $pagename;
      InsertPage($adbi, $newpagename, $pagehash);
   }

   function strip_magic_quotes_gpc ($string) {
      return get_magic_quotes_gpc() ? stripslashes($string) : $string;
   }
?>
