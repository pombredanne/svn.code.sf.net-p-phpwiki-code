<!-- $Id: wiki_editpage.php3,v 1.11 2000-08-07 22:47:40 wainstead Exp $ -->
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

      if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && !$admin_edit) {
	 $html = "<p>This page has been locked by the administrator\n" .
		 "and cannot be edited.\n" .
		 "<p>Sorry for the inconvinience.\n";
	 GeneratePage('MESSAGE', $html, "Problem while editing $pagename", 0);
	 exit;
      }

      $textarea = implode("\n", $pagehash["content"]);
      if($copy) {
	 $cdbi = OpenDataBase($WikiPageStore);
	 $currentpage = RetrievePage($cdbi, $pagename);
         $pagehash["version"] = $currentpage["version"];
      }
      elseif ($pagehash["version"] > 1) {
	 $adbi = OpenDataBase($ArchivePageStore);
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
