<!-- $Id: editpage.php,v 1.10 2001-02-10 22:15:08 dairiki Exp $ -->
<?php

   // editpage relies on $pagename and $ScriptUrl

   $currentpage = RetrievePage($dbi, $pagename, $WikiPageStore);
   $editing_copy = isset($version) && $version == 'archive';

   if ($editing_copy) {   
      $banner = htmlspecialchars (sprintf (gettext ("Copy of %s"), $pagename));
      $pagehash = RetrievePage($dbi, $pagename, $ArchivePageStore);
   } else {
      $banner = htmlspecialchars($pagename);
      $pagehash = $currentpage;
   }

   if (is_array($pagehash)) {

      if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && $user->is_admin()) {
	 $html = "<p>";
	 $html .= gettext ("This page has been locked by the administrator and cannot be edited.");
	 $html .= "\n<p>";
	 $html .= gettext ("Sorry for the inconvenience.");
	 $html .= "\n";
	 GeneratePage('MESSAGE', $html, sprintf (gettext ("Problem while editing %s"), $pagename), 0);
	 ExitWiki ("");
      }

      $textarea = implode("\n", $pagehash["content"]);
      if ($editing_copy) {
         $pagehash["version"] = $currentpage["version"];
      }
      else {
	 if ($pagehash["version"] > 1 && IsInArchive($dbi, $pagename)) {
	    $pagehash["copy"] = 1;
	 }
      }
   } else {
      $textarea = sprintf(gettext ("Describe %s here."),
				htmlspecialchars($pagename));
      unset($pagehash);
      $pagehash["version"] = 0;
      $pagehash["lastmodified"] = time();
      $pagehash["author"] = '';
      $currentpage = $pagehash;
   }


   if ($user->id() == $currentpage['author'] || $user->is_admin()) {
      $ckbox = element('input', array('type' => 'checkbox',
				      'name' => 'minor_edit',
				      'value' => 'yes'));
      $page_age = time() - $currentpage['lastmodified'];
      if ($user->id() == $currentpage['author'] && $page_age < MINOR_EDIT_TIMEOUT)
	 $ckbox .= " checked";
      $pagehash['minor_edit_checkbox'] = $ckbox . '>';
   }

   GeneratePage('EDITPAGE', $textarea, $pagename, $pagehash);   
?>
