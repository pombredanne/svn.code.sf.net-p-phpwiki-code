<?php

rcs_id( '$Id: file.php,v 1.1 2003-01-04 03:21:00 wainstead Exp $' );

require_once( 'lib/WikiDB.php' );
require_once( 'lib/WikiDB/backend/file.php' );

/**
 * Wrapper class for the file backend.
 *
 * Author: Gerrit Riessen, gerrit.riessen@open-source-consultants.de
 */
class WikiDB_file extends WikiDB
{  
    /**
     * Constructor requires the DB parameters. 
     */
    function WikiDB_file( $dbparams ) 
    {
        $backend = new WikiDB_backend_file( $dbparams );
        $this->WikiDB($backend, $dbparams);
    }
}
?>
