<?php // -*-php-*-
// SQLite PearDB backend by Matthew Palmer
// The SQLite DB will gain popularity with the current MySQL vs PHP license drama.
rcs_id('$Id: PearDB_sqlite.php,v 1.1 2004-03-17 14:40:37 rurban Exp $');

require_once('lib/WikiDB/backend/PearDB.php');

class WikiDB_backend_sqlite
extends WikiDB_backend_PearDB
{
    /**
     * Pack tables.
     */
    function optimize() {
    // NOP
    }

    /**
     * Lock tables.
     */
    function _lock_tables($write_lock = true) {
    // NOP - SQLite does all locking automatically
    }

    /**
     * Release all locks.
     */
    function _unlock_tables() {
    // NOP
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