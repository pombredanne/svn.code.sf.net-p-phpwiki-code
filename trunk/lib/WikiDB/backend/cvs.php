<?php
rcs_id('$Id: cvs.php,v 1.1 2001-09-28 14:28:22 riessen Exp $');
/**
 * Backend for handling CVS repository. 
 *
 * This code assumes that cvs and grep are in the path of the program
 * which uses this code.
 *
 * Author: Gerrit Riessen, gerrit.riessen@open-source-consultants.de
 */
require_once('lib/WikiDB/backend.php');

class WikiDB_backend_cvs
extends WikiDB_backend
{
    function get_pagedata($pagename) {
	
    }
    function update_pagedata($pagename, $newdata) {
    }
    function get_latest_version($pagename) {
    }
    function get_previous_version($pagename, $version) {
    }
    function get_versiondata($pagename, $version, $want_content = false) {
    }
    function delete_page($pagename) {
    }
    function delete_versiondata($pagename, $version) {
    }
    function set_versiondata($pagename, $version, $data) {
    }
    function update_versiondata($pagename, $version, $newdata) {
    }
    function set_links($pagename, $links) {
    }
    function get_links($pagename, $reversed) {
    }
    function get_all_revisions($pagename) {
    }
    function get_all_pages($include_defaulted) {
    }
    function text_search($search = '', $fullsearch = false) {
    }
    function most_popular($limit) {
    }
    function most_recent($params) {
    }
    function lock($write_lock = true) {
    }
    function unlock($force = false) {
    }
    function close () {
    }
    function sync() {
    }
    function optimize() {
    }
    function check() {
    }
    function rebuild() {
    }

    // 
    // The rest are all internal methods, not to be used 
    // directly.
    //
    /**
     * Return a list of currently existing Wiki pages.
     */
    function _GetAllWikiPageNames($dirName) {
	$namelist = array();
	$d = opendir( $dirName );
	$curr = 0;
	while ( $entry = readdir( $d ) ) {
	    $namelist[$curr++] = $entry;
	}

	// TODO: do we need to do something similar to a closedir ???
	return $namelist;
    }

    /**
     * Recursively create all directories.
     */
    function _mkdir( $path, $mode ) {
	$directoryName = dirname( $path );
	if ( $directoryName != "." && $directoryName != "/" 
	&& $directoryName != "\\"  && !is_dir( $direcoryName ) ) {
	    $rVal = _mkdir( $directoryName, $mode );
	}
	else {
	    return true;
	}
      
	return ($rVal && mkdir( $path, $mode ) );
    }

    /**
     * Recursively create all directories and then the file.
     */
    function _createFile( $path, $mode ) {
	_mkdir( dirname( $path ), $mode );
	touch( $path );
	chmod( $path, $mode );
    }
    
    /**
     * Debug function specifically for the CVS database functions.
     * Can be deactived by setting the WikiDB['debug_file'] to ""
     */
    function _cvsDebug( $msg )	{
	global $WikiDB;
	$filename = $WikiDB['debug_file'];
	if ( $filename == "" ) {
	    return;
	}
	
	if ( !file_exists( $filename ) ) {
	    _createFile( $filename, 0755 );
	}

	if ( $fdlock = @fopen( $filename, 'a' ) ) {
	    $locked = flock( $fdlock, 2 );
	    if ( !$locked ) {
		fclose( $fdlock );
		return;
	    }
	    
	    $fdappend = @fopen( $filename, 'a' );
	    fwrite( $fdappend, ($msg . "\n") );
	    fclose( $fdappend );
	    fclose( $fdlock );
	}
	else {
	    print( "unable to locate/open [$filename]\n" );
	}
    }
}
?>
