<!-- $Id: wiki_editpage.php3,v 1.8 2000-06-18 15:12:13 ahollosi Exp $ -->
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
      $textarea = implode($pagehash["content"], "\n");
      if (($pagehash["version"] > 1) &&
          ($pagehash["author"] != $remoteuser)) {  ### FIXME - should compare with author of archived version
         $pagehash["copy"] = 1;
      }
      if($copy) {		### FIXME - version++ is wrong
         $pagehash["version"]++;
      }
   } else {
      $textarea = "Describe " . htmlspecialchars($pagename) . " here.";
      unset($pagehash);
      $pagehash["version"] = 0;
   }

   GeneratePage('EDITPAGE', $textarea, $pagename, $pagehash);   
?>
