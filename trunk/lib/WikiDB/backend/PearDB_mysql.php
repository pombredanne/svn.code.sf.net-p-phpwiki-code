<?php // -*-php-*-
rcs_id('$Id: PearDB_mysql.php,v 1.7 2004-07-08 15:35:17 rurban Exp $');

require_once('lib/WikiDB/backend/PearDB.php');

class WikiDB_backend_PearDB_mysql
extends WikiDB_backend_PearDB
{
    /**
     * Constructor.
     */
    function WikiDB_backend_PearDB_mysql($dbparams) {
        $this->WikiDB_backend_PearDB($dbparams);
        //$this->_serverinfo = $this->_dbh->ServerInfo();
        $row = $this->_dbh->GetOne("SELECT version()");
        if (!DB::isError($row) and !empty($row)) {
            $arr = explode('.',$row);
            $this->_serverinfo['version'] = (string)(($arr[0] * 100) + $arr[1]) . "." . (integer)$arr[2];
            if ($this->_serverinfo['version'] < 323.0) {
                // Older MySQL's don't have CASE WHEN ... END
                $this->_expressions['maxmajor'] = "MAX(IF(minor_edit=0,version,0))";
                $this->_expressions['maxminor'] = "MAX(IF(minor_edit<>0,version,0))";
            }
        }
    }
    
    /**
     * Kill timed out processes. ( so far only called on about every 50-th save. )
     */
    function _timeout() {
    	if (empty($this->_dbparams['timeout'])) return;
	$result = mysql_query("SHOW processlist");
	while ($row = mysql_fetch_array($result)) { 
	    if ($row["db"] == $this->_dbh->dsn['database']
	        and $row["User"] == $this->_dbh->dsn['username']
	        and $row["Time"] > $this->_dbparams['timeout']
	        and $row["Command"] == "Sleep") {
	            $process_id = $row["Id"]; 
	            mysql_query("KILL $process_id");
	    }
	}
    }
   
    /**
     * Pack tables.
     */
    function optimize() {
        $dbh = &$this->_dbh;
	$this->_timeout();
        foreach ($this->_table_names as $table) {
            $dbh->query("OPTIMIZE TABLE $table");
        }
        return 1;
    }

    /**
     * Lock tables.
     */
    function _lock_tables($write_lock = true) {
        $lock_type = $write_lock ? "WRITE" : "READ";
        foreach ($this->_table_names as $table) {
            $tables[] = "$table $lock_type";
        }
        $this->_dbh->query("LOCK TABLES " . join(",", $tables));
    }

    /**
     * Release all locks.
     */
    function _unlock_tables() {
        $this->_dbh->query("UNLOCK TABLES");
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
