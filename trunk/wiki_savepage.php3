<!-- $Id: wiki_savepage.php3,v 1.8 2000-06-18 15:12:13 ahollosi Exp $ -->
<?

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

   function ConcurrentUpdates($pagename)
   {
      $html = "<P>PhpWiki is unable to save your changes, because\n" .
	   "another user edited and saved the page while you\n" .
	   "were editing the page too. If saving proceeded now\n" .
	   "changes from the previous author would be lost.</P>\n" .
	   "<P>In order to recover from this situation follow these steps:\n" .
	   "<OL><LI>Use your browsers <B>Back</B> button to go back " .
	   "to the edit page.\n" .
	   "<LI>Copy your changes to the clipboard or to another temporary " .
	   "place (e.g. text editor).\n" .
	   "<LI><B>Reload</B> the page. You should now see the most current" .
	   " version of the page. Your changes are no longer there.\n" .
	   "<LI>Make changes to the file again. Paste your additions from " .
	   "the clipboard (or text editor).\n" .
	   "<LI>Press <B>Save</B> again.</OL>\n" .
	   "<P>Sorry for the inconvinience.</P>";
      GeneratePage('MESSAGE', $html, "Problem while updating " .
		   htmlspecialchars($pagename), 0);
      exit;
   }

   $pagename = rawurldecode($post);
   $pagehash = RetrievePage($dbi, $pagename);

   // if this page doesn't exist yet, now's the time!
   if (! is_array($pagehash)) {
      $pagehash = array();
      $pagehash["version"] = 0;
      $newpage = 1;
   } else {
      if(isset($editversion) && ($editversion != $pagehash["version"])) {
         ConcurrentUpdates($pagename);
      }
      // archive it if it's a new author
      if ($pagehash["author"] != $remoteuser) {
         SaveCopyToArchive($pagename, $pagehash);
      }
      $newpage = 0;
   }

   $pagehash["date"] = GetCurrentDate();
   $pagehash["version"]++;
   $pagehash["author"] = $remoteuser;

   // create page header
   $enc_url = rawurlencode($pagename);
   $enc_name = htmlspecialchars($pagename);
   $html = "Thank you for editing " .
	"<a href=\"$ScriptUrl?$enc_url\">$enc_name</a><br>\n";

   if (! empty($content)) {
      // patch from Grant Morgan <grant@ryuuguu.com> for magic_quotes_gpc
      if (get_magic_quotes_gpc())
         $content = stripslashes($content);

      $pagehash["content"] = explode("\n", $content);

      // convert spaces to tabs at user request
      if ($convert) {
         $pagehash["content"] = CookSpaces($pagehash["content"]);      
      }
   }

   for ($i = 1; $i <= NUM_LINKS; $i++) {
        if (! empty(${'r'.$i})) {
	   if (preg_match("#^($AllowedProtocols):#", ${'r'.$i}))
              $pagehash['r'.$i] = ${'r'.$i};
	   else
	      $html .= "<P>Link [$i]: <B>unknown protocol</B>" .
	           " - use one of $AllowedProtocols - link discarded.</P>\n";
	}
   }

   InsertPage($dbi, $pagename, $pagehash);
   UpdateRecentChanges($dbi, $pagename, $newpage);

   $html .= "Your careful attention to detail is much appreciated.\n";

   if ($WikiDataBase == "/tmp/wikidb") {
      $html .= "<P><B>Warning: the Wiki DBM file still lives in the " .
		"/tmp directory. Please read the INSTALL file and move " .
		"the DBM file to a permanent location or risk losing " .
		"all the pages!</B>\n";
   }

   $html .= "<P><img src=\"$SignatureImg\"></P><hr noshade><P>";
   include("wiki_transform.php3");

   GeneratePage('BROWSE', $html, $pagename, $pagehash);
?>
