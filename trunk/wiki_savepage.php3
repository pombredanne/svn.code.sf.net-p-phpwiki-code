<?

/*
   All page saving events take place here. All page info is also 
   taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

   $pagename = $post;
   $pagehash = RetrievePage($dbi, $pagename);

   // if this page doesn't exist yet, now's the time!
   if (! is_array($pagehash)) {
      $pagehash = array();
      $pagehash["version"] = 0;
   } else {
      // archive it if it's a new author
      if ($pagehash["author"] != $remoteuser) {
         SaveCopyToArchive($pagename, $pagehash);
      }
   }

   $pagehash["date"] = GetCurrentDate();
   $pagehash["version"]++;
   $pagehash["author"] = $remoteuser;

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

   // Ward Cunningham has deprecated these, but I already 
   // implented them. You are discouraged from using them.
   if (! empty($r1)) { $pagehash["r1"] = $r1; } 
   if (! empty($r2)) { $pagehash["r2"] = $r2; }
   if (! empty($r3)) { $pagehash["r3"] = $r3; } 
   if (! empty($r4)) { $pagehash["r4"] = $r4; }

   InsertPage($dbi, $pagename, $pagehash);
   UpdateRecentChanges($dbi, $pagename);
   WikiHeader("Thanks for $pagename Edits");
?>
Thank you for editing
<?

   echo "<a href=\"$ScriptUrl?$pagename\">$pagename</a><br>\n";

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
   WikiFooter();
?>
