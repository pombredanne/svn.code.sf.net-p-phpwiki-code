<?php
/**
 * Check if all pages can be rendered (dumped)
 */

require_once 'lib/loadsave.php';
require_once 'PHPUnit.php';

class DumpHtml extends PHPUnit_TestCase {
    // constructor of the test suite
    function DumpHtml($name) {
       $this->PHPUnit_TestCase($name);
    }

    function testRateIt() {
        global $request;
        $request->setArg('directory','.dumphtml');
        $request->setArg('pages','RateIt');
        DumpHtmlToDir($request);
    }

    function testDumpHtml() {
        global $request;
        $request->setArg('directory','.dumphtml');
        DumpHtmlToDir($request);
    }
}


?>
