<!-- $Id: savepage.php,v 1.3 2000-10-26 15:38:38 ahollosi Exp $ -->
<?php

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
      $html = "<P>" . gettext ("PhpWiki is unable to save your changes, because another user edited and saved the page while you were editing the page too. If saving proceeded now changes from the previous author would be lost.") . "</P>\n<P>" .
	gettext ("In order to recover from this situation follow these steps:") . "\n<OL><LI>" .
	gettext ("Use your browser's <b>Back</b> button to go back to the edit page.") . "\n<LI>" .
	gettext ("Copy your changes to the clipboard or to another temporary place (e.g. text editor).") . "\n<LI>" .
	gettext ("<b>Reload</b> the page. You should now see the most current version of the page. Your changes are no longer there.") . "\n<LI>" .
	gettext ("Make changes to the file again. Paste your additions from the clipboard (or text editor).") . "\n<LI>" .
	gettext ("Press <b>Save</b> again.") . "</OL>\n<P>" .
	gettext ("Sorry for the inconvenience.") ."</P>";

      GeneratePage('MESSAGE', $html,
	sprintf (gettext ("Problem while updating %s"), $pagename), 0);
      exit;
   }

   $pagename = rawurldecode($post);
   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);

   // if this page doesn't exist yet, now's the time!
   if (! is_array($pagehash)) {
      $pagehash = array();
      $pagehash["version"] = 0;
      $pagehash["created"] = time();
      $pagehash["flags"] = 0;
      $newpage = 1;
   } else {
      if(isset($editversion) && ($editversion != $pagehash["version"])) {
         ConcurrentUpdates($pagename);
      }
      // archive it if it's a new author
      if ($pagehash["author"] != $remoteuser) {
         SaveCopyToArchive($dbi, $pagename, $pagehash);
      }
      $newpage = 0;
   }

   $pagehash["lastmodified"] = time();
   $pagehash["version"]++;
   $pagehash["author"] = $remoteuser;

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

      $pagehash["content"] = preg_split('/[ \t\r]*\n/', chop($content));

      // convert spaces to tabs at user request
      if ($convert) {
         $pagehash["content"] = CookSpaces($pagehash["content"]);      
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

   if ($WikiPageStore == "/tmp/wikidb") {
      $html .= "<P><B>Warning: the Wiki DBM file still lives in the " .
		"/tmp directory. Please read the INSTALL file and move " .
		"the DBM file to a permanent location or risk losing " .
		"all the pages!</B>\n";
   }

   $html .= "<P><img src=\"$SignatureImg\"></P><hr noshade><P>";
   include("lib/transform.php");

   GeneratePage('BROWSE', $html, $pagename, $pagehash);
?>
