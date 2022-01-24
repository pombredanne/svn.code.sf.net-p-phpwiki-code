<?php
/**
 * 1st important test: Check if all pgsrc files import without failure
 */

require_once 'lib/loadsave.php';
require_once 'PHPUnit.php';

class SetupWiki extends phpwiki_TestCase {

    function _loadPage($pagename) {
        global $request;
        $dbi = $request->getDbh();
        $dbi->purgePage($pagename);
        $this->assertFalse($dbi->isWikiPage($pagename));

        $request->setArg('source', FindFile('pgsrc/'.urlencode($pagename)));
        $request->setArg('overwrite', 1);
        LoadAny($request, $request->getArg('source'));
        $request->setArg('source', false);
        $this->assertTrue($dbi->isWikiPage($pagename));
    }

    function testIncludePagePlugin() {
        $this->_loadPage('Help/IncludePagePlugin');
    }

    function testSetupWiki() {
        global $request;

        purge_testbox();

        $dbi = $request->getDbh();
        $dbi->purgePage('HomePage'); // possibly in cache
        $this->assertFalse($dbi->isWikiPage('HomePage'));

        $request->setArg('source', FindFile('pgsrc'));
        $request->setArg('overwrite', 1);
        LoadAny($request, $request->getArg('source'));
        $request->setArg('source', false);
        $request->setArg('overwrite', false);

        $this->assertTrue($dbi->isWikiPage('HomePage'));
    }
}
