<?php
// display.php: fetch page or get default content
// calls transform.php for actual transformation of wiki markup to HTML
rcs_id('$Id: display.php,v 1.10 2001-09-18 19:16:23 dairiki Exp $');

require_once('lib/Template.php');
require_once('lib/transform.php');

function displayPage($dbi, $request) {
   $pagename = $request->getArg('pagename');
   $version = $request->getArg('version');
   
   $page = $dbi->getPage($pagename);
   if ($version) {
      $revision = $page->getRevision($version);
      if (!$revision)
         NoSuchRevision($page, $version);
   }
   else {
      $revision = $page->getCurrentRevision();
   }

   $template = new WikiTemplate('BROWSE');
   $template->setPageRevisionTokens($revision);
   $template->replace('CONTENT', do_transform($revision->getContent()));
   echo $template->getExpansion();
   flush();
   $page->increaseHitCount();
}

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
