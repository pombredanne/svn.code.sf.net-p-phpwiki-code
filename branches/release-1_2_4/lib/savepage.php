<?php rcs_id('$Id: savepage.php,v 1.7.2.3.2.1 2005-01-07 13:48:42 rurban Exp $');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

   function UpdateRecentChanges($dbi, $pagename, $isnewpage)
   {
      global $remoteuser; // this is set in the config
      global $dateformat;
      global $WikiPageStore;

      $recentchanges = RetrievePage($dbi, gettext ("RecentChanges"), $WikiPageStore);

      // this shouldn't be necessary, since PhpWiki loads 
      // default pages if this is a new baby Wiki
      if ($recentchanges == -1) {
         $recentchanges = array(); 
      }

      $now = time();
      $today = date($dateformat, $now);

      if (date($dateformat, $recentchanges['lastmodified']) != $today) {
         $isNewDay = TRUE;
         $recentchanges['lastmodified'] = $now;
      } else {
         $isNewDay = FALSE;
      }

      $numlines = sizeof($recentchanges['content']);
      $newpage = array();
      $k = 0;

      // scroll through the page to the first date and break
      // dates are marked with "____" at the beginning of the line
      for ($i = 0; $i < $numlines; $i++) {
         if (preg_match("/^____/",
                        $recentchanges['content'][$i])) {
            break;
         } else {
            $newpage[$k++] = $recentchanges['content'][$i];
         }
      }

      // if it's a new date, insert it
      $newpage[$k++] = $isNewDay ? "____$today\r"
				 : $recentchanges['content'][$i++];

      // add the updated page's name to the array
      if($isnewpage) {
         $newpage[$k++] = "* [$pagename] (new) ..... $remoteuser\r";
      } else {
	 $diffurl = "phpwiki:?diff=" . rawurlencode($pagename);
         $newpage[$k++] = "* [$pagename] ([diff|$diffurl]) ..... $remoteuser\r";
      }
      if ($isNewDay)
         $newpage[$k++] = "\r";

      // copy the rest of the page into the new array
      // and skip previous entry for $pagename
      $pagename = preg_quote($pagename);
      for (; $i < $numlines; $i++) {
         if (!preg_match("|\[$pagename\]|", $recentchanges['content'][$i])) {
            $newpage[$k++] = $recentchanges['content'][$i];
         }
      }

      // copy the new page back into recentchanges, skipping empty days
      $numlines = sizeof($newpage);
      $recentchanges['content'] = array();
      $k = 0;
      for ($i = 0; $i < $numlines; $i++) {
         if ($i != $numlines-1 &&
             preg_match("/^____/", $newpage[$i]) &&
             preg_match("/^[\r\n]*$/", $newpage[$i+1])) {
            $i++;
         } else {
            $recentchanges['content'][$k++] = $newpage[$i];
         }
      }

      InsertPage($dbi, gettext ("RecentChanges"), $recentchanges);
   }


   function ConcurrentUpdates($pagename)
   {
      /* xgettext only knows about c/c++ line-continuation strings
        is does not know about php's dot operator.
        We want to translate this entire paragraph as one string, of course.
      */
      $html = "<P>";
      $html .= gettext ("PhpWiki is unable to save your changes, because another user edited and saved the page while you were editing the page too. If saving proceeded now changes from the previous author would be lost.");
      $html .= "</P>\n<P>";
      $html .= gettext ("In order to recover from this situation follow these steps:");
      $html .= "\n<OL><LI>";
      $html .= gettext ("Use your browser's <b>Back</b> button to go back to the edit page.");
      $html .= "\n<LI>";
      $html .= gettext ("Copy your changes to the clipboard or to another temporary place (e.g. text editor).");
      $html .= "\n<LI>";
      $html .= gettext ("<b>Reload</b> the page. You should now see the most current version of the page. Your changes are no longer there.");
      $html .= "\n<LI>";
      $html .= gettext ("Make changes to the file again. Paste your additions from the clipboard (or text editor).");
      $html .= "\n<LI>";
      $html .= gettext ("Press <b>Save</b> again.");
      $html .= "</OL>\n<P>";
      $html .= gettext ("Sorry for the inconvenience.");
      $html .= "</P>";

      GeneratePage('MESSAGE', $html,
	sprintf (gettext ("Problem while updating %s"), $pagename), 0);
      exit;
   }


   if (get_magic_quotes_gpc()) {
      $post = stripslashes($post);
   }
   $pagename = rawurldecode($post);
   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);

   // if this page doesn't exist yet, now's the time!
   if (! is_array($pagehash)) {
      $pagehash = array();
      $pagehash['version'] = 0;
      $pagehash['created'] = time();
      $pagehash['flags'] = 0;
      $newpage = 1;
   } else {
      if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && !defined('WIKI_ADMIN')) {
	 $html = "<p>" . gettext ("This page has been locked by the administrator and cannot be edited.");
	 $html .= "\n<p>" . gettext ("Sorry for the inconvenience.");
	 GeneratePage('MESSAGE', $html, sprintf (gettext ("Problem while editing %s"), $pagename), 0);
	 ExitWiki ("");
      }

      if(isset($editversion) && ($editversion != $pagehash['version'])) {
         ConcurrentUpdates($pagename);
      }

      // archive it if it's a new author
      if ($pagehash['author'] != $remoteuser) {
         SaveCopyToArchive($dbi, $pagename, $pagehash);
      }
      $newpage = 0;
   }

   // set new pageinfo
   $pagehash['lastmodified'] = time();
   $pagehash['version']++;
   $pagehash['author'] = $remoteuser;

   // create page header
   $enc_url = rawurlencode($pagename);
   $enc_name = htmlspecialchars($pagename);
   $html = sprintf(gettext("Thank you for editing %s."),
		   "<a href=\"$ScriptUrl?$enc_url\">$enc_name</a>");
   $html .= "<br>\n";

   if (! empty($content)) {
      // patch from Grant Morgan <grant@ryuuguu.com> for magic_quotes_gpc
      if (get_magic_quotes_gpc())
         $content = stripslashes($content);

      $pagehash['content'] = preg_split('/[ \t\r]*\n/', chop($content));

      // convert spaces to tabs at user request
      if (isset($convert)) {
         $pagehash['content'] = CookSpaces($pagehash['content']);
      }
   }

   for ($i = 1; $i <= NUM_LINKS; $i++) {
        if (! empty(${'r'.$i})) {
	   if (preg_match("#^($AllowedProtocols):#", ${'r'.$i}))
              $pagehash['refs'][$i] = ${'r'.$i};
	   else
	      $html .= "<P>Link [$i]: <B>unknown protocol</B>" .
	           " - use one of $AllowedProtocols - link discarded.</P>\n";
	}
   }

   InsertPage($dbi, $pagename, $pagehash);
   UpdateRecentChanges($dbi, $pagename, $newpage);

   $html .= gettext ("Your careful attention to detail is much appreciated.");
   $html .= "\n";

   // fixme: no test for flat file db system
   if (isset($DBMdir) && preg_match('@^/tmp\b@', $DBMdir)) {
      $html .= "<P><B>Warning: the Wiki DB files still live in the " .
		"/tmp directory. Please read the INSTALL file and move " .
		"the DB files to a permanent location or risk losing " .
		"all the pages!</B>\n";
   }

   if (!empty($SignatureImg))
      $html .= "<P><img src=\"$SignatureImg\"></P>\n";

   $html .= "<hr noshade><P>";
   include('lib/transform.php');

   GeneratePage('BROWSE', $html, $pagename, $pagehash);
?>
