<?php
ini_set('session.save_handler','user'); 

//----------------------------------------------------+
// This class is based on PEAR (http://pear.php.net) -+
//----------------------------------------------------+

// {{{ includes

//include 'DB.php';
include_once 'pear/DB.php';
require_once('lib/WikiDB/backend/PearDB.php');

// }}} includes

// {{{ classes

/**
 * Store sessions data in Pear DB.
 * Minor rewrite by Reini Urban <rurban@x-ray.at> for Phpwiki.
 *
 * @package Session
 * @class   DB_Session
 * @author  Stanislav Shramko <stanis@movingmail.com>
 * @access  public
 * @version $Revision: 1.1 $
 *
 */

class DB_Session 
extends WikiDB_backend_PearDB
{

    /**
     * Constructor.
     *
     * @param  string $table a name of the table
     * @access public
     */
    function DB_Session($table = 'session_table') {
	global $db_session_dbh, $db_session_dsn, $db_session_persistent, $session_table;
	// $this->PEAR();
	$session_table = $table;
	if (!$this->_dbh) { // Fixme. This should not happen.
	    $db_session_dbh = DB::connect($GLOBALS['DBParams']['dsn'], 
					  array('persistent' => true, 'debug' => 2));
	    if (DB::isError($db_session_dbh)) {
		die("DB_Session allocated the following problem: " . 
		    $db_session_dbh->getMessage());
	    }
	}
	else {
	    $db_session_dbh =& $this->_dbh;
	}
	// We always use persistent connections for now.
	$db_session_persistent = true; //$dbh->get('persistent');
	session_set_save_handler ("_db_session_open", 
				  "_db_session_close", "_db_session_read", 
				  "_db_session_write", "_db_session_destroy", 
				  "_db_session_gc");
	session_start();
    }

    /**
     * Destructor.
     *
     * Used to remove a persistent connection if it's made.
     *
     * @access private
     */
    function _DB_Session() {
	global $db_session_persistent, $db_session_dbh;
	if ($db_session_persistent == true && isset($db_session_dbh) &&
	    get_class($db_session_dbh) == 'db') {
	    $db_session_dbh->disconnect();
	}
    }
}

// }}} classes

// {{{ functions

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

function _db_session_open ($save_path, $session_name) {
    return(true);
}

/**
 * Closes a session.
 *
 * This function is called just after 
 * <i>_db_session_write</i> call.
 *
 * @return boolean true just a variable to notify PHP that everything 
 * is good.
 * @access private
 */
function _db_session_close() {
    return(true);
}

/*
 * Reads the session data from DB.
 *
 * @param  string $id an id of current session
 * @return string
 * @access private
 */
function _db_session_read ($id) {
    global $db_session_dbh, $db_session_dsn, $session_table, $db_session_persistent;

    if ($db_session_persistent != true) {
	$db_session_dbh = DB::connect($db_session_dsn);
    }
    if (!DB::isError($db_session_dbh)) {
	$res = $db_session_dbh->getOne("SELECT sess_data 
              FROM $session_table WHERE sess_id = " . $db_session_dbh->quote($id));
	if (DB::isError($res)) {
	    $res = "";
	}
    } else {
	$res = "";
    }
    if ($db_session_persistent != true) {
	$db_session_dbh->disconnect();
    }
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
function _db_session_write ($id, $sess_data) {
    global $db_session_dbh, $db_session_dsn, $session_table, $db_session_persistent;
    if ($db_session_persistent != true) {
	$db_session_dbh = DB::connect($db_session_dsn);
	// var_dump($db_session_dbh);
    }
    if (!DB::isError($db_session_dbh)) {
	$count = $db_session_dbh->getOne("SELECT COUNT(*) FROM $session_table
              WHERE sess_id = " . $db_session_dbh->quote($id));
	if ($count == 0) {
	    $sql = "INSERT INTO $session_table " . 
		"(sess_id, sess_data, sess_date) VALUES " .
		"(" . $db_session_dbh->quote($id) . ", " . 
		$db_session_dbh->quote($sess_data) . ", " .
		$db_session_dbh->quote(time()) . ")";
	    $res = $db_session_dbh->query($sql);
	    if (DB::isError($res)) {
		echo $res->getMessage();
	    }
	} else {
	    $sql = "UPDATE $session_table SET " .
		"sess_data = " . $db_session_dbh->quote($sess_data) . ", " .
		"sess_date = " . $db_session_dbh->quote(time()) . 
		" WHERE sess_id = " . $db_session_dbh->quote($id);
	    $res = $db_session_dbh->query($sql);
	}
	if (DB::isError($res)) {
	    $res = false;
	} 
    } else {
	echo $db_session_dbh->getMessage();
	$res = false;
    }
    if ($db_session_persistent != true) {
	$db_session_dbh->disconnect();
    }
    return $res;
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
function _db_session_destroy ($id) {
    global $db_session_dbh, $db_session_dsn, $session_table, $db_session_persistent;
    if ($db_session_persistent != true) {
	$db_session_dbh = DB::connect($db_session_dsn);
	var_dump($db_session_dbh);
    }
    if (!DB::isError($db_session_dbh)) {
	$db_session_dbh->query("DELETE FROM $session_table ".
			"WHERE sess_id = " . $db_session_dbh->quote($id));
    }
    if ($db_session_persistent != true) {
	$db_session_dbh->disconnect();
    }
    return true;     
}

/**
 * Cleans out all expired sessions.
 *
 * @param  int $maxlifetime session's time to live.
 * @return boolean true
 * @access private
 */
function _db_session_gc ($maxlifetime) {
    global $db_session_dbh, $db_session_dsn, $session_table, $db_session_persistent;
    if ($db_session_persistent != true) {
	$db_session_dbh = DB::connect($db_session_dsn);
    }
    $db_session_dbh->query("DELETE FROM $session_table WHERE sess_date < " . 
			   time() - $maxlifetime);
    //$db_session_dbh->query("FLUSH TABLES");
    if ($db_session_persistent != true) {
	$db_session_dbh->disconnect();
    }
    return true;
}

// }}} functions

?>
