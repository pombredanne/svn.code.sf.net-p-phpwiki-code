<?php
/**
 * Check if all pages can be rendered (dumped)
 */

require_once 'lib/loadsave.php';
require_once 'PHPUnit.php';

class DumpHtml extends phpwiki_TestCase {

    function _dumpPage($pagename) {
        global $request, $cur_dir;

        $request->setArg('directory','.dumphtml');
        $request->setArg('pages',$pagename);
        unlink($cur_dir."/.dumphtml/$pagename.html");
        DumpHtmlToDir($request);
        $this->assertTrue(file_exists($cur_dir."/.dumphtml/$pagename.html")); 
    }

    /* at first dump some problematic pages */
    function test01RateIt() {
        $this->_dumpPage('RateIt');
    }
    function test02OrphanedPages() {
        $this->_dumpPage('OrphanedPages');
    }
    function test03OldTextFormattingRules() {
        $this->_dumpPage('OldTextFormattingRules');
    }

    /* finally all. esp. with start_debug=1 this needs some time... */
    function test99DumpHtml() {
        global $request, $cur_dir;

        $request->setArg('directory','.dumphtml');
        purge_dir($cur_dir."/.dumphtml");
        purge_dir($cur_dir."/.dumphtml/images");
        $request->setArg('pages','');
        DumpHtmlToDir($request);
        $this->assertTrue(file_exists($cur_dir."/.dumphtml/HomePage.html")); 
    }

}


?>
