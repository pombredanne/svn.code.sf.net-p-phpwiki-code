<?php  // #! /usr/local/bin/php -Cq
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
$rootdir = $cur_dir . '/../../';
$ini_sep = substr(PHP_OS,0,3) == 'WIN' ? ';' : ':';
ini_set('include_path', ini_get('include_path') . $ini_sep . $rootdir);

# This quiets a warning in config.php
$HTTP_SERVER_VARS['REMOTE_ADDR'] = '127.0.0.1';

# Other needed files
require_once $rootdir.'index.php';
require_once $rootdir.'lib/stdlib.php';

# Show lots of detail when an assert() in the code fails
function assert_callback( $script, $line, $message ) {
   echo "assert failed: script ", $script," line ", $line," :";
   echo "$message";
   echo "Traceback:";
   print_r(debug_backtrace());
   exit;
}
$foo = assert_options( ASSERT_CALLBACK, 'assert_callback');

# This is the test DB backend
#require_once( 'lib/WikiDB/backend/cvs.php' );
$db_params                         = array();
$db_params['directory']            = $cur_dir . '/.testbox';
$db_params['dbtype']               = 'file';

# Mock objects to allow tests to run
require_once($rootdir.'lib/Request.php');
require_once($rootdir.'lib/WikiDB.php');
if (ENABLE_USER_NEW)
    require_once($rootdir."lib/WikiUserNew.php");
else
    require_once($rootdir."lib/WikiUser.php"); 
require_once($rootdir."lib/WikiGroup.php");
require_once($rootdir."lib/PagePerm.php");

class MockRequest extends Request {
    function MockRequest(&$dbparams) {
    	global $Theme, $request;
        $this->_dbi = WikiDB::open(&$dbparams);
        $this->_args = array('pagename' => 'HomePage', 'action' => 'browse');
        $this->Request();
    }
    function setArg($arg, $value) {
        $this->_args[$arg] = $value;
    }
    function getArg($arg) {
        return $this->_args[$arg];
    }
    function getDbh() {
        return $this->_dbi;
    }
    function getUser () {
        if (isset($this->_user))
            return $this->_user;
        else
            return $GLOBALS['ForbiddenUser'];
    }
    function getPage ($pagename = false) {
        if (!isset($this->_dbi))
            $this->getDbh();
        if (!$pagename) 
            $pagename = $this->getArg('pagename');
        return $this->_dbi->getPage($pagename);
    }
    function getPrefs () {
        return $this->_prefs;
    }
    function getPref ($key) {
        if (isset($this->_prefs))
            return $this->_prefs->get($key);
    }
}

$request = new MockRequest($db_params);

if (ENABLE_USER_NEW)
    $request->_user = WikiUser('AnonUser');
else {
    $request->_user = new WikiUser($request, 'AnonUser');
    $request->_prefs = $request->_user->getPreferences();
}
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
require_once dirname(__FILE__).'/lib/PageListTest.php';
require_once dirname(__FILE__).'/lib/plugin/ListPagesTest.php';
require_once dirname(__FILE__).'/lib/plugin/AllPagesTest.php';
require_once dirname(__FILE__).'/lib/plugin/AllUsersTest.php';
require_once dirname(__FILE__).'/lib/plugin/OrphanedPagesTest.php'; 

if (isset($HTTP_SERVER_VARS['REQUEST_METHOD']))
    echo "<pre>\n";
print "Run tests ..\n";

$suite  = new PHPUnit_TestSuite("phpwiki");
$suite->addTest( new PHPUnit_TestSuite("InlineParserTest") );
$suite->addTest( new PHPUnit_TestSuite("HtmlParserTest") );
$suite->addTest( new PHPUnit_TestSuite("PageListTest") );
$suite->addTest( new PHPUnit_TestSuite("ListPagesTest") );
$suite->addTest( new PHPUnit_TestSuite("AllPagesTest") );
$suite->addTest( new PHPUnit_TestSuite("AllUsersTest") );
$suite->addTest( new PHPUnit_TestSuite("OrphanedPagesTest") );
$result = PHPUnit::run($suite); 

echo $result->toString();

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
