<?php rcs_id('$Id: savepage.php,v 1.15 2001-06-26 18:08:32 uckelman Exp $');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/


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
      ExitWiki();
   }


   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore, 0);

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

		SavePageToArchive($pagename, $pagehash);
		$newpage = 0;
	}

   // set new pageinfo
   $pagehash['lastmodified'] = time();
   $pagehash['version']++;
   $pagehash['author'] = $user->id();

   // create page header
   $html = sprintf(gettext("Thank you for editing %s."),
		   LinkExistingWikiWord($pagename));
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

   ReplaceCurrentPage($pagename, $pagehash);
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
      
   $html .= "<hr noshade>\n";
   include('lib/transform.php');

   echo GeneratePage('BROWSE', $html, $pagename, $pagehash);
?>
