#!/usr/local/bin/php -Cq
<?php  
/* Copyright (C) 2004 Dan Frankowski <dfrankow@cs.umn.edu>
 * Copyright (C) 2004 Reini Urban <rurban@x-ray.at>
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
 * Use the argv (from cli) or tests (from browser) params to run only certain tests.
 */
/****************************************************************
   User definable options
*****************************************************************/
// common cfg options are taken from config/config.ini

define('GROUP_METHOD', 'NONE');
define('USE_DB_SESSION', false);
define('RATING_STORAGE', 'WIKIPAGE');

// available database backends to test:
$database_backends = array(
                           'file',
                           'dba',
                           'SQL',
                           'ADODB',
                           );
// "flatfile" testing occurs in "tests/unit/.testbox/"
// "dba" needs the DATABASE_DBA_HANDLER, also in the .textbox directory
$database_dba_handler = "db3";
// "SQL" and "ADODB" need delete permissions to the test db
//  You have to create that database beforehand with our schema
$database_dsn = "mysql://wikiuser:@localhost/phpwiki_test";
// For "cvs" see the seperate tests/unit_test_backend_cvs.php

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

define('PHPWIKI_NOMAIN',true);
define('DEBUG', 9); //_DEBUG_VERBOSE | _DEBUG_TRACE

# Other needed files
require_once $rootdir.'index.php';
require_once $rootdir.'lib/main.php';

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
// This is too strict, fails on every notice and warning. 
/*
function myErrorHandler$errno, $errstr, $errfile, $errline) {
   echo "$errfile: $errline: error# $errno: $errstr\n";
   echo "Traceback:\n";
   printSimpleTrace(debug_backtrace());
}
// The ErrorManager version
function _ErrorHandler_CB(&$error) {
   echo "Traceback:\n";
   printSimpleTrace(debug_backtrace());
   if ($error->isFatal()) {
        $error->errno = E_USER_WARNING;
        return true; // ignore error
   }
   return true;
}
// set to the user defined error handler
// $old_error_handler = set_error_handler("myErrorHandler");
// This is already done via _DEBUG_TRACE
//$ErrorManager->pushErrorHandler(new WikiFunctionCb('_ErrorHandler_CB'));
*/

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

//FIXME: ignore cached requests (if-modified-since) from cli
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
        else // FIXME: this is set to "/f:" somewhere.
            return WikiGroup::getGroup();
    }
}

function purge_dir($dir) {
    static $finder;
    if (!isset($finder)) {
        $finder = new FileFinder;
    }
    $fileSet = new fileSet($dir);
    assert(!empty($dir));
    foreach ($fileSet->getFiles() as $f) {
    	unlink("$dir/$f");
    }
}

function purge_testbox() {
    global $db_params;	
    if (isset($GLOBALS['request'])) {
        $dbi = $GLOBALS['request']->getDbh();
    }
    $dir = $db_params['directory'];
    switch ($db_params['dbtype']) {
    case 'file':
        assert(!empty($dir));
        foreach (array('latest_ver','links','page_data','ver_data') as $d) {
            purge_dir("$dir/$d");
        }
        break;
    case 'SQL':
    case 'ADODB':
        foreach ($dbi->_backend->_table_names as $table) {
            $dbi->genericSqlQuery("DELETE FROM $table");
        }
        break;
    case 'dba':
        purge_dir($dir);
        break;
    }
    if (isset($dbi)) {
        $dbi->_cache->close();
        $dbi->_backend->_latest_versions = array();
    }
}

global $ErrorManager;
$ErrorManager->setPostponedErrorMask(EM_FATAL_ERRORS|EM_WARNING_ERRORS|EM_NOTICE_ERRORS);

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

if (isset($HTTP_SERVER_VARS['REQUEST_METHOD']))
    echo "<pre>\n";
// purge the testbox
    
// save and restore all args for each test.
class phpwiki_TestCase extends PHPUnit_TestCase {
    function setUp() { 
        global $request;
        $this->_savedargs = $request->_args;
        $request->_args = array();
    }
    function tearDown() {
        global $request;
        $request->_args = $this->_savedargs;
        if (DEBUG & _DEBUG_TRACE) {
            echo "-- MEMORY USAGE: ";
            if (isWindows()) { // requires a newer cygwin
	        echo `cat /proc/meminfo | grep Mem:|perl -ane"print \$F[2];"`,"\n";
            } elseif (function_exists('memory_get_usage')) {
                echo memory_get_usage(),"\n";
            } elseif (function_exists('getrusage')) {
                $u = getrusage();
                echo $u['ru_maxrss'],"\n";
            } elseif (!isWindows()) { // only on unix, not on cygwin:
	        $pid = getmypid();
	        echo `ps -eo%mem,rss,pid | grep $pid`,"\n";
            }
            flush();
        }
    }
}

// use argv (from cli) or tests (from browser) params to run only certain tests
$alltests = array('InlineParserTest','HtmlParserTest','PageListTest','ListPagesTest',
                  'SetupWiki','DumpHtml','AllPagesTest','AllUsersTest','OrphanedPagesTest');
if (isset($HTTP_SERVER_VARS['REQUEST_METHOD']) and !empty($HTTP_GET_VARS['tests']))
    $argv = explode(',',$HTTP_GET_VARS['tests']);
if (!empty($argv) and preg_match("/test\.php$/", $argv[0]))
    array_shift($argv);
if (!empty($argv)) {
    $runtests = array();
    foreach ($argv as $test) {
        if (in_array($test,$alltests))
            $runtests[] = $test;
    }
    $alltests = $runtests;
    print_r($runtests);
    flush();
}

# Test all db backends.
foreach ($database_backends as $dbtype) {

    $suite  = new PHPUnit_TestSuite("phpwiki");

    $db_params                         = array();
    $db_params['directory']            = $cur_dir . '/.testbox';
    $db_params['dsn']                  = $database_dsn;
    $db_params['dba_handler']          = $database_dba_handler;
    $db_params['dbtype']               = $dbtype;

    echo "Testing DB Backend \"$dbtype\" ...\n";
    $request = new MockRequest($db_params);

    foreach ($alltests as $test) {
        if (file_exists(dirname(__FILE__).'/lib/'.$test.'.php'))
            require_once dirname(__FILE__).'/lib/'.$test.'.php';
        else    
            require_once dirname(__FILE__).'/lib/plugin/'.$test.'.php';
        $suite->addTest( new PHPUnit_TestSuite($test) );
    }

    $result = PHPUnit::run($suite); 
    echo "ran " . $result->runCount() . " tests, " . $result->failureCount() . " failures.\n";
    flush();

    if ($result->failureCount() > 0) {
        echo "More detail:\n";
        echo $result->toString();
    }
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