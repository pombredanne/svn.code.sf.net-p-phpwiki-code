<?php
rcs_id('$Id: editpage.php,v 1.16 2001-06-26 18:08:32 uckelman Exp $');

   // editpage relies on $pagename, $version

   $currentpage = RetrievePage($dbi, $pagename, SelectStore($dbi, $pagename, $version, $WikiPageStore, $ArchivePageStore), $version);

	$banner = htmlspecialchars($pagename);
	$pagehash = $currentpage;

   if (is_array($pagehash)) {
		if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && !$user->is_admin()) {
		 $html = "<p>";
		 $html .= gettext ("This page has been locked by the administrator and cannot be edited.");
		 $html .= "\n<p>";
		 $html .= gettext ("Sorry for the inconvenience.");
		 $html .= "\n";
		 echo GeneratePage('MESSAGE', $html, sprintf (gettext ("Problem while editing %s"), $pagename), 0);
		 ExitWiki ("");
      }

		$textarea = htmlspecialchars(implode("\n", $pagehash["content"]));
	}
	else {
      if (preg_match("/^${WikiNameRegexp}\$/", $pagename)) $newpage = $pagename;
      else $newpage = "[$pagename]";
      
		$textarea = htmlspecialchars(sprintf(gettext("Describe %s here."), $newpage));

      unset($pagehash);
      $pagehash["version"] = 0;
      $pagehash["lastmodified"] = time();
      $pagehash["author"] = '';
      $currentpage = $pagehash;
   }

   echo GeneratePage('EDITPAGE', $textarea, $pagename, $pagehash);   

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
