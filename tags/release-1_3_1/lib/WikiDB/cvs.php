<?php

rcs_id( '$Id: cvs.php,v 1.1 2001-09-28 14:28:21 riessen Exp $' );

require_once( 'lib/WikiDB.php' );
require_once( 'lib/WikiDB/backend/cvs.php' );

/**
 * Wrapper class for the cvs backend.
 *
 * Author: Gerrit Riessen, gerrit.riessen@open-source-consultants.de
 */
class WikiDB_cvs 
extends WikiDB
{
    function WikiDB_cvs( $dbparams ) {
	$backend = new WikiDB_backend_cvs( $dbparams );
    }
}
?>