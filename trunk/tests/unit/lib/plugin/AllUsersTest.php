<?php

require_once 'lib/WikiPlugin.php';
require_once 'lib/plugin/AllUsers.php';
require_once 'PHPUnit.php';

class AllUsersTest extends PHPUnit_TestCase {
    // constructor of the test suite
    function AllUsersTest($name) {
       $this->PHPUnit_TestCase($name);
    }

    /**
     * Test that we can instantiate and run AllUsers plugin without error.
     */
    function testAllUsers() {
        global $request;

        $lp = new WikiPlugin_AllUsers();
        $this->assertEquals("AllUsers", $lp->getName());
        $basepage = "";
        $args = "";
        $result = $lp->run($request->getDbh(), $args, $request, $basepage);
        $this->assertType('object',$result,'isa PageList');
    }
}


?>
