<?php
// display.php: fetch page or get default content
// calls transform.php for actual transformation of wiki markup to HTML
rcs_id('$Id: display.php,v 1.11 2001-09-19 03:24:36 wainstead Exp $');

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
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>
