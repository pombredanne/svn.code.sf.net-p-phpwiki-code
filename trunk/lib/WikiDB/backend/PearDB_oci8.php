<?php // -*-php-*-
rcs_id('$Id: PearDB_oci8.php,v 1.2 2004-07-08 15:35:17 rurban Exp $');

/**
 * Oracle extensions for the Pear DB backend.
 * @author: Philippe.Vanhaesendonck@topgame.be
 */

require_once('lib/ErrorManager.php');
require_once('lib/WikiDB/backend/PearDB.php');

class WikiDB_backend_PearDB_oci8
extends WikiDB_backend_PearDB
{
    /**
     * Constructor
     */
    function WikiDB_backend_PearDB_oci8($dbparams) {
        // Backend constructor
        $this->WikiDB_backend_PearDB($dbparams);
        
        // Empty strings are NULLS
        $this->_expressions['notempty'] = "IS NOT NULL";
        $this->_expressions['iscontent'] = "DECODE(DBMS_LOB.GETLENGTH(content), NULL, 0, 0, 0, 1)";

        // Set parameters:
        $dbh = &$this->_dbh;
        // - No persistent conections (I don't like them)
        $dbh->setOption('persistent', false);
        // - Set lowercase compatibility option
        // - Set numrows as well -- sure why this is needed, but some queries 
        //   are triggering DB_ERROR_NOT_CAPABLE
        $dbh->setOption('portability',
            DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_NULL_TO_EMPTY | DB_PORTABILITY_NUMROWS);
    }

            
    /**
     * Pack tables.
     */
    function optimize() {
        // Do nothing here -- Leave that for the DBA
        // Cost Based Optimizer tuning vary from version to version
        return 1;
    }

    /**
     * Lock all tables we might use.
     */
    function _lock_tables($write_lock = true) {
        $dbh = &$this->_dbh;
        
        // Not sure if we really need to lock tables here, the Oracle row
        // locking mechanism should be more than enough
        // For the time being, lets stay on the safe side and lock...
        if($write_lock) {
            // Next line is default behaviour, so just skip it
            // $dbh->query("SET TRANSACTION READ WRITE");
            foreach ($this->_table_names as $table) {
                $dbh->query("LOCK TABLE $table IN EXCLUSIVE MODE");
            }
        } else {
            // Just ensure read consistency
            $dbh->query("SET TRANSACTION READ ONLY");
        }
    }

    /**
     * Unlock all tables.
     */
    function _unlock_tables() {
        $dbh = &$this->_dbh;
        $dbh->query("COMMIT WORK");
    }

    
    // Search callabcks
    // Page name
    function _sql_match_clause($word) {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->quoteString($word);
        return "LOWER(pagename) LIKE '%$word%'";
    }

    // Fulltext -- case sensisitive :-\
    // If we want case insensitive search, one need to create a Context
    // Index on the CLOB. While it is very efficient, it requires the
    // Intermedia Text option, so let's stick to the 'simple' thing
    function _fullsearch_sql_match_clause($word) {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->quoteString($word);
        return "LOWER(pagename) LIKE '%$word%' " 
               . "OR DBMS_LOB.INSTR(content, '$word') > 0";
    }


    /**
     * Serialize data
     */
    function _serialize($data) {
        if (empty($data))
            return '';
        assert(is_array($data));
        return base64_encode(serialize($data));
    }

    /**
     * Unserialize data
     */
    function _unserialize($data) {
        if (empty($data))
            return array();
        // Base64 encoded data does not contain colons.
        //  (only alphanumerics and '+' and '/'.)
        if (substr($data,0,2) == 'a:')
            return unserialize($data);
        return unserialize(base64_decode($data));
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
