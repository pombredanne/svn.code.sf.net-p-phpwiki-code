<?php
/**
 * Check if all pgsrc files import without failure
 */

require_once 'lib/loadsave.php';
require_once 'PHPUnit.php';

class SetupWiki extends PHPUnit_TestCase {
    function SetupWiki($name) {
       $this->PHPUnit_TestCase($name);
    }

    function testImportOldMarkup() {
        global $request;
        $dbi = $request->getDbh();
        $pagename = 'OldMarkupTestPage';
        $dbi->deletePage($pagename);
        $this->assertFalse($dbi->isWikiPage($pagename));

        $request->setArg('source', FindFile('pgsrc/'.$pagename));
        $request->setArg('overwrite', 1);
        LoadAny($request, $request->getArg('source'));
        $this->assertTrue($dbi->isWikiPage($pagename));
    }

    function testSetupWiki() {
        global $request;

        $dbi = $request->getDbh();
        $dbi->deletePage('HomePage');
        $this->assertFalse($dbi->isWikiPage('HomePage'));

        $request->setArg('source', FindFile('pgsrc'));
        $request->setArg('overwrite', 1);
        LoadAny($request, $request->getArg('source'));
        
        $this->assertTrue($dbi->isWikiPage('HomePage'));
    }
}


?>
