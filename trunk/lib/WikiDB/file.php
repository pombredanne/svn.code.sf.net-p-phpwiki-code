<?php

rcs_id( '$Id: file.php,v 1.3 2003-01-04 03:29:02 wainstead Exp $' );

require_once( 'lib/WikiDB.php' );
require_once( 'lib/WikiDB/backend/file.php' );

/**
 * Wrapper class for the file backend.
 *
 * Authors: Gerrit Riessen, gerrit.riessen@open-source-consultants.de
 *          Jochen Kalmbach <Jochen@Kalmbachnet.de>
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


// $Log: not supported by cvs2svn $
//
// revision 1.2
// Added credits, php emacs stuff, log tag for CVS.
//
// revision 1.1
// date: 2003/01/04 03:21:00;  author: wainstead;  state: Exp;
// New flat file database for the 1.3 branch thanks to Jochen Kalmbach.


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   

?>
