<!-- $Id: editpage.php,v 1.8.2.3 2005-01-07 13:59:58 rurban Exp $ -->
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
      $banner = htmlspecialchars (sprintf (gettext ("Copy of %s"), $pagename));
      $pagehash = RetrievePage($dbi, $pagename, $ArchivePageStore);

   } else {
      ExitWiki(gettext ("No page name passed into editpage!"));
   }


   if (is_array($pagehash)) {

      if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && !defined('WIKI_ADMIN')) {
	 $html = "<p>";
	 $html .= gettext ("This page has been locked by the administrator and cannot be edited.");
	 $html .= "\n<p>";
	 $html .= gettext ("Sorry for the inconvenience.");
	 $html .= "\n";
	 GeneratePage('MESSAGE', $html, sprintf (gettext ("Problem while editing %s"), $pagename), 0);
	 ExitWiki ("");
      }

      $textarea = htmlspecialchars(implode("\n", $pagehash["content"]));
      if (isset($copy)) {
	 // $cdbi = OpenDataBase($WikiPageStore);
	 $currentpage = RetrievePage($dbi, $pagename, $WikiPageStore);
         $pagehash["version"] = $currentpage["version"];
      }
      elseif ($pagehash["version"] > 1) {
	 if(IsInArchive($dbi, $pagename))
           $pagehash["copy"] = 1;
      }
   } else {
      if (preg_match("/^${WikiNameRegexp}\$/", $pagename))
	 $newpage = $pagename;
      else
	 $newpage = "[$pagename]";

      $textarea = htmlspecialchars(
	 sprintf(gettext ("Describe %s here."), $newpage));

      unset($pagehash);
      $pagehash["version"] = 0;
      $pagehash["lastmodified"] = time();
      $pagehash["author"] = '';
   }

   GeneratePage('EDITPAGE', $textarea, $pagename, $pagehash);   
?>