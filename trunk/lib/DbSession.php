<?php rcs_id('$Id: DbSession.php,v 1.32 2005-02-11 14:41:57 rurban Exp $');

/**
 * Store sessions data in Pear DB / ADODB / dba / ....
 *
 * History
 *
 * Originally by Stanislav Shramko <stanis@movingmail.com>
 * Minor rewrite by Reini Urban <rurban@x-ray.at> for Phpwiki.
 * Quasi-major rewrite/decruft/fix by Jeff Dairiki <dairiki@dairiki.org>.
 * ADODB and dba classes by Reini Urban.
 *
 * Warning: Enable USE_SAFE_DBSESSION if you get INSERT duplicate id warnings.
 */
class DbSession
{
    var $_backend;
    /**
     * Constructor
     *
     * @param mixed $dbh
     * Pear DB handle, or WikiDB object (from which the Pear DB handle will
     * be extracted.
     *
     * @param string $table
     * Name of SQL table containing session data.
     */
    function DbSession(&$dbh, $table = 'session') {
        // Coerce WikiDB to PearDB or ADODB.
        // Todo: adodb/dba handlers
        $db_type = $dbh->getParam('dbtype');
        if (isa($dbh, 'WikiDB')) {
            $backend = &$dbh->_backend;
            $db_type = substr(get_class($dbh),7);
            $class = "DbSession_".$db_type;
            
            // < 4.1.2 crash on dba sessions at session_write_close(). 
            // (Tested with 4.1.1 and 4.1.2)
            // Didn't try postgres sessions.
            if (!check_php_version(4,1,2) and $db_type == 'dba')
                return false;

            @include_once("lib/DbSession/".$db_type.".php");
            if (class_exists($class)) {
                $this->_backend = new $class($backend->_dbh, $table);
                return $this->_backend;
            }
        }
        //Fixme: E_USER_WARNING ignored!
        trigger_error(sprintf(_("Your WikiDB DB backend '%s' cannot be used for DbSession.")." ".
                              _("Set USE_DB_SESSION to false."),
                             $db_type), E_USER_WARNING);
        return false;
    }
    
    function currentSessions() {
        return $this->_backend->currentSessions();
    }
    function query($sql) {
        return $this->_backend->query($sql);
    }
    function quote($string) { return $string; }
}

// $Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>