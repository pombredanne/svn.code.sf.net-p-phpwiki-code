<!-- $Id: wiki_editpage.php3,v 1.10.2.1 2000-07-21 18:29:07 dairiki Exp $ -->
<?

   // editpage relies on $pagename and $ScriptUrl
   if (!$pagename)
      echo "No page name passed into editpage!<br>\n";

   if ($action == 'copy')
      $banner = htmlspecialchars("Copy of $pagename");
   else   
      $banner = htmlspecialchars($pagename);


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
      if($action == 'copy') {
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
