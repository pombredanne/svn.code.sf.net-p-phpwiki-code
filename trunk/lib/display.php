<?php
   // display.php: fetch page or get default content
   // calls transform.php for actual transformation of wiki markup to HTML
   rcs_id('$Id: display.php,v 1.9 2001-06-26 18:04:54 uckelman Exp $');

	if (!isset($version)) $version = 0;
	$pagestore = SelectStore($dbi, $pagename, $version, $WikiPageStore, $ArchivePageStore);
   $pagehash = RetrievePage($dbi, $pagename, $pagestore, $version);
   
	$html = "";

   // we render the page if it exists, else ask the user to write one.
   if (is_array($pagehash)) {
      // transform.php returns $html containing all the HTML markup
      include("lib/transform.php");
   } else {
      $html .= sprintf(gettext("Describe %s here."),
		       LinkUnknownWikiWord($pagename));
   }

   echo GeneratePage('BROWSE', $html, $pagename, $pagehash);
   flush();

   IncreaseHitCount($dbi, $pagename);
// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
