<?php

require_once 'lib/WikiPlugin.php';
require_once 'lib/plugin/WantedPages.php';
require_once 'PHPUnit.php';

class OrphanedPagesTest extends phpwiki_TestCase {
    /**
     * Test that we can instantiate and run OrphanedPages plugin without error.
     */
    function testOrphanedPages() {
        global $request;

        $lp = new WikiPlugin_WantedPages();
        $this->assertEquals("WantedPages", $lp->getName());

        $basepage = "";
        $args = "";
        $result = $lp->run($request->getDbh(), $args, $request, $basepage);
        $this->assertType('object',$result,'isa PageList');

        $args = "HomePage";
        $result = $lp->run($request->getDbh(), $args, $request, $basepage);
        $this->assertType('object',$result,'isa PageList');
    }
}


?>
