<!-- $Id: editpage.php,v 1.3 2000-10-19 22:25:45 ahollosi Exp $ -->
<?php

   // editpage relies on $pagename and $ScriptUrl

   if ($edit) {
      $pagename = rawurldecode($edit);
      if (get_magic_quotes_gpc()) {
         $pagename = stripslashes($pagename);
      }
      $banner = htmlspecialchars($pagename);
      $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);

   } elseif ($copy) {
      $pagename = rawurldecode($copy);
      if (get_magic_quotes_gpc()) {
         $pagename = stripslashes($pagename);
      }
      $banner = htmlspecialchars("Copy of $pagename");
      $pagehash = RetrievePage($dbi, $pagename, $ArchivePageStore);

   } else
      ExitWiki("No page name passed into editpage!");


   if (is_array($pagehash)) {

      if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && !$admin_edit) {
	 $html = "<p>This page has been locked by the administrator\n" .
		 "and cannot be edited.\n" .
		 "<p>Sorry for the inconvinience.\n";
	 GeneratePage('MESSAGE', $html, "Problem while editing $pagename", 0);
	 ExitWiki('');
      }

      $textarea = implode("\n", $pagehash["content"]);
      if ($copy) {
	 // $cdbi = OpenDataBase($WikiPageStore);
	 $currentpage = RetrievePage($dbi, $pagename, $WikiPageStore);
         $pagehash["version"] = $currentpage["version"];
      }
      elseif ($pagehash["version"] > 1) {
	 if(IsInArchive($dbi, $pagename))
           $pagehash["copy"] = 1;
      }
   } else {
      $textarea = sprintf(gettext("Describe %s here."),
			  htmlspecialchars($pagename));
      unset($pagehash);
      $pagehash["version"] = 0;
   }

   GeneratePage('EDITPAGE', $textarea, $pagename, $pagehash);   
?>
