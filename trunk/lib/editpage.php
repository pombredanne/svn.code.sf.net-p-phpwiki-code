<?php
rcs_id('$Id: editpage.php,v 1.12 2001-02-13 05:54:38 dairiki Exp $');

   // editpage relies on $pagename, $version

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

      if (($pagehash['flags'] & FLAG_PAGE_LOCKED) && !$user->is_admin()) {
	 $html = "<p>";
	 $html .= gettext ("This page has been locked by the administrator and cannot be edited.");
	 $html .= "\n<p>";
	 $html .= gettext ("Sorry for the inconvenience.");
	 $html .= "\n";
	 echo GeneratePage('MESSAGE', $html,
			   sprintf (gettext ("Problem while editing %s"), $pagename), 0);
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

   if (empty($pagehash['copy']))
      $do_archive = false;
   else if ( $user->is_admin() )
      $do_archive = 'probably';
   else if ( $user->id() == $currentpage['author'] )
   {
      $page_age = time() - $currentpage['lastmodified'];
      if ($page_age < MINOR_EDIT_TIMEOUT)
	 $do_archive = 'maybe';
      else
	 $do_archive = 'probably';
   }
   else
      $do_archive = 'force';

   if ($do_archive == 'probably' || $do_archive == 'maybe')
   {
      $pagehash['minor_edit_checkbox']
	  = Element('input', array('type' => 'checkbox',
				   'name' => 'minor_edit',
				   'value' => 'yes',
				   'checked' => ($do_archive == 'probably')));
   }

   echo GeneratePage('EDITPAGE', $textarea, $pagename, $pagehash);   

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
