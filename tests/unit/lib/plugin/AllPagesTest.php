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
        $args = "";
        if (!isa($request->_dbi, "WikiDB")) {
        	// very very strange bug
			$request->_dbi = WikiDB::open($GLOBALS['db_params']);
	        if (!isa($request->_dbi, "WikiDB")) {
	        	trigger_error("strange php bug\n",E_USER_WARNING);
	        	return;
	        }
	    }
        $result = $lp->run($request->getDbh(), $args, $request, "AllPages");
        $this->assertType('object', $result, 'isa PageList');
    }
}


?>
