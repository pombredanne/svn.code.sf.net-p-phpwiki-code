#! /usr/local/bin/php -Cq
<?php  
/* Copyright (C) 2004, Dan Frankowski <dfrankow@cs.umn.edu>
 * Copyright (C) 2004, Reini Urban <rurban@x-ray.at>
 *
 * This file is part of PhpWiki.
 * 
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * Unit tests for PhpWiki. 
 *
 * You must have PEAR's PHPUnit package <http://pear.php.net/package/PHPUnit>. 
 * These tests are unrelated to test/maketest.pl, which do not use PHPUnit.
 * These tests run from the command-line as well as from the browser.
 */

####################################################################
#
# Preamble needed to get the tests to run.
#
####################################################################


$cur_dir = getcwd();
# Add root dir to the path
if (substr(PHP_OS,0,3) == 'WIN')
    $cur_dir = str_replace("\\","/",$cur_dir);
$rootdir = $cur_dir . '/../../';
$ini_sep = substr(PHP_OS,0,3) == 'WIN' ? ';' : ':';
ini_set('include_path', ini_get('include_path') . $ini_sep . $rootdir);

# This quiets a warning in config.php
$HTTP_SERVER_VARS['REMOTE_ADDR'] = '127.0.0.1';
$HTTP_SERVER_VARS['HTTP_USER_AGENT'] = "PHPUnit";
define('GROUP_METHOD', 'NONE');
define('RATING_STORAGE','WIKIPAGE');
define('PHPWIKI_NOMAIN',true);

# Other needed files
require_once $rootdir.'index.php';
require_once $rootdir.'lib/main.php';

define('DEBUG', _DEBUG_TRACE);

function printSimpleTrace($bt) {
    //print_r($bt);
    echo "Traceback:\n";
    foreach ($bt as $i => $elem) {
        if (!array_key_exists('file', $elem)) {
            continue;
        }
        print "  " . $elem['file'] . ':' . $elem['line'] . "\n";
    }
}

# Show lots of detail when an assert() in the code fails
function assert_callback( $script, $line, $message ) {
   echo "assert failed: script ", $script," line ", $line," :";
   echo "$message";
   echo "Traceback:\n";
   printSimpleTrace(debug_backtrace());
   exit;
}
$foo = assert_options( ASSERT_CALLBACK, 'assert_callback');

#
# Get error reporting to call back, too
#
// set the error reporting level for this script
error_reporting(E_ALL);
/*
// This is too strict, fails on every notice and warning. 
// TODO: push an errorhandler with printSimpleTrace
function myErrorHandler($errno, $errstr, $errfile, $errline)
{
   echo "$errfile: $errline: error# $errno: $errstr\n";
   // Back trace
   echo "Traceback:\n";
   printSimpleTrace(debug_backtrace());
   exit;
}
// set to the user defined error handler
$old_error_handler = set_error_handler("myErrorHandler");
*/

# This is the test DB backend
$db_params                         = array();
$db_params['directory']            = $cur_dir . '/.testbox';
$db_params['dbtype']               = 'file';

if (ENABLE_USER_NEW) {
    class MockUser extends _WikiUser {
        function MockUser($name, $isSignedIn) {
            $this->_userid = $name;
            $this->_isSignedIn = $isSignedIn;
        }
        function isSignedIn() {
            return $this->_isSignedIn;
        }
    }
} else {
    class MockUser extends WikiUser {
        function MockUser($name, $isSignedIn) {
            $this->_userid = $name;
            $this->_isSignedIn = $isSignedIn;
        }
        function isSignedIn() {
            return $this->_isSignedIn;
        }
    }
}

class MockRequest extends WikiRequest {
    function MockRequest(&$dbparams) {
        $this->_dbi = WikiDB::open($dbparams);
        $this->_user = new MockUser("a_user", true);
        $this->_group = WikiGroup::getGroup();
        $this->_args = array('pagename' => 'HomePage', 'action' => 'browse');
        $this->Request();
    }
    function getGroup() {
    	if (is_object($this->_group))
            return $this->_group;
        else     
            return WikiGroup::getGroup();
    }
}

function purge_dir($dir) {
    static $finder;
    if (!isset($finder)) {
        $finder = new FileFinder;
    }
	$fileSet = new fileSet($dir);
	assert($dir);
    foreach ($fileSet->getFiles() as $f) {
    	unlink("$dir/$f");
    }
}

function purge_testbox() {
    global $db_params;	
    $dir = $db_params['directory'];
    assert($dir);
    foreach (array('latest_ver','links','page_data', 'ver_data') as $d) {
    	purge_dir("$dir/$d");
    }
}

global $ErrorManager;
$ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE|E_USER_WARNING|E_WARNING);
$request = new MockRequest($db_params);

/*
if (ENABLE_USER_NEW)
    $request->_user = WikiUser('AnonUser');
else {
    $request->_user = new WikiUser($request, 'AnonUser');
    $request->_prefs = $request->_user->getPreferences();
}
*/
include_once("themes/" . THEME . "/themeinfo.php");

####################################################################
#
# End of preamble, run the test suite ..
#
####################################################################

# Test files
require_once 'PHPUnit.php';
# lib/config.php might do a cwd()
require_once dirname(__FILE__).'/lib/InlineParserTest.php';
require_once dirname(__FILE__).'/lib/SetupWiki.php';
require_once dirname(__FILE__).'/lib/PageListTest.php';
require_once dirname(__FILE__).'/lib/DumpHtml.php';
require_once dirname(__FILE__).'/lib/plugin/ListPagesTest.php';
require_once dirname(__FILE__).'/lib/plugin/AllPagesTest.php';
require_once dirname(__FILE__).'/lib/plugin/AllUsersTest.php';
require_once dirname(__FILE__).'/lib/plugin/OrphanedPagesTest.php'; 

if (isset($HTTP_SERVER_VARS['REQUEST_METHOD']))
    echo "<pre>\n";
// purge the testbox
    
print "Run tests .. ";
print "Purge the testbox .. ";
purge_testbox();

$suite  = new PHPUnit_TestSuite("phpwiki");
$suite->addTest( new PHPUnit_TestSuite("InlineParserTest") );
$suite->addTest( new PHPUnit_TestSuite("HtmlParserTest") );
$suite->addTest( new PHPUnit_TestSuite("PageListTest") );
$suite->addTest( new PHPUnit_TestSuite("ListPagesTest") );
$suite->addTest( new PHPUnit_TestSuite("SetupWiki") );
$suite->addTest( new PHPUnit_TestSuite("DumpHtml") );
$suite->addTest( new PHPUnit_TestSuite("AllPagesTest") );
$suite->addTest( new PHPUnit_TestSuite("AllUsersTest") );
$suite->addTest( new PHPUnit_TestSuite("OrphanedPagesTest") );
$result = PHPUnit::run($suite); 

echo "ran " . $result->runCount() . " tests, " . $result->failureCount() . " failures.\n";

if ($result->failureCount() > 0) {
    echo "More detail:\n";
    echo $result->toString();
}

if (isset($HTTP_SERVER_VARS['REQUEST_METHOD']))
    echo "</pre>\n";

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
