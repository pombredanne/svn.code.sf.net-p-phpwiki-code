<? rcs_id('$Id: wiki_stdlib.php3,v 1.23.2.4 2000-07-31 21:10:01 dairiki Exp $');
   /*
      Standard functions for Wiki functionality
         WikiURL ($pagename, $action)
         LinkExistingWikiWord($wikiword) 
         LinkUnknownWikiWord($wikiword) 
	 LinkWikiWord($wikiword)
	 LinkExternal($text [,$url [,$inline]])
         CookSpaces($pagearray) 
         UpdateRecentChanges($dbi, $pagename, $isnewpage) 
	 strip_magic_quotes_gpc($string)
	 wiki_message($handle, $messageid)
   */

   require('wiki_template.php3');

   function WikiURL($pagename, $action = false, $extra = false) {
      global $ScriptName;

      $enc_name = rawurlencode($pagename);
      $action = rawurlencode($action ? $action : 'browse');

      if (!$extra || !preg_match('/edit|links|browse|diff/', $action))
	  $extra = array();
      if (!is_array($extra))
	  $extra = array('version' => $extra);

      if (WIKI_PAGENAME_IN_PATHINFO) {
	 $url = $enc_name;
	 if ($action != 'browse')
	     $args[] = "action=$action";
      }
      else {
	 $url = $ScriptName;
	 $args[] = $action == 'browse' ? $enc_name : "$action=$enc_name";
      }

      while (list($key,$val) = each($extra))
	  $args[] = rawurlencode($key) . '=' . rawurlencode($val);
      
      if ($args)
	  $url .= '?' . implode('&', $args);
      return "http:$url";
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
      return ( $dbi->isWikiPage($page)
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


   // converts spaces to tabs
   function CookSpaces($pagearray) {
      return preg_replace("/ {3,8}/", "\t", $pagearray);
   }


   // The Recent Changes file is solely handled here
   function UpdateRecentChanges($dbi, $pagename, $prev_version) {

      global $remoteuser; // this is set in the config
      global $dateformat;

      $recentchanges = $dbi->getPage("RecentChanges");
      $lines = $recentchanges->content();
      $newpage = array();

      // scroll through the page to the first date and break
      // dates are marked with "____" at the beginning of the line
      reset($lines);
      while (list($junk, $line) = each($lines)) 
	{
	  if (preg_match("/^____/", $line))
	      break;
	  $newpage[] = $line;
	}

      // FIXME: templatize
      
      // if it's a new date, insert it, else add the updated page's
      // name to the array
      $today = date($dateformat);
      if ($prev_version)
	  $difflink = '[diff|' . WikiURL($pagename, 'diff') . ']';
      else
	  $difflink = 'new';
      $newpage[] = "____$today";
      $newpage[] = "\t* [$pagename] ($difflink) ..... $remoteuser";

      if (! preg_match("/^____$today$/", $line))
	  $newpage[] = $line;

      // copy the rest of the page into the new array
      $pagename = preg_quote($pagename);
      while (list($junk, $line) = each($lines)) 
	{
         // skip previous entry for $pagename
	  if (preg_match("|\[$pagename\]|", $line))
	      continue;
	  $newpage[] = $line;
	}

      $newchanges = new WikiPage('RecentChanges',
				 array('content' => $newpage,
				       'author' => $recentchanges->author(),
				       'refs' => $recentchanges->refs()));

      $dbi->insertPage($newchanges, true);
   }

   function strip_magic_quotes_gpc ($string) {
      return get_magic_quotes_gpc() ? stripslashes($string) : $string;
   }

   function wiki_message ($handle, $error)
   {
     SafeSetToken('Message', $error);
     SetToken('content', Template(strtoupper($handle)));
     return false;
   }

   $DebugInfo = "";
   function Debug($message)
   {
     global $DebugInfo;
     $DebugInfo .= nl2br(htmlspecialchars(chop($message) . "\n"));
   }
?>
