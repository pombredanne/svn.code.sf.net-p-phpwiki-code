<?php rcs_id('$Id: DbSession.php,v 1.3 2003-03-04 05:33:00 dairiki Exp $');

/**
 * Store sessions data in Pear DB.
 *
 * History
 *
 * Originally by Stanislav Shramko <stanis@movingmail.com>
 * Minor rewrite by Reini Urban <rurban@x-ray.at> for Phpwiki.
 * Quasi-major rewrite/decruft/fix by Jeff Dairiki <dairiki@dairiki.org>.
 */
class DB_Session
{
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

        // Coerce WikiDB to Pear DB.
        if (isa($dbh, 'WikiDB')) {
            $backend = &$dbh->_backend;
            if (!isa($backend, 'WikiDB_backend_PearDB')) {
                trigger_error('Your WikiDB does not seem to be using a Pear DB backend',
                              E_USER_ERROR);
                return;
            }
            $dbh = &$backend->_dbh;
        }
    
        $this->_dbh = &$dbh;
        $this->_table = $table;

        ini_set('session.save_handler','user');

        session_set_save_handler(array(&$this, 'do_open'),
                                 array(&$this, 'do_close'),
                                 array(&$this, 'do_read'),
                                 array(&$this, 'do_write'),
                                 array(&$this, 'do_destroy'),
                                 array(&$this, 'do_gc'));
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
    function do_open ($save_path, $session_name) {
        //$this->log("_do_open($save_path, $session_name)");
        return true;
    }

    /**
     * Closes a session.
     *
     * This function is called just after <i>do_write</i> call.
     *
     * @return boolean true just a variable to notify PHP that everything 
     * is good.
     * @access private
     */
    function do_close() {
        //$this->log("_do_close()");
        return true;
    }

    /**
     * Reads the session data from DB.
     *
     * @param  string $id an id of current session
     * @return string
     * @access private
     */
    function do_read ($id) {
        //$this->log("_do_read($id)");
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
    function do_write ($id, $sess_data) {
        
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
    function do_destroy ($id) {
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
    function do_gc ($maxlifetime) {
        $dbh = &$this->_connect();
        $table = $this->_table;
        $threshold = time() - $maxlifetime;

        $dbh->query("DELETE FROM $table WHERE sess_date < $threshold");

        $this->_disconnect();
        return true;
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
