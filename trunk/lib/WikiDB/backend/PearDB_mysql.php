<?php // -*-php-*-
rcs_id('$Id: PearDB_mysql.php,v 1.12 2004-11-11 14:34:12 rurban Exp $');

require_once('lib/WikiDB/backend/PearDB.php');

// The slowest function overall is mysql_connect with [680ms]
// 2nd is db_mysql::simpleQuery with [257ms]
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
	        and $row["Command"] == "Sleep") 
            {
	            $process_id = $row["Id"]; 
	            mysql_query("KILL $process_id");
	    }
	}
    }

    /**
     * Create a new revision of a page.
     */
    function set_versiondata($pagename, $version, $data) {
        $dbh = &$this->_dbh;
        $version_tbl = $this->_table_names['version_tbl'];
        
        $minor_edit = (int) !empty($data['is_minor_edit']);
        unset($data['is_minor_edit']);
        
        $mtime = (int)$data['mtime'];
        unset($data['mtime']);
        assert(!empty($mtime));

        @$content = (string) $data['%content'];
        unset($data['%content']);
        unset($data['%pagedata']);
        
        $this->lock();
        $id = $this->_get_pageid($pagename, true);
        // requires PRIMARY KEY (id,version)!
        // VALUES supported since mysql-3.22.5
        $dbh->query(sprintf("REPLACE INTO $version_tbl"
                            . " (id,version,mtime,minor_edit,content,versiondata)"
                            . " VALUES(%d,%d,%d,%d,'%s','%s')",
                            $id, $version, $mtime, $minor_edit,
                            $dbh->escapeSimple($content),
                            $dbh->escapeSimple($this->_serialize($data))
                            ));
        // real binding (prepare,execute) only since mysqli + PHP5
        $this->_update_recent_table($id);
        $this->_update_nonempty_table($id);
        $this->unlock();
    }

    function _update_recent_table($pageid = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        extract($this->_expressions);

        $pageid = (int)$pageid;

        // optimized: mysql can do this with one REPLACE INTO.
        // supported in every (?) mysql version
        // requires PRIMARY KEY (id)!
        $dbh->query("REPLACE INTO $recent_tbl"
                    . " (id, latestversion, latestmajor, latestminor)"
                    . " SELECT id, $maxversion, $maxmajor, $maxminor"
                    . " FROM $version_tbl"
                    . ( $pageid ? " WHERE id=$pageid" : "")
                    . " GROUP BY id" );
    }

    function _update_nonempty_table($pageid = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pageid = (int)$pageid;

        // Optimized: mysql can do this with one REPLACE INTO.
        // supported in every (?) mysql version
        // requires PRIMARY KEY (id)
        $dbh->query("REPLACE INTO $nonempty_tbl (id)"
                    . " SELECT $recent_tbl.id"
                    . " FROM $recent_tbl, $version_tbl"
                    . " WHERE $recent_tbl.id=$version_tbl.id"
                    . "       AND version=latestversion"
                    . "  AND content<>''"
                    . ( $pageid ? " AND $recent_tbl.id=$pageid" : ""));
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

    function increaseHitCount($pagename) {
        $dbh = &$this->_dbh;
        // Hits is the only thing we can update in a fast manner.
        // Note that this will fail silently if the page does not
        // have a record in the page table.  Since it's just the
        // hit count, who cares?
        // LIMIT since 3.23
        $dbh->query(sprintf("UPDATE LOW_PRIORITY %s SET hits=hits+1 WHERE pagename='%s' LIMIT 1",
                            $this->_table_names['page_tbl'],
                            $dbh->escapeSimple($pagename)));
        return;
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
