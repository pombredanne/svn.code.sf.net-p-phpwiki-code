<!-- $Id: wiki_editpage.php3,v 1.9 2000-06-21 23:55:45 ahollosi Exp $ -->
<?

   // editpage relies on $pagename and $ScriptUrl

   if ($edit) {
      $pagename = rawurldecode($edit);
      if (get_magic_quotes_gpc()) {
         $pagename = stripslashes($pagename);
      }
      $banner = htmlspecialchars($pagename);
   } elseif ($copy) {
      $pagename = rawurldecode($copy);
      if (get_magic_quotes_gpc()) {
         $pagename = stripslashes($pagename);
      }
      $banner = htmlspecialchars("Copy of $pagename");
   } else {
      echo "No page name passed into editpage!<br>\n";
      exit();
   }

   $pagehash = RetrievePage($dbi, $pagename);

   if (is_array($pagehash)) {
      $textarea = implode("\n", $pagehash["content"]);
      if($copy) {
	 $cdbi = OpenDataBase($WikiDataBase);
	 $currentpage = RetrievePage($cdbi, $pagename);
         $pagehash["version"] = $currentpage["version"];
      }
      elseif ($pagehash["version"] > 1) {
	 $adbi = OpenDataBase($ArchiveDataBase);
	 if(IsWikiPage($adbi, $pagename))
           $pagehash["copy"] = 1;
      }
   } else {
      $textarea = "Describe " . htmlspecialchars($pagename) . " here.";
      unset($pagehash);
      $pagehash["version"] = 0;
   }

   GeneratePage('EDITPAGE', $textarea, $pagename, $pagehash);   
?>
