<?php
/**
 * Check if all pgsrc files import without failure
 */

require_once 'lib/loadsave.php';
require_once 'PHPUnit.php';

class SetupWiki extends PHPUnit_TestCase {
    // constructor of the test suite
    function SetupWiki($name) {
       $this->PHPUnit_TestCase($name);
    }

    function testSetupWiki() {
        global $request;

        $request->setArg('source', FindFile('pgsrc'));
        $request->setArg('overwrite', 1);
        LoadAny($request, $request->getArg('source'));
        
        $dbh = $request->getDbh();
        $this->assertTrue($dbh->isWikiPage('HomePage'));
    }
}


?>
