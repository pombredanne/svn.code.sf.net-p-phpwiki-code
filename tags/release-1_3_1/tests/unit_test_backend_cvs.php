<?php

/**
 * Unit tests the 'lib/WikiDB/backend/cvs.php' file and with it
 * the class WikiDB_backend_cvs
 *
 * Author: Gerrit Riessen, gerrit.riessen@open-source-consultants.de
 */

// need to set this to be something sensible ....
ini_set('include_path', '/www/development/phpwiki' );

function rcs_id()
{
}

if ( $USER == "root" ) {
  // root user can't check in to a CVS repository
  print( "can not be run as root\n" );
  exit(1);
}

// set to false if something went wrong
$REMOVE_DEBUG = true;

require_once( 'lib/WikiDB/backend/cvs.php' );

$db_params                           = array();
/**
 * These are the parameters required by the backend. 
 */
$db_params[CVS_PAGE_SOURCE]          = "/www/development/phpwiki/pgsrc";
$db_params[CVS_CHECK_FOR_REPOSITORY] = true;
// the following three are removed if the test succeeds.
$db_params[CVS_DOC_DIR]              = "/tmp/wiki_docs";
$db_params[CVS_REPOSITORY]           = "/tmp/wiki_repository";
$db_params[CVS_DEBUG_FILE]           = "/tmp/php_cvs.log";

//
// Check the creation of a new CVS repository and the importing of
// the default pages.
//
$cvsdb = new WikiDB_backend_cvs( $db_params );
// check that all files contained in page source where checked in.
$allPageNames = array();
$d = opendir( $db_params[CVS_PAGE_SOURCE] );
while ( $entry = readdir( $d ) ) {
    exec( "grep 'Checking in $entry' " . $db_params[CVS_DEBUG_FILE],
          $cmdOutput, $cmdRetval );
    
    if ( !is_dir( $db_params[CVS_PAGE_SOURCE] . "/" . $entry )) {
        $allPageNames[] = $entry;
        
        if ( $cmdRetval ) {
            print "*** Error: [$entry] was not checked in -- view " 
                . $db_params[CVS_DEBUG_FILE] . " for details\n";
            $REMOVE_DEBUG = false;
        }
    }
}
closedir( $d );

//
// Check that the meta data files were created
//
function get_pagedata( $item, $key, $cvsdb ) 
{
    global $REMOVE_DEBUG;
    $pageHash = $cvsdb->get_pagedata( $item );
    if ( $pageHash[CMD_VERSION] != "2" ) {
        print "*** Error: [$item] version wrong (". $pageHash[CMD_VERSION]
            .")\n";
        $REMOVE_DEBUG = false;
    }
}
array_walk( $allPageNames, 'get_pagedata', $cvsdb );

//
// clean up after ourselves
//
if ( $REMOVE_DEBUG ) {
    exec( "rm -fr " . $db_params[CVS_DOC_DIR], $cmdout, $retval );
    exec( "rm -fr " . $db_params[CVS_REPOSITORY], $cmdout, $retval );
    exec( "rm -f " . $db_params[CVS_DEBUG_FILE], $cmdout, $retval );
} else {
    print "It appears something went wrong, nothing being removed\n";
}

?>