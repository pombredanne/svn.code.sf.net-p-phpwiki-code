<?php // -*-php-*-
rcs_id('$Id: PearDB.php,v 1.1 2001-09-18 19:16:23 dairiki Exp $');

require_once('DB.php');
require_once('lib/WikiDB/backend.php');

class WikiDB_backend_PearDB
extends WikiDB_backend
{
    function WikiDB_backend_PearDB ($dbparams) {
        // Open connection to database
        $dsn = $dbparams['dsn'];
        $this->_dbh = DB::connect($dsn, true); //FIXME: true -> persistent connection
        $dbh = &$this->_dbh;
        if (DB::isError($dbh)) {
            trigger_error("Can't connect to database: "
                          . $this->_pear_error_message($dbh),
                          E_USER_ERROR);
        }
        $this->_dbh = $dbh;
        $dbh->setErrorHandling(PEAR_ERROR_CALLBACK,
                               array($this, '_pear_error_callback'));
        $dbh->setFetchMode(DB_FETCHMODE_ASSOC);

        $prefix = isset($dbparams['prefix']) ? $dbparams['prefix'] : '';

        $this->_table_names
            = array('page_tbl'     => $prefix . 'page',
                    'version_tbl'  => $prefix . 'version',
                    'link_tbl'     => $prefix . 'link',
                    'recent_tbl'   => $prefix . 'recent',
                    'nonempty_tbl' => $prefix . 'nonempty');

        $this->_lock_count = 0;
    }
    
    /**
     * Close database connection.
     */
    function close () {
        if (!$this->_dbh)
            return;
        if ($this->_lock_count) {
            trigger_error("WARNING: database still locked"
                          . " (lock_count = $this->_lock_count)\n<br>",
                          E_USER_WARNING);
        }
        $this->_dbh->setErrorHandling(PEAR_ERROR_PRINT);	// prevent recursive loops.
        $this->unlock('force');

        $this->_dbh->disconnect();
        $this->_dbh = false;
    }


    /*
     * Test fast wikipage.
     */
    function is_wiki_page($pagename) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        return $dbh->getOne(sprintf("SELECT $page_tbl.id"
                                    . " FROM $nonempty_tbl INNER JOIN $page_tbl USING(id)"
                                    . " WHERE pagename='%s'",
                                    $dbh->quoteString($pagename)));
    }
        
    function get_all_pagenames() {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        return $dbh->getCol("SELECT pagename"
                            . " FROM $nonempty_tbl INNER JOIN $page_tbl USING(id)");
    }
            
    /**
     * Read page information from database.
     */
    function get_pagedata($pagename) {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];

        //trigger_error("GET_PAGEDATA $pagename", E_USER_NOTICE);

        $result = $dbh->getRow(sprintf("SELECT * FROM $page_tbl WHERE pagename='%s'",
                                       $dbh->quoteString($pagename)),
                               DB_FETCHMODE_ASSOC);
        if (!$result)
            return false;
        return $this->_extract_page_data($result);
    }

    function  _extract_page_data(&$query_result) {
        extract($query_result);
        $data = empty($pagedata) ? array() : unserialize($pagedata);
        $data['hits'] = $hits;
        return $data;
    }

    function update_pagedata($pagename, $newdata) {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];

        // Hits is the only thing we can update in a fast manner.
        if (count($newdata) == 1 && isset($newdata['hits'])) {
            // Note that this will fail silently if the page does not
            // have a record in the page table.  Since it's just the
            // hit count, who cares?
            $dbh->query(sprintf("UPDATE $page_tbl SET hits=%d WHERE pagename='%s'",
                                $newdata['hits'], $dbh->quoteString($pagename)));
            return;
        }

        $this->lock();
        $data = $this->get_pagedata($pagename);
        if (!$data) {
            $data = array();
            $this->_get_pageid($pagename, true); // Creates page record
        }
        
        @$hits = (int)$data['hits'];
        unset($data['hits']);

        foreach ($newdata as $key => $val) {
            if ($key == 'hits')
                $hits = (int)$val;
            else if (empty($val))
                unset($data[$key]);
            else
                $data[$key] = $val;
        }

        $dbh->query(sprintf("UPDATE $page_tbl"
                            . " SET hits=%d, pagedata='%s'"
                            . " WHERE pagename='%s'",
                            $hits,
                            $dbh->quoteString(serialize($data)),
                            $dbh->quoteString($pagename)));

        $this->unlock();
    }

    function _get_pageid($pagename, $create_if_missing = false) {
        
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        
        $query = sprintf("SELECT id FROM $page_tbl WHERE pagename='%s'",
                         $dbh->quoteString($pagename));

        if (!$create_if_missing)
            return $dbh->getOne($query);

        $this->lock();
        $id = $dbh->getOne($query);
        if (empty($id)) {
            $max_id = $dbh->getOne("SELECT MAX(id) FROM $page_tbl");
            $id = $max_id + 1;
            $dbh->query(sprintf("INSERT INTO $page_tbl"
                                . " (id,pagename,hits)"
                                . " VALUES (%d,'%s',0)",
                                $id, $dbh->quoteString($pagename)));
        }
        $this->unlock();
        return $id;
    }

    function get_latest_version($pagename) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        return
            (int)$dbh->getOne(sprintf("SELECT latestversion"
                                      . " FROM $page_tbl"
                                      . "  INNER JOIN $recent_tbl USING(id)"
                                      . " WHERE pagename='%s'",
                                      $dbh->quoteString($pagename)));
    }

    function get_previous_version($pagename, $version) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        
        return
            (int)$dbh->getOne(sprintf("SELECT version"
                                      . " FROM $version_tbl"
                                      . "  INNER JOIN $page_tbl USING(id)"
                                      . " WHERE pagename='%s'"
                                      . "  AND version < %d"
                                      . " ORDER BY version DESC"
                                      . " LIMIT 1",
                                      $dbh->quoteString($pagename),
                                      $version));
    }
    
    /**
     * Get version data.
     *
     * @param $version int Which version to get.
     *
     * @return hash The version data, or false if specified version does not
     *              exist.
     */
    function get_versiondata($pagename, $version, $want_content = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
                
        assert(!empty($pagename));
        assert($version > 0);
        
        //trigger_error("GET_REVISION $pagename $version $want_content", E_USER_NOTICE);
        // FIXME: optimization: sometimes don't get page data?

        if ($want_content) {
            $fields = "*";
        }
        else {
            $fields = ("$page_tbl.*,"
                       . "mtime,minor_edit,versiondata,"
                       . "content<>'' AS have_content");
        }

        $result = $dbh->getRow(sprintf("SELECT $fields"
                                       . " FROM $page_tbl"
                                       . "  INNER JOIN $version_tbl USING(id)"
                                       . " WHERE pagename='%s' AND version=%d",
                                       $dbh->quoteString($pagename), $version),
                               DB_FETCHMODE_ASSOC);

        return $this->_extract_version_data($result);
    }

    function _extract_version_data(&$query_result) {
        if (!$query_result)
            return false;

        extract($query_result);
        $data = empty($versiondata) ? array() : unserialize($versiondata);

        $data['mtime'] = $mtime;
        $data['is_minor_edit'] = !empty($minor_edit);
        
        if (isset($content))
            $data['%content'] = $content;
        elseif ($have_content)
            $data['%content'] = true;
        else
            $data['%content'] = '';

        // FIXME: this is ugly.
        if (isset($pagename)) {
            // Query also includes page data.
            // We might as well send that back too...
            $data['%pagedata'] = $this->_extract_page_data($query_result);
        }

        return $data;
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

        // FIXME: optimize: mysql can do this with one REPLACE INTO (I think).
        $dbh->query(sprintf("DELETE FROM $version_tbl"
                            . " WHERE id=%d AND version=%d",
                            $id, $version));

        $dbh->query(sprintf("INSERT INTO $version_tbl"
                            . " (id,version,mtime,minor_edit,content,versiondata)"
                            . " VALUES(%d,%d,%d,%d,'%s','%s')",
                            $id, $version, $mtime, $minor_edit,
                            $dbh->quoteString($content),
                            $dbh->quoteString(serialize($data))));

        $this->_update_recent_table($id);
        $this->_update_nonempty_table($id);
        
        $this->unlock();
    }
    
    /**
     * Delete an old revision of a page.
     */
    function delete_versiondata($pagename, $version) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        if ( ($id = $this->_get_pageid($pagename)) ) {
            $dbh->query("DELETE FROM $version_tbl"
                        . " WHERE id=$id AND version=$version");
            $this->_update_recent_table($id);
            // This shouldn't be needed (as long as the latestversion
            // never gets deleted.)  But, let's be safe.
            $this->_update_nonempty_table($id);
        }
        $this->unlock();
    }

    /**
     * Delete page from the database.
     */
    function delete_page($pagename) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        
        $this->lock();
        if ( ($id = $this->_get_pageid($pagename, 'id')) ) {
            $dbh->query("DELETE FROM $version_tbl  WHERE id=$id");
            $dbh->query("DELETE FROM $recent_tbl   WHERE id=$id");
            $dbh->query("DELETE FROM $nonempty_tbl WHERE id=$id");
            $dbh->query("DELETE FROM $link_tbl     WHERE linkfrom=$id");
            $nlinks = $dbh->getOne("SELECT COUNT(*) FROM $link_tbl WHERE linkto=$id");
            if ($nlinks) {
                // We're still in the link table (dangling link) so we can't delete this
                // altogether.
                $dbh->query("UPDATE $page_tbl SET hits=0, pagedata='' WHERE id=$id");
            }
            else {
                $dbh->query("DELETE FROM $page_tbl WHERE id=$id");
            }
            $this->_update_recent_table();
            $this->_update_nonempty_table();
        }
        $this->unlock();
    }
            

    // The only thing we might be interested in updating which we can
    // do fast in the flags (minor_edit).   I think the default
    // update_versiondata will work fine...
    //function update_versiondata($pagename, $version, $data) {
    //}

    function set_links($pagename, $links) {
        // Update link table.
        // FIXME: optimize: mysql can do this all in one big INSERT.

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        $pageid = $this->_get_pageid($pagename, true);

        $dbh->query("DELETE FROM $link_tbl WHERE linkfrom=$pageid");

        foreach($links as $link) {
            if (isset($linkseen[$link]))
                continue;
            $linkseen[$link] = true;
            $linkid = $this->_get_pageid($link, true);
            $dbh->query("INSERT INTO $link_tbl (linkfrom, linkto)"
                        . " VALUES ($pageid, $linkid)");
        }
        $this->unlock();
    }
    
    /**
     * Find pages which link to or are linked from a page.
     */
    function get_links($pagename, $reversed = true) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($reversed)
            list($have,$want) = array('linkee', 'linker');
        else
            list($have,$want) = array('linker', 'linkee');

        $qpagename = $dbh->quoteString($pagename);
        
        $result = $dbh->query("SELECT $want.*"
                              . " FROM $link_tbl"
                              . "  INNER JOIN $page_tbl AS linker ON linkfrom=linker.id"
                              . "  INNER JOIN $page_tbl AS linkee ON linkto=linkee.id"
                              . " WHERE $have.pagename='$qpagename'"
                              //. " GROUP BY $want.id"
                              . " ORDER BY $want.pagename",
                              DB_FETCHMODE_ASSOC);
        
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    function get_all_pages($include_deleted) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($include_deleted) {
            $result = $dbh->query("SELECT * FROM $page_tbl ORDER BY pagename");
        }
        else {
            $result = $dbh->query("SELECT $page_tbl.*"
                                  . " FROM $nonempty_tbl INNER JOIN $page_tbl USING(id)"
                                  . " ORDER BY pagename");
        }

        return new WikiDB_backend_PearDB_iter($this, $result);
    }
        
    /**
     * Title search.
     */
    function text_search($search = '', $fullsearch = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        
        $table = "$nonempty_tbl INNER JOIN $page_tbl USING(id)";
        $fields = "$page_tbl.*";
        $callback = '_sql_match_clause';
        
        if ($fullsearch) {
            $table .= (" INNER JOIN $recent_tbl ON $page_tbl.id=$recent_tbl.id"
                       . " INNER JOIN $version_tbl"
                       . "   ON $page_tbl.id=$version_tbl.id"
                       . "   AND latestversion=version" );
            $fields .= ",$version_tbl.*";
            $callback = '_fullsearch_sql_match_clause';
        }

        
        $search_clause = $search->makeSqlClause(array($this, $callback));
        
        $result = $dbh->query("SELECT $fields FROM $table"
                              . " WHERE $search_clause"
                              . " ORDER BY pagename");
        
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    function _sql_match_clause($word) {
        $word = $this->_dbh->quoteString($word);
        return "LOWER(pagename) LIKE '%$word%'";
    }

    function _fullsearch_sql_match_clause($word) {
        $word = $this->_dbh->quoteString($word);
        return "LOWER(pagename) LIKE '%$word%' OR content LIKE '%$word%'";
    }

    /**
     * Find highest hit counts.
     */
    function most_popular($limit) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $limitclause = $limit ? " LIMIT $limit" : '';
        $result = $dbh->query("SELECT $page_tbl.*"
                              . " FROM $nonempty_tbl INNER JOIN $page_tbl USING(id)"
                              . " ORDER BY hits DESC"
                              . " $limitclause");

        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /**
     * Find recent changes.
     */
    function most_recent($params) {
        $limit = 0;
        $since = 0;
        $include_minor_revisions = false;
        $exclude_major_revisions = false;
        $include_all_revisions = false;
        extract($params);

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pick = array();
        if ($since)
            $pick[] = "mtime >= $since";
        
        if ($include_all_revisions) {
            // Include all revisions of each page.
            $table = "$page_tbl INNER JOIN $version_tbl USING(id)";

            if ($exclude_major_revisions) {
		// Include only minor revisions
                $pick[] = "minor_edit <> 0";
            }
            elseif (!$include_minor_revisions) {
		// Include only major revisions
                $pick[] = "minor_edit = 0";
            }
        }
        else {
            $table = ( "$page_tbl INNER JOIN $recent_tbl USING(id)"
                       . " INNER JOIN $version_tbl ON $version_tbl.id=$page_tbl.id");
                
            if ($exclude_major_revisions) {
                // Include only most recent minor revision
                $pick[] = 'version=latestminor';
            }
            elseif (!$include_minor_revisions) {
                // Include only most recent major revision
                $pick[] = 'version=latestmajor';
            }
            else {
                // Include only the latest revision (whether major or minor).
                $pick[] ='version=latestversion';
            }
        }

        $limitclause = $limit ? " LIMIT $limit" : '';
        $whereclause = $pick ? " WHERE " . join(" AND ", $pick) : '';

        // FIXME: use SQL_BUFFER_RESULT for mysql?
        $result = $dbh->query("SELECT $page_tbl.*,$version_tbl.*"
                              . " FROM $table"
                              . $whereclause
                              . " ORDER BY mtime DESC"
                              . $limitclause);

        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    function _update_recent_table($pageid = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $maxmajor = "MAX(CASE WHEN minor_edit=0 THEN version END)";
        $maxminor = "MAX(CASE WHEN minor_edit<>0 THEN version END)";
        $maxversion = "MAX(version)";

        $pageid = (int)$pageid;

        $this->lock();

        $dbh->query("DELETE FROM $recent_tbl"
                    . ( $pageid ? " WHERE id=$pageid" : ""));
        
        $dbh->query( "INSERT INTO $recent_tbl"
                     . " (id, latestversion, latestmajor, latestminor)"
                     . " SELECT id, $maxversion, $maxmajor, $maxminor"
                     . " FROM $version_tbl"
                     . ( $pageid ? " WHERE id=$pageid" : "")
                     . " GROUP BY id" );
        $this->unlock();
    }

    function _update_nonempty_table($pageid = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pageid = (int)$pageid;

        $this->lock();

        $dbh->query("DELETE FROM $nonempty_tbl"
                    . ( $pageid ? " WHERE id=$pageid" : ""));

        $dbh->query("INSERT INTO $nonempty_tbl (id)"
                    . " SELECT $recent_tbl.id"
                    . " FROM $recent_tbl INNER JOIN $version_tbl"
                    . "               ON $recent_tbl.id=$version_tbl.id"
                    . "                  AND version=latestversion"
                    . "  WHERE content<>''"
                    . ( $pageid ? " AND $recent_tbl.id=$pageid" : ""));

        $this->unlock();
    }


    /**
     * Grab a write lock on the tables in the SQL database.
     *
     * Calls can be nested.  The tables won't be unlocked until
     * _unlock_database() is called as many times as _lock_database().
     *
     * @access protected
     */
    function lock($write_lock = true) {
        if ($this->_lock_count++ == 0)
            $this->_lock_tables($write_lock);
    }

    /**
     * Actually lock the required tables.
     */
    function _lock_tables($write_lock) {
        trigger_error("virtual", E_USER_ERROR);
    }
    
    /**
     * Release a write lock on the tables in the SQL database.
     *
     * @access protected
     *
     * @param $force boolean Unlock even if not every call to lock() has been matched
     * by a call to unlock().
     *
     * @see _lock_database
     */
    function unlock($force = false) {
        if ($this->_lock_count == 0)
            return;
        if (--$this->_lock_count <= 0 || $force) {
            $this->_unlock_tables();
            $this->_lock_count = 0;
        }
    }

    /**
     * Actually unlock the required tables.
     */
    function _unlock_tables($write_lock) {
        trigger_error("virtual", E_USER_ERROR);
    }
    
    /**
     * Callback for PEAR (DB) errors.
     *
     * @access protected
     *
     * @param A PEAR_error object.
     */
    function _pear_error_callback($error) {
        $this->_dbh->setErrorHandling(PEAR_ERROR_PRINT);	// prevent recursive loops.
        $this->close();
        //trigger_error($this->_pear_error_message($error), E_USER_WARNING);
        ExitWiki($this->_pear_error_message($error));
    }

    function _pear_error_message($error) {
        $class = get_class($this);
        $message = "$class: fatal database error\n"
             . "\t" . $error->getMessage() . "\n"
             . "\t(" . $error->getDebugInfo() . ")\n";

        return $message;
    }
};

class WikiDB_backend_PearDB_iter
extends WikiDB_backend_iterator
{
    function WikiDB_backend_PearDB_iter(&$backend, &$query_result) {
        if (DB::isError($query_result)) {
            // This shouldn't happen, I thought.
            $backend->_pear_error_callback($query_result);
        }
        
        $this->_backend = &$backend;
        $this->_result = $query_result;
    }
    
    function next() {
        $backend = &$this->_backend;
        if (!$this->_result)
            return false;

        $record = $this->_result->fetchRow(DB_FETCHMODE_ASSOC);
        if (!$record) {
            $this->free();
            return false;
        }
        
        $pagedata = $backend->_extract_page_data($record);
        $rec = array('pagename' => $record['pagename'],
                     'pagedata' => $pagedata);

        if (!empty($record['version'])) {
            $rec['versiondata'] = $backend->_extract_version_data($record);
            $rec['version'] = $record['version'];
        }
        
        return $rec;
    }

    function free () {
        if ($this->_result) {
            $this->_result->free();
            $this->_result = false;
        }
    }
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
