<?php

require_once 'lib/WikiPlugin.php';
require_once 'lib/plugin/AllPages.php';
require_once 'PHPUnit.php';

class AllPagesTest extends PHPUnit_TestCase {
    // constructor of the test suite
    function AllPagesTest($name) {
       $this->PHPUnit_TestCase($name);
    }

    /**
     * Test that we can instantiate and run AllPages plugin without error.
     */
    function testAllPages() {
        global $request;

        $lp = new WikiPlugin_AllPages();
        $this->assertEquals("AllPages", $lp->getName());
        $result = $lp->run($request->getDbh(), "pages=foo", $request);
        $this->assertType('object',$result,'isa PageList');
    }
}


?>
