<?php
/**
 * Check if all pgsrc files import without failure
 */

require_once 'lib/loadsave.php';
require_once 'PHPUnit.php';

class SetupWiki extends phpwiki_TestCase {

    function testImportOldMarkup() {
        global $request;
        $dbi = $request->getDbh();
        $pagename = 'OldMarkupTestPage';
        $dbi->deletePage($pagename);
        $this->assertFalse($dbi->isWikiPage($pagename));

        $request->setArg('source', FindFile('pgsrc/'.$pagename));
        $request->setArg('overwrite', 1);
        LoadAny($request, $request->getArg('source'));
        $request->setArg('source', false);
        $this->assertTrue($dbi->isWikiPage($pagename));
    }

    function testSetupWiki() {
        global $request;

        print "Purge the testbox .. ";
        purge_testbox();
        
        $dbi = $request->getDbh();
        $dbi->deletePage('HomePage'); // possibly in cache
        $this->assertFalse($dbi->isWikiPage('HomePage'));

        $request->setArg('source', FindFile('pgsrc'));
        $request->setArg('overwrite', 1);
        LoadAny($request, $request->getArg('source'));
        $request->setArg('source', false);
        $request->setArg('overwrite', false);
        
        $this->assertTrue($dbi->isWikiPage('HomePage'));
    }
}

?>
