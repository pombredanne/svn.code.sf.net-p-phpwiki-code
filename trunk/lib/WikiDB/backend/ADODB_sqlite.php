<?php // -*-php-*-
rcs_id('$Id: ADODB_sqlite.php,v 1.1 2004-07-05 12:56:48 rurban Exp $');

require_once('lib/WikiDB/backend/ADODB.php');

/**
 * WikiDB layer for ADODB-sqlite, called by lib/WikiDB/ADODB.php.
 * Just to create a not existing database.
 * 
 * @author: Reini Urban
 */
class WikiDB_backend_ADODB_sqlite
extends WikiDB_backend_ADODB
{
    /**
     * Constructor.
     */
    function WikiDB_backend_ADODB_sqlite($dbparams) {
        $parsed = parseDSN($dbparams['dsn']);
        if (! file_exists($parsed['database'])) {
            // creating the empty database
            $db = $parsed['database'];
            $schema = FindFile("schemas/sqlite.sql");
            `sqlite $db < $schema`;
        }
        $this->WikiDB_backend_ADODB($dbparams);
    }
};

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
