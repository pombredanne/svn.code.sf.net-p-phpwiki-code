<?php rcs_id('$Id: DbSession.php,v 1.8 2004-04-02 15:06:55 rurban Exp $');

/**
 * Store sessions data in Pear DB / ADODB ....
 *
 * History
 *
 * Originally by Stanislav Shramko <stanis@movingmail.com>
 * Minor rewrite by Reini Urban <rurban@x-ray.at> for Phpwiki.
 * Quasi-major rewrite/decruft/fix by Jeff Dairiki <dairiki@dairiki.org>.
 */
class DB_Session
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
    function DB_Session(&$dbh, $table = 'session') {
        // Coerce WikiDB to PearDB or ADODB.
        // Todo: adodb/dba handlers
        $db_type = $GLOBALS['DBParams']['dbtype'];
        if (isa($dbh, 'WikiDB')) {
            $backend = &$dbh->_backend;
            $db_type = substr(get_class($dbh),7);
            $class = "DB_Session_".$db_type;
            if (class_exists($class)) {
                $this->_backend = new $class(&$backend->_dbh, $table);
                return $this->_backend;
            }
        }
        return false;
        
        //Fixme: E_USER_WARNING ignored!
        trigger_error(sprintf(
_("Your WikiDB DB backend '%s' cannot be used for DB_Session. Set USE_DB_SESSION to false."),
                             $db_type), E_USER_WARNING);
    }
    
    function currentSessions() {
        return $this->_backend->currentSessions();
    }
    function query($sql) {
        return $this->_backend->query($sql);
    }
    function quote($string) {
        return $this->_backend->quote($string);
    }

}

class DB_Session_SQL
extends DB_Session
{
    var $_backend_type = "SQL";

    function DB_Session_SQL ($dbh, $table) {

        $this->_dbh = &$dbh;
        $this->_table = $table;

        ini_set('session.save_handler','user');
        session_module_name('user'); // new style
        session_set_save_handler(array(&$this, 'open'),
                                 array(&$this, 'close'),
                                 array(&$this, 'read'),
                                 array(&$this, 'write'),
                                 array(&$this, 'destroy'),
                                 array(&$this, 'gc'));
        return $this;
    }

    function _connect() {
        $dbh = &$this->_dbh;
        $this->_connected = (bool)$dbh->connection;
        if (!$this->_connected) {
            $res = $dbh->connect($dbh->dsn);
            if (DB::isError($res)) {
                error_log("PhpWiki::DB_Session::_connect: " . $res->getMessage());
            }
        }
        return $dbh;
    }
    
    function query($sql) {
        return $this->_dbh->query($sql);
    }

    function quote($string) {
        return $this->_dbh->quote($string);
    }

    function _disconnect() {
        if (!$this->_connected)
            $this->_dbh->disconnect();
    }

    /**
     * Opens a session.
     *
     * Actually this function is a fake for session_set_save_handle.
     * @param  string $save_path a path to stored files
     * @param  string $session_name a name of the concrete file
     * @return boolean true just a variable to notify PHP that everything 
     * is good.
     * @access private
     */
    function open ($save_path, $session_name) {
        //$this->log("_open($save_path, $session_name)");
        return true;
    }

    /**
     * Closes a session.
     *
     * This function is called just after <i>write</i> call.
     *
     * @return boolean true just a variable to notify PHP that everything 
     * is good.
     * @access private
     */
    function close() {
        //$this->log("_close()");
        return true;
    }

    /**
     * Reads the session data from DB.
     *
     * @param  string $id an id of current session
     * @return string
     * @access private
     */
    function read ($id) {
        //$this->log("_read($id)");
        $dbh = &$this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);
    
        $res = $dbh->getOne("SELECT sess_data FROM $table WHERE sess_id=$qid");

        $this->_disconnect();
        if (DB::isError($res) || empty($res))
            return '';
        if (preg_match('|^[a-zA-Z0-9/+=]+$|', $res))
            $res = base64_decode($res);
        return $res;
    }
  
    /**
     * Saves the session data into DB.
     *
     * Just  a  comment:       The  "write"  handler  is  not 
     * executed until after the output stream is closed. Thus,
     * output from debugging statements in the "write" handler
     * will  never be seen in the browser. If debugging output
     * is  necessary, it is suggested that the debug output be
     * written to a file instead.
     *
     * @param  string $id
     * @param  string $sess_data
     * @return boolean true if data saved successfully  and false
     * otherwise.
     * @access private
     */
    function write ($id, $sess_data) {
        
        $dbh = &$this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);
        $time = time();

        // postgres can't handle binary data in a TEXT field.
        if (isa($dbh, 'DB_pgsql'))
            $sess_data = base64_encode($sess_data);
        $qdata = $dbh->quote($sess_data);
        
        $res = $dbh->query("UPDATE $table"
                           . " SET sess_data=$qdata, sess_date=$time"
                           . " WHERE sess_id=$qid");

        if ($dbh->affectedRows() == 0)
            $res = $dbh->query("INSERT INTO $table"
                               . " (sess_id, sess_data, sess_date)"
                               . " VALUES ($qid, $qdata, $time)");

        $this->_disconnect();
        return ! DB::isError($res);
    }

    /**
     * Destroys a session.
     *
     * Removes a session from the table.
     *
     * @param  string $id
     * @return boolean true 
     * @access private
     */
    function destroy ($id) {
        $dbh = &$this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);

        $dbh->query("DELETE FROM $table WHERE sess_id=$qid");

        $this->_disconnect();
        return true;     
    }

    /**
     * Cleans out all expired sessions.
     *
     * @param  int $maxlifetime session's time to live.
     * @return boolean true
     * @access private
     */
    function gc ($maxlifetime) {
        $dbh = &$this->_connect();
        $table = $this->_table;
        $threshold = time() - $maxlifetime;

        $dbh->query("DELETE FROM $table WHERE sess_date < $threshold");

        $this->_disconnect();
        return true;
    }

    // WhoIsOnline support
    function currentSessions() {
        $sessions = array();
        $dbh = &$this->_connect();
        $table = $this->_table;
        $res = $this->query("SELECT sess_data,sess_date FROM $table ORDER BY sess_date DESC");
        if (DB::isError($res) || empty($res))
            return $sessions;
        while ($row = $res->fetchRow()) {
            $data = $row['sess_data'];
            $date = $row['sess_date'];
            if (preg_match('|^[a-zA-Z0-9/+=]+$|', $data))
                $data = base64_decode($data);
            // session_data contains the <variable name> + "|" + <packed string>
            // we need just the wiki_user object (might be array as well)
            $user = strstr($data,"wiki_user|");
            $sessions[] = array('wiki_user' => substr($user,10), // from "O:" onwards
                                'date' => $date);
        }
        $this->_disconnect();
        return $sessions;
    }
}


// self-written adodb-sessions
class DB_Session_ADODB
extends DB_Session
{
    var $_backend_type = "ADODB";

    function DB_Session_ADODB ($dbh, $table) {

        $this->_dbh = &$dbh;
        $this->_table = $table;

        ini_set('session.save_handler','user');
        session_module_name('user'); // new style
        session_set_save_handler(array(&$this, 'open'),
                                 array(&$this, 'close'),
                                 array(&$this, 'read'),
                                 array(&$this, 'write'),
                                 array(&$this, 'destroy'),
                                 array(&$this, 'gc'));
        return $this;
    }

    function _connect() {
        global $DBParams;
        static $parsed = false;
        $dbh = &$this->_dbh;
        if (!$dbh) {
            if (!$parsed) $parsed = parseDSN($DBParams['dsn']);
            $this->_dbh = &ADONewConnection($parsed['phptype']); // Probably only MySql works just now
            $conn = $this->_dbh->Connect($parsed['hostspec'],$parsed['username'], 
                                         $parsed['password'], $parsed['database']);
            $dbh = &$this->_dbh;                             
        }
        return $dbh;
    }
    
    function query($sql) {
        return $this->_dbh->Execute($sql);
    }

    function quote($string) {
        return $this->_dbh->qstr($string);
    }

    function _disconnect() {
        if (!$this->_dbh)
            $this->_dbh->close();
    }

    /**
     * Opens a session.
     *
     * Actually this function is a fake for session_set_save_handle.
     * @param  string $save_path a path to stored files
     * @param  string $session_name a name of the concrete file
     * @return boolean true just a variable to notify PHP that everything 
     * is good.
     * @access private
     */
    function open ($save_path, $session_name) {
        //$this->log("_open($save_path, $session_name)");
        return true;
    }

    /**
     * Closes a session.
     *
     * This function is called just after <i>write</i> call.
     *
     * @return boolean true just a variable to notify PHP that everything 
     * is good.
     * @access private
     */
    function close() {
        //$this->log("_close()");
        return true;
    }

    /**
     * Reads the session data from DB.
     *
     * @param  string $id an id of current session
     * @return string
     * @access private
     */
    function read ($id) {
        //$this->log("_read($id)");
        $dbh = &$this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);
        $res = '';
        $rs = $dbh->Execute("SELECT sess_data FROM $table WHERE sess_id=$qid");
        if (!$rs->EOF) {
            $res = $rs->fields["sess_data"];
        }
        $this->_disconnect();
        if (!empty($res) and preg_match('|^[a-zA-Z0-9/+=]+$|', $res))
            $res = base64_decode($res);
        return $res;
    }
  
    /**
     * Saves the session data into DB.
     *
     * Just  a  comment:       The  "write"  handler  is  not 
     * executed until after the output stream is closed. Thus,
     * output from debugging statements in the "write" handler
     * will  never be seen in the browser. If debugging output
     * is  necessary, it is suggested that the debug output be
     * written to a file instead.
     *
     * @param  string $id
     * @param  string $sess_data
     * @return boolean true if data saved successfully  and false
     * otherwise.
     * @access private
     */
    function write ($id, $sess_data) {
        
        $dbh = &$this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);
        $time = time();

        // postgres can't handle binary data in a TEXT field.
        if (isa($dbh, 'DB_pgsql'))
            $sess_data = base64_encode($sess_data);
        $qdata = $dbh->quote($sess_data);
        $res = $dbh->query("UPDATE $table"
                           . " SET sess_data=$qdata, sess_date=$time"
                           . " WHERE sess_id=$qid");
        // Warning: This works only only adodb_mysql!
        // The parent class adodb needs ->AffectedRows()
        if (!$dbh->_AffectedRows()) 
            $res = $dbh->query("INSERT INTO $table"
                               . " (sess_id, sess_data, sess_date)"
                               . " VALUES ($qid, $qdata, $time)");
        $this->_disconnect();
        return ! $res->EOF;
    }

    /**
     * Destroys a session.
     *
     * Removes a session from the table.
     *
     * @param  string $id
     * @return boolean true 
     * @access private
     */
    function destroy ($id) {
        $dbh = &$this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);

        $dbh->query("DELETE FROM $table WHERE sess_id=$qid");

        $this->_disconnect();
        return true;     
    }

    /**
     * Cleans out all expired sessions.
     *
     * @param  int $maxlifetime session's time to live.
     * @return boolean true
     * @access private
     */
    function gc ($maxlifetime) {
        $dbh = &$this->_connect();
        $table = $this->_table;
        $threshold = time() - $maxlifetime;

        $dbh->query("DELETE FROM $table WHERE sess_date < $threshold");

        $this->_disconnect();
        return true;
    }

    // WhoIsOnline support
    function currentSessions() {
        $sessions = array();
        $dbh = &$this->_connect();
        $table = $this->_table;
        $rs = $this->query("SELECT sess_data,sess_date FROM $table ORDER BY sess_date DESC");
        if ($rs->EOF) {
            return $sessions;
        }
        while ($row = $rs->fetchRow()) {
            $data = $row['sess_data'];
            $date = $row['sess_date'];
            if (preg_match('|^[a-zA-Z0-9/+=]+$|', $data))
                $data = base64_decode($data);
            // session_data contains the <variable name> + "|" + <packed string>
            // we need just the wiki_user object (might be array as well)
            $user = strstr($data,"wiki_user|");
            $sessions[] = array('wiki_user' => substr($user,10), // from "O:" onwards
                                'date' => $date);
        }
        $this->_disconnect();
        return $sessions;
    }

}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
