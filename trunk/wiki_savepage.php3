<!-- $Id: wiki_savepage.php3,v 1.5 2000-06-07 11:10:46 ahollosi Exp $ -->
<?

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

   function ConcurrentUpdates($pagename)
   {
      echo WikiHeader("Problem while updating $pagename");
      $pagename = htmlspecialchars($pagename);
      echo "<h1>Problem while updating $pagename</h1>\n" .
	   "<P>PhpWiki is unable to save your changes, because\n" .
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
      echo WikiFooter();
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
   echo WikiHeader("Thanks for $pagename Edits");
   $enc_url = rawurlencode($pagename);
   $enc_name = htmlspecialchars($pagename);
   echo "Thank you for editing " .
	"<a href=\"$ScriptUrl?$enc_url\">$enc_name</a><br>\n";


   if (! empty($text)) {
      // patch from Grant Morgan <grant@ryuuguu.com> for
      // magic_quotes_gpc
      if(get_magic_quotes_gpc()) { $text = stripslashes($text); }

      $pagehash["text"] = explode("\n", $text);

      // convert spaces to tabs at user request
      if ($convert) {
         $pagehash["text"] = CookSpaces($pagehash["text"]);      
      }
   }

   for ($i = 1; $i <= NUM_LINKS; $i++) {
        if (! empty(${'r'.$i})) {
	   if (preg_match("#^($AllowedProtocols):#", ${'r'.$i}))
              $pagehash['r'.$i] = ${'r'.$i};
	   else
	      echo "<P>Link [$i]: <B>unknown protocol</B>" .
	           " - use one of $AllowedProtocols - link discarded.</P>\n";
	}
   }

   InsertPage($dbi, $pagename, $pagehash);
   UpdateRecentChanges($dbi, $pagename, $newpage);
?>
Your careful attention to detail is much appreciated.<br>
<img src="<? echo "$SignatureImg"; ?>"><br>
p.s. Be sure to <em>Reload</em> your old pages.<br>

<?
   if ($WikiDataBase == "/tmp/wikidb") {
      echo "<h2>Warning: the Wiki DBM file still lives in the ";
      echo "/tmp directory. Please read the INSTALL file and move ";
      echo "the DBM file to a permanent location or risk losing ";
      echo "all the pages!</h2>\n";
   }
   echo WikiFooter();
?>
