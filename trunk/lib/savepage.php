<?php rcs_id('$Id: savepage.php,v 1.11 2001-02-12 01:43:10 dairiki Exp $');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

   function UpdateRecentChanges($dbi, $pagename, $isnewpage)
   {
      global $user;
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

      $userid = $user->id();
      
      // add the updated page's name to the array
      if($isnewpage) {
         $newpage[$k++] = "* [$pagename] (new) ..... $userid\r";
      } else {
	 $diffurl = "phpwiki:" . rawurlencode($pagename) . "?action=diff";
         $newpage[$k++] = "* [$pagename] ([diff|$diffurl]) ..... $userid\r";
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

      $recentchanges['content'] = $newpage;

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

      echo GeneratePage('MESSAGE', $html,
			sprintf (gettext ("Problem while updating %s"), $pagename), 0);
      exit;
   }


   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);

   // if this page doesn't exist yet, now's the time!
   if (! is_array($pagehash)) {
      $pagehash = array();
      $pagehash['version'] = 0;
      $pagehash['created'] = time();
      $pagehash['flags'] = 0;
      $newpage = 1;
   } else {
      if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && ! $user->is_admin()) {
	 $html = "<p>" . gettext ("This page has been locked by the administrator and cannot be edited.");
	 $html .= "\n<p>" . gettext ("Sorry for the inconvenience.");
	 echo GeneratePage('MESSAGE', $html,
			   sprintf (gettext ("Problem while editing %s"), $pagename), 0);
	 ExitWiki ("");
      }

      if(isset($editversion) && ($editversion != $pagehash['version'])) {
         ConcurrentUpdates($pagename);
      }

      if ($user->id() != $pagehash['author'] && ! $user->is_admin())
	 unset($minor_edit);      // Force archive
      
      if (empty($minor_edit))
	 SaveCopyToArchive($dbi, $pagename, $pagehash);

      $newpage = 0;
   }

   // set new pageinfo
   $pagehash['lastmodified'] = time();
   $pagehash['version']++;
   $pagehash['author'] = $user->id();

   // create page header
   $html = sprintf(gettext("Thank you for editing %s."),
		   WikiURL($pagename));
   $html .= "<br>\n";

   if (! empty($content)) {
      // patch from Grant Morgan <grant@ryuuguu.com> for magic_quotes_gpc
      fix_magic_quotes_gpc($content);

      $pagehash['content'] = preg_split('/[ \t\r]*\n/', chop($content));

      // convert spaces to tabs at user request
      if (isset($convert)) {
         $pagehash['content'] = CookSpaces($pagehash['content']);
      }
   }

   InsertPage($dbi, $pagename, $pagehash);
   UpdateRecentChanges($dbi, $pagename, $newpage);

   $html .= gettext ("Your careful attention to detail is much appreciated.");
   $html .= "\n";

   // fixme: no test for flat file db system
   if (!empty($DBWarning)) {
      $html .= "<P><B>Warning: $DBWarning" .
		"Please read the INSTALL file and move " .
		"the DB file to a permanent location or risk losing " .
		"all the pages!</B>\n";
   }

   if (!empty($SignatureImg))
      $html .= sprintf("<P><img src=\"%s\"></P>\n", DataURL($SignatureImg));
      
   $html .= "<hr noshade><P>";
   include('lib/transform.php');

   echo GeneratePage('BROWSE', $html, $pagename, $pagehash);
?>
