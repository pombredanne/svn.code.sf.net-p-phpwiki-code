<?php
/**
 * Copyright © 2002,2004,2005,2006 $ThePhpWikiProgrammingTeam
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

require_once 'lib/WikiDB/backend.php';

class WikiDB_backend_PearDB extends WikiDB_backend
{
    public $_dbh;

    public function __construct($dbparams)
    {
        require_once('lib/pear/DB/common.php');
        require_once('lib/pear/DB.php');

        // Open connection to database
        $this->_dsn = $dbparams['dsn'];
        $this->_dbparams = $dbparams;
        $this->_lock_count = 0;

        // persistent is usually a DSN option: we override it with a config value.
        //   phptype://username:password@hostspec/database?persistent=false
        $dboptions = array('persistent' => DATABASE_PERSISTENT,
            'debug' => 2);
        //if (preg_match('/^pgsql/', $this->_dsn)) $dboptions['persistent'] = false;
        $this->_dbh = DB::connect($this->_dsn, $dboptions);
        $dbh = &$this->_dbh;
        if (DB::isError($dbh)) {
            trigger_error(
                sprintf(
                "Can't connect to database: %s",
                $this->_pear_error_message($dbh)
            ),
                E_USER_ERROR
            );
        }
        $dbh->setErrorHandling(
            PEAR_ERROR_CALLBACK,
            array($this, '_pear_error_callback')
        );
        $dbh->setFetchMode(DB_FETCHMODE_ASSOC);

        $prefix = isset($dbparams['prefix']) ? $dbparams['prefix'] : '';
        $this->_table_names
            = array('page_tbl' => $prefix . 'page',
            'version_tbl' => $prefix . 'version',
            'link_tbl' => $prefix . 'link',
            'recent_tbl' => $prefix . 'recent',
            'nonempty_tbl' => $prefix . 'nonempty');
        $page_tbl = $this->_table_names['page_tbl'];
        $version_tbl = $this->_table_names['version_tbl'];
        $this->page_tbl_fields = "$page_tbl.id AS id, $page_tbl.pagename AS pagename, $page_tbl.hits AS hits";
        $this->version_tbl_fields = "$version_tbl.version AS version, $version_tbl.mtime AS mtime, " .
            "$version_tbl.minor_edit AS minor_edit, $version_tbl.content AS content, $version_tbl.versiondata AS versiondata";

        $this->_expressions
            = array('maxmajor' => "MAX(CASE WHEN minor_edit=0 THEN version END)",
            'maxminor' => "MAX(CASE WHEN minor_edit<>0 THEN version END)",
            'maxversion' => "MAX(version)",
            'notempty' => "<>''",
            'iscontent' => "content<>''");
    }

    /* with one result row */
    public function getRow($sql)
    {
        return $this->_dbh->getRow($sql);
    }

    /**
     * Close database connection.
     */
    public function close()
    {
        if (!$this->_dbh) {
            return;
        }
        if ($this->_lock_count) {
            trigger_error(
                "WARNING: database still locked " .
                    '(lock_count = $this->_lock_count)' . "\n<br />",
                E_USER_WARNING
            );
        }
        $this->_dbh->setErrorHandling(PEAR_ERROR_PRINT); // prevent recursive loops.
        $this->unlock('force');

        $this->_dbh->disconnect();

        if (!empty($this->_pearerrhandler)) {
            $GLOBALS['ErrorManager']->popErrorHandler();
        }
    }

    /*
     * Fast test for wikipage.
     */
    public function is_wiki_page($pagename)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        return $dbh->getOne(sprintf(
            "SELECT $page_tbl.id AS id"
                . " FROM $nonempty_tbl, $page_tbl"
                . " WHERE $nonempty_tbl.id=$page_tbl.id"
                . "   AND pagename='%s'",
            $dbh->escapeSimple($pagename)
        ));
    }

    public function get_all_pagenames()
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        return $dbh->getCol("SELECT pagename"
            . " FROM $nonempty_tbl, $page_tbl"
            . " WHERE $nonempty_tbl.id=$page_tbl.id");
    }

    /*
     * filter (nonempty pages) currently ignored
     */
    public function numPages($filter = false, $exclude = '')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        return $dbh->getOne("SELECT count(*)"
            . " FROM $nonempty_tbl, $page_tbl"
            . " WHERE $nonempty_tbl.id=$page_tbl.id");
    }

    public function increaseHitCount($pagename)
    {
        $dbh = &$this->_dbh;
        // Hits is the only thing we can update in a fast manner.
        // Note that this will fail silently if the page does not
        // have a record in the page table.  Since it's just the
        // hit count, who cares?
        $dbh->query(sprintf(
            "UPDATE %s SET hits=hits+1 WHERE pagename='%s'",
            $this->_table_names['page_tbl'],
            $dbh->escapeSimple($pagename)
        ));
    }

    /*
     * Read page information from database.
     */
    public function get_pagedata($pagename)
    {
        $dbh = &$this->_dbh;
        //trigger_error("GET_PAGEDATA $pagename", E_USER_NOTICE);
        $result = $dbh->getRow(
            sprintf(
            "SELECT hits,pagedata FROM %s WHERE pagename='%s'",
            $this->_table_names['page_tbl'],
            $dbh->escapeSimple($pagename)
        ),
            DB_FETCHMODE_ASSOC
        );
        return $result ? $this->_extract_page_data($result) : false;
    }

    public function _extract_page_data($data)
    {
        if (empty($data)) {
            return array();
        } elseif (empty($data['pagedata'])) {
            return $data;
        } else {
            $data = array_merge($data, $this->_unserialize($data['pagedata']));
            unset($data['pagedata']);
            return $data;
        }
    }

    public function update_pagedata($pagename, $newdata)
    {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];

        // Hits is the only thing we can update in a fast manner.
        if (count($newdata) == 1 && isset($newdata['hits'])) {
            // Note that this will fail silently if the page does not
            // have a record in the page table.  Since it's just the
            // hit count, who cares?
            $dbh->query(sprintf(
                "UPDATE $page_tbl SET hits=%d WHERE pagename='%s'",
                $newdata['hits'],
                $dbh->escapeSimple($pagename)
            ));
            return;
        }

        $this->lock(array($page_tbl));
        $data = $this->get_pagedata($pagename);
        if (!$data) {
            $data = array();
            $this->_get_pageid($pagename, true); // Creates page record
        }

        $hits = (empty($data['hits'])) ? 0 : (int)$data['hits'];
        unset($data['hits']);

        foreach ($newdata as $key => $val) {
            if ($key == 'hits') {
                $hits = (int)$val;
            } elseif (empty($val)) {
                unset($data[$key]);
            } else {
                $data[$key] = $val;
            }
        }
        $dbh->query(
            "UPDATE $page_tbl"
                . " SET hits=?, pagedata=?"
                . " WHERE pagename=?",
            array($hits, $this->_serialize($data), $pagename)
        );
        $this->unlock(array($page_tbl));
    }

    public function get_cached_html($pagename)
    {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        return $dbh->GetOne(sprintf(
            "SELECT cached_html FROM $page_tbl WHERE pagename='%s'",
            $dbh->escapeSimple($pagename)
        ));
    }

    public function set_cached_html($pagename, $data)
    {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        $dbh->query(
            "UPDATE $page_tbl"
                . " SET cached_html=?"
                . " WHERE pagename=?",
            array($data, $pagename)
        );
    }

    public function _get_pageid($pagename, $create_if_missing = false)
    {
        // check id_cache
        global $request;
        $cache = $request->_dbi->_cache->_id_cache;
        if (isset($cache[$pagename])) {
            if ($cache[$pagename] or !$create_if_missing) {
                return $cache[$pagename];
            }
        }

        // attributes play this game.
        if ($pagename === '') {
            return 0;
        }

        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];

        $query = sprintf(
            "SELECT id FROM $page_tbl WHERE pagename='%s'",
            $dbh->escapeSimple($pagename)
        );

        if (!$create_if_missing) {
            return $dbh->getOne($query);
        }

        $id = $dbh->getOne($query);
        if (empty($id)) {
            $this->lock(array($page_tbl)); // write lock
            $max_id = $dbh->getOne("SELECT MAX(id) FROM $page_tbl");
            $id = $max_id + 1;
            // requires createSequence and on mysql lock the interim table ->getSequenceName
            //$id = $dbh->nextId($page_tbl . "_id");
            $dbh->query(sprintf(
                "INSERT INTO $page_tbl"
                    . " (id,pagename,hits)"
                    . " VALUES (%d,'%s',0)",
                $id,
                $dbh->escapeSimple($pagename)
            ));
            $this->unlock(array($page_tbl));
        }
        return $id;
    }

    public function get_latest_version($pagename)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        return
            (int)$dbh->getOne(sprintf(
                "SELECT latestversion"
                    . " FROM $page_tbl, $recent_tbl"
                    . " WHERE $page_tbl.id=$recent_tbl.id"
                    . "  AND pagename='%s'",
                $dbh->escapeSimple($pagename)
            ));
    }

    public function get_previous_version($pagename, $version)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        return
            (int)$dbh->getOne(sprintf(
                "SELECT version"
                    . " FROM $version_tbl, $page_tbl"
                    . " WHERE $version_tbl.id=$page_tbl.id"
                    . "  AND pagename='%s'"
                    . "  AND version < %d"
                    . " ORDER BY version DESC",
                /* Non portable and useless anyway with getOne
                . " LIMIT 1",
                */
                $dbh->escapeSimple($pagename),
                $version
            ));
    }

    /**
     * Get version data.
     *
     * @param string $pagename Name of the page
     * @param int $version Which version to get
     * @param bool $want_content Do we need content?
     *
     * @return array|false The version data, or false if specified version does not exist.
     */
    public function get_versiondata($pagename, $version, $want_content = false)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        extract($this->_expressions);

        assert(is_string($pagename) and $pagename != '');
        assert($version > 0);

        // FIXME: optimization: sometimes don't get page data?
        if ($want_content) {
            $fields = $this->page_tbl_fields
                . ",$page_tbl.pagedata AS pagedata,"
                . $this->version_tbl_fields;
        } else {
            $fields = $this->page_tbl_fields . ","
                . "mtime, minor_edit, versiondata,"
                . "$iscontent AS have_content";
        }

        $result = $dbh->getRow(
            sprintf(
            "SELECT $fields"
                    . " FROM $page_tbl, $version_tbl"
                    . " WHERE $page_tbl.id=$version_tbl.id"
                    . "  AND pagename='%s'"
                    . "  AND version=%d",
            $dbh->escapeSimple($pagename),
            $version
        ),
            DB_FETCHMODE_ASSOC
        );

        return $this->_extract_version_data($result);
    }

    public function _extract_version_data($query_result)
    {
        if (!$query_result) {
            return false;
        }

        /* Earlier versions (<= 1.3.7) stored the version data in base64.
           This could be done here or in upgrade.
        */
        if (!strstr($query_result['versiondata'], ":")) {
            $query_result['versiondata'] =
                base64_decode($query_result['versiondata']);
        }
        $data = $this->_unserialize($query_result['versiondata']);

        $data['mtime'] = $query_result['mtime'];
        $data['is_minor_edit'] = !empty($query_result['minor_edit']);

        if (isset($query_result['content'])) {
            $data['%content'] = $query_result['content'];
        } elseif ($query_result['have_content']) {
            $data['%content'] = true;
        } else {
            $data['%content'] = '';
        }

        // FIXME: this is ugly.
        if (isset($query_result['pagedata'])) {
            // Query also includes page data.
            // We might as well send that back too...
            unset($query_result['versiondata']);
            $data['%pagedata'] = $this->_extract_page_data($query_result);
        }
        return $data;
    }

    /*
     * Create a new revision of a page.
     */
    public function set_versiondata($pagename, $version, $data)
    {
        $dbh = &$this->_dbh;
        $version_tbl = $this->_table_names['version_tbl'];

        $minor_edit = (int)!empty($data['is_minor_edit']);
        unset($data['is_minor_edit']);

        $mtime = (int)$data['mtime'];
        unset($data['mtime']);
        assert(!empty($mtime));

        $content = isset($data['%content']) ? (string)$data['%content'] : '';
        unset($data['%content']);
        unset($data['%pagedata']);

        $this->lock();
        $id = $this->_get_pageid($pagename, true);

        $dbh->query(sprintf(
            "DELETE FROM $version_tbl"
                . " WHERE id=%d AND version=%d",
            $id,
            $version
        ));
        // generic slow PearDB bind eh quoting.
        $dbh->query(
            "INSERT INTO $version_tbl"
                . " (id,version,mtime,minor_edit,content,versiondata)"
                . " VALUES(?, ?, ?, ?, ?, ?)",
            array($id, $version, $mtime, $minor_edit, $content,
                $this->_serialize($data))
        );

        $this->_update_recent_table($id);
        $this->_update_nonempty_table($id);

        $this->unlock();
    }

    /*
     * Delete an old revision of a page.
     */
    public function delete_versiondata($pagename, $version)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        if (($id = $this->_get_pageid($pagename))) {
            $dbh->query("DELETE FROM $version_tbl"
                . " WHERE id=$id AND version=$version");
            $this->_update_recent_table($id);
            // This shouldn't be needed (as long as the latestversion
            // never gets deleted.)  But, let's be safe.
            $this->_update_nonempty_table($id);
        }
        $this->unlock();
    }

    /*
     * Delete page completely from the database.
     */
    public function purge_page($pagename)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        if (($id = $this->_get_pageid($pagename))) {
            $dbh->query("DELETE FROM $nonempty_tbl WHERE id=$id");
            $dbh->query("DELETE FROM $recent_tbl   WHERE id=$id");
            $dbh->query("DELETE FROM $version_tbl  WHERE id=$id");
            $dbh->query("DELETE FROM $link_tbl     WHERE linkfrom=$id");
            $nlinks = $dbh->getOne("SELECT COUNT(*) FROM $link_tbl WHERE linkto=$id");
            if ($nlinks) {
                // We're still in the link table (dangling link) so we can't delete this
                // altogether.
                $dbh->query("UPDATE $page_tbl SET hits=0, pagedata='' WHERE id=$id");
                $result = 0;
            } else {
                $dbh->query("DELETE FROM $page_tbl WHERE id=$id");
                $result = 1;
            }
            $this->_update_recent_table();
            $this->_update_nonempty_table();
        } else {
            $result = -1; // already purged or not existing
        }
        $this->unlock();
        return $result;
    }

    /**
     * Set links for page.
     *
     * @param string $pagename Page name
     * @param array  $links    List of page(names) which page links to.
     */
    public function set_links($pagename, $links)
    {
        // Update link table.
        // FIXME: optimize: mysql can do this all in one big INSERT/REPLACE.

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        $pageid = $this->_get_pageid($pagename, true);

        $dbh->query("DELETE FROM $link_tbl WHERE linkfrom=$pageid");
        if ($links) {
            $linkseen = array();
            foreach ($links as $link) {
                $linkto = $link['linkto'];
                if ($linkto === "") { // ignore attributes
                    continue;
                }
                if (isset($link['relation'])) {
                    $relation = $this->_get_pageid($link['relation'], true);
                } else {
                    $relation = 0;
                }
                // avoid duplicates
                if (isset($linkseen[$linkto]) and !$relation) {
                    continue;
                }
                if (!$relation) {
                    $linkseen[$linkto] = true;
                }
                $linkid = $this->_get_pageid($linkto, true);
                if (!$linkid) {
                    echo("No link for $linkto on page $pagename");
                    trigger_error("No link for $linkto on page $pagename");
                }
                assert($linkid);
                $dbh->query("INSERT INTO $link_tbl (linkfrom, linkto, relation)"
                    . " VALUES ($pageid, $linkid, $relation)");
            }
            unset($linkseen);
        }
        $this->unlock();
    }

    /**
     * Find pages which link to or are linked from a page.
     *
     * @param string    $pagename       Page name
     * @param bool      $reversed       True to get backlinks
     * @param bool      $include_empty  True to get empty pages
     * @param string    $sortby
     * @param string    $limit
     * @param string    $exclude        Pages to exclude
     * @param bool      $want_relations
     *
     * FIXME: array or iterator?
     * @return object A WikiDB_backend_iterator.
     *
     * TESTME relations: get_links is responsible to add the relation to the pagehash
     * as 'linkrelation' key as pagename. See WikiDB_PageIterator::next
     *   if (isset($next['linkrelation']))
     */
    public function get_links(
        $pagename,
        $reversed = true,
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = '',
        $want_relations = false
    )
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($reversed) {
            list($have, $want) = array('linkee', 'linker');
        } else {
            list($have, $want) = array('linker', 'linkee');
        }
        $orderby = $this->sortby($sortby, 'db', array('pagename'));
        if ($orderby) {
            $orderby = " ORDER BY $want." . $orderby;
        }
        if ($exclude) { // array of pagenames
            $exclude = " AND $want.pagename NOT IN " . $this->_sql_set($exclude);
        } else {
            $exclude = '';
        }

        $qpagename = $dbh->escapeSimple($pagename);
        $sql = "SELECT $want.id AS id, $want.pagename AS pagename, "
            . ($want_relations ? " related.pagename as linkrelation" : " $want.hits AS hits")
            . " FROM "
            . (!$include_empty ? "$nonempty_tbl, " : '')
            . " $page_tbl linkee, $page_tbl linker, $link_tbl "
            . ($want_relations ? " JOIN $page_tbl related ON ($link_tbl.relation=related.id)" : '')
            . " WHERE linkfrom=linker.id AND linkto=linkee.id"
            . " AND $have.pagename='$qpagename'"
            . (!$include_empty ? " AND $nonempty_tbl.id=$want.id" : "")
            //. " GROUP BY $want.id"
            . $exclude
            . $orderby;
        if ($limit) {
            // extract from,count from limit
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }

        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /*
     * Find if a page links to another page
     */
    public function exists_link($pagename, $link, $reversed = false)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($reversed) {
            list($have, $want) = array('linkee', 'linker');
        } else {
            list($have, $want) = array('linker', 'linkee');
        }
        $qpagename = $dbh->escapeSimple($pagename);
        $qlink = $dbh->escapeSimple($link);
        $row = $dbh->GetRow("SELECT CASE WHEN $want.pagename='$qlink' THEN 1 ELSE 0 END as result"
            . " FROM $link_tbl, $page_tbl linker, $page_tbl linkee, $nonempty_tbl"
            . " WHERE linkfrom=linker.id AND linkto=linkee.id"
            . " AND $have.pagename='$qpagename'"
            . " AND $want.pagename='$qlink'");
        return $row['result'];
    }

    public function get_all_pages(
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        $orderby = $this->sortby($sortby, 'db');
        if ($orderby) {
            $orderby = ' ORDER BY ' . $orderby;
        }
        if ($exclude) { // array of pagenames
            $exclude = " AND $page_tbl.pagename NOT IN " . $this->_sql_set($exclude);
        } else {
            $exclude = '';
        }

        if (strstr($orderby, 'mtime ')) { // multiple columns possible
            if ($include_empty) {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $page_tbl, $recent_tbl, $version_tbl"
                    . " WHERE $page_tbl.id=$recent_tbl.id"
                    . " AND $page_tbl.id=$version_tbl.id AND latestversion=version"
                    . $exclude
                    . $orderby;
            } else {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $nonempty_tbl, $page_tbl, $recent_tbl, $version_tbl"
                    . " WHERE $nonempty_tbl.id=$page_tbl.id"
                    . " AND $page_tbl.id=$recent_tbl.id"
                    . " AND $page_tbl.id=$version_tbl.id AND latestversion=version"
                    . $exclude
                    . $orderby;
            }
        } else {
            if ($include_empty) {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $page_tbl"
                    . ($exclude ? " WHERE $exclude" : '')
                    . $orderby;
            } else {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $nonempty_tbl, $page_tbl"
                    . " WHERE $nonempty_tbl.id=$page_tbl.id"
                    . $exclude
                    . $orderby;
            }
        }
        if ($limit && $orderby) {
            // extract from,count from limit
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /*
     * Text search (title or full text)
     */
    public function text_search(
        $search,
        $fulltext = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        $orderby = $this->sortby($sortby, 'db');
        if ($orderby) {
            $orderby = ' ORDER BY ' . $orderby;
        }
        $searchclass = get_class($this) . "_search";
        // no need to define it everywhere and then fallback. memory!
        if (!class_exists($searchclass)) {
            $searchclass = "WikiDB_backend_PearDB_search";
        }
        $searchobj = new $searchclass($search, $dbh);

        $table = "$nonempty_tbl, $page_tbl";
        $join_clause = "$nonempty_tbl.id=$page_tbl.id";
        $fields = $this->page_tbl_fields;

        if ($fulltext) {
            $table .= ", $recent_tbl";
            $join_clause .= " AND $page_tbl.id=$recent_tbl.id";

            $table .= ", $version_tbl";
            $join_clause .= " AND $page_tbl.id=$version_tbl.id AND latestversion=version";

            $fields .= ", $page_tbl.pagedata as pagedata, " . $this->version_tbl_fields;
            $callback = new WikiMethodCb($searchobj, "_fulltext_match_clause");
        } else {
            $callback = new WikiMethodCb($searchobj, "_pagename_match_clause");
        }
        $search_clause = $search->makeSqlClauseObj($callback);
        $sql = "SELECT $fields FROM $table"
            . " WHERE $join_clause"
            . "  AND ($search_clause)"
            . $orderby;
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }

        $iter = new WikiDB_backend_PearDB_iter($this, $result);
        $iter->stoplisted = @$searchobj->stoplisted;
        return $iter;
    }

    //Todo: check if the better Mysql MATCH operator is supported,
    // (ranked search) and also google like expressions.
    public function _sql_match_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->escapeSimple($word);
        //$page_tbl = $this->_table_names['page_tbl'];
        //Note: Mysql 4.1.0 has a bug which fails with binary fields.
        //      e.g. if word is lowercased.
        // http://bugs.mysql.com/bug.php?id=1491
        return "LOWER(pagename) LIKE '%$word%'";
    }

    public function _sql_casematch_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->escapeSimple($word);
        return "pagename LIKE '%$word%'";
    }

    public function _fullsearch_sql_match_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->escapeSimple($word);
        //$page_tbl = $this->_table_names['page_tbl'];
        //Mysql 4.1.1 has a bug which fails here if word is lowercased.
        return "LOWER(pagename) LIKE '%$word%' OR content LIKE '%$word%'";
    }

    public function _fullsearch_sql_casematch_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->escapeSimple($word);
        return "pagename LIKE '%$word%' OR content LIKE '%$word%'";
    }

    public function _sql_set(&$pagenames)
    {
        $s = '(';
        foreach ($pagenames as $p) {
            $s .= ("'" . $this->_dbh->escapeSimple($p) . "',");
        }
        return substr($s, 0, -1) . ")";
    }

    /*
     * Find highest or lowest hit counts.
     */
    public function most_popular($limit = 20, $sortby = '-hits')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        if ($limit < 0) {
            $order = "ASC";
            $limit = -$limit;
            $where = "";
        } else {
            $order = "DESC";
            $where = " AND hits > 0";
        }
        $orderby = '';
        if ($sortby != '-hits') {
            if ($order = $this->sortby($sortby, 'db')) {
                $orderby = " ORDER BY " . $order;
            }
        } else {
            $orderby = " ORDER BY hits $order";
        }
        $sql = "SELECT "
            . $this->page_tbl_fields
            . " FROM $nonempty_tbl, $page_tbl"
            . " WHERE $nonempty_tbl.id=$page_tbl.id"
            . $where
            . $orderby;
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /*
     * Find recent changes.
     */
    public function most_recent($params)
    {
        $limit = 0;
        $since = 0;
        $include_minor_revisions = false;
        $exclude_major_revisions = false;
        $include_all_revisions = false;
        extract($params);

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pick = array();
        if ($since) {
            $pick[] = "mtime >= $since";
        }

        if ($include_all_revisions) {
            // Include all revisions of each page.
            $table = "$page_tbl, $version_tbl";
            $join_clause = "$page_tbl.id=$version_tbl.id";

            if ($exclude_major_revisions) {
                // Include only minor revisions
                $pick[] = "minor_edit <> 0";
            } elseif (!$include_minor_revisions) {
                // Include only major revisions
                $pick[] = "minor_edit = 0";
            }
        } else {
            $table = "$page_tbl, $recent_tbl";
            $join_clause = "$page_tbl.id=$recent_tbl.id";
            $table .= ", $version_tbl";
            $join_clause .= " AND $version_tbl.id=$page_tbl.id";

            if ($exclude_major_revisions) {
                // Include only most recent minor revision
                $pick[] = 'version=latestminor';
            } elseif (!$include_minor_revisions) {
                // Include only most recent major revision
                $pick[] = 'version=latestmajor';
            } else {
                // Include only the latest revision (whether major or minor).
                $pick[] = 'version=latestversion';
            }
        }
        $order = "DESC";
        if ($limit < 0) {
            $order = "ASC";
            $limit = -$limit;
        }
        $where_clause = $join_clause;
        if ($pick) {
            $where_clause .= " AND " . join(" AND ", $pick);
        }
        $sql = "SELECT "
            . $this->page_tbl_fields . ", " . $this->version_tbl_fields
            . " FROM $table"
            . " WHERE $where_clause"
            . " ORDER BY mtime $order";
        // FIXME: use SQL_BUFFER_RESULT for mysql?
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /*
     * Find referenced empty pages.
     */
    public function wanted_pages($exclude = '', $sortby = '', $limit = '')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        if ($orderby = $this->sortby($sortby, 'db', array('pagename', 'wantedfrom'))) {
            $orderby = 'ORDER BY ' . $orderby;
        }

        if ($exclude) { // array of pagenames
            $exclude = " AND p.pagename NOT IN " . $this->_sql_set($exclude);
        }

        $sql = "SELECT p.pagename AS wantedfrom, pp.pagename AS pagename"
            . " FROM $page_tbl p, $link_tbl linked"
            . " LEFT JOIN $page_tbl pp ON linked.linkto = pp.id"
            . " LEFT JOIN $nonempty_tbl ne ON linked.linkto = ne.id"
            . " WHERE ne.id IS NULL"
            . " AND p.id = linked.linkfrom"
            . $exclude
            . $orderby;
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count * 3);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_generic_iter($this, $result);
    }

    /**
     * Rename page in the database.
     *
     * @param string $pagename Current page name
     * @param string $to       Future page name
     */

    public function rename_page($pagename, $to)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        $id = $this->_get_pageid($pagename);
        $dbh->query(sprintf(
            "UPDATE $page_tbl SET pagename='%s' WHERE id=$id",
            $dbh->escapeSimple($to)
        ));
        $this->unlock();
        return $id;
    }

    /**
     * Delete page from the database with backup possibility.
     * This should remove all links (from the named page) from
     * the link database.
     *
     * @param string $pagename Page name.
     * i.e save_page('') and DELETE nonempty id
     * Can be undone and is seen in RecentChanges.
     */

    public function delete_page($pagename)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $mtime = time();
        $user =& $request->_user;
        $vdata = array('author' => $user->getId(),
            'author_id' => $user->getAuthenticatedId(),
            'mtime' => $mtime);

        $this->lock(); // critical section:
        $version = $this->get_latest_version($pagename);
        $this->set_versiondata($pagename, $version + 1, $vdata);
        $this->set_links($pagename, array()); // links are purged.
        // SQL needs to invalidate the non_empty id
        if (!WIKIDB_NOCACHE_MARKUP) {
            // need the hits, perms and LOCKED, otherwise you can reset the perm
            // by action=remove and re-create it with default perms
            $pagedata = $this->get_pagedata($pagename);
            unset($pagedata['_cached_html']);
            $this->update_pagedata($pagename, $pagedata);
        }
        $this->unlock();
    }


    public function _update_recent_table($pageid = false)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        extract($this->_expressions);

        $pageid = (int)$pageid;

        $this->lock();
        $dbh->query("DELETE FROM $recent_tbl"
                . ($pageid ? " WHERE id=$pageid" : ""));
        $dbh->query("INSERT INTO $recent_tbl"
                . " (id, latestversion, latestmajor, latestminor)"
                . " SELECT id, $maxversion, $maxmajor, $maxminor"
                . " FROM $version_tbl"
                . ($pageid ? " WHERE id=$pageid" : "")
                . " GROUP BY id");
        $this->unlock();
    }

    public function _update_nonempty_table($pageid = false)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pageid = (int)$pageid;

        extract($this->_expressions);
        $this->lock();
        $dbh->query("DELETE FROM $nonempty_tbl"
            . ($pageid ? " WHERE id=$pageid" : ""));
        $dbh->query("INSERT INTO $nonempty_tbl (id)"
            . " SELECT $recent_tbl.id"
            . " FROM $recent_tbl, $version_tbl"
            . " WHERE $recent_tbl.id=$version_tbl.id"
            . "  AND version=latestversion"
            // We have some specifics here (Oracle)
            //. "  AND content<>''"
            . "  AND content $notempty" // On Oracle not just "<>''"
            . ($pageid ? " AND $recent_tbl.id=$pageid" : ""));
        $this->unlock();
    }

    /*
     * Grab a write lock on the tables in the SQL database.
     *
     * Calls can be nested.  The tables won't be unlocked until
     * _unlock_database() is called as many times as _lock_database().
     */
    public function lock($tables = array(), $write_lock = true)
    {
        if ($this->_lock_count++ == 0) {
            $this->_lock_tables($write_lock);
        }
    }

    /*
     * Actually lock the required tables.
     */
    protected function _lock_tables($write_lock = true)
    {
        trigger_error("virtual", E_USER_ERROR);
    }

    /**
     * Release a write lock on the tables in the SQL database.
     *
     * @param array $tables
     * @param bool $force Unlock even if not every call to lock() has been matched
     * by a call to unlock().
     *
     * @see _lock_database
     */
    public function unlock($tables = array(), $force = false)
    {
        if ($this->_lock_count == 0) {
            return;
        }
        if (--$this->_lock_count <= 0 || $force) {
            $this->_unlock_tables();
            $this->_lock_count = 0;
        }
    }

    /**
     * Actually unlock the required tables.
     */
    protected function _unlock_tables()
    {
        trigger_error("virtual", E_USER_ERROR);
    }

    /*
     * Serialize data
     */
    public function _serialize($data)
    {
        if (empty($data)) {
            return '';
        }
        assert(is_array($data));
        return serialize($data);
    }

    /*
     * Unserialize data
     */
    public function _unserialize($data)
    {
        return empty($data) ? array() : unserialize($data);
    }

    /**
     * Callback for PEAR (DB) errors.
     *
     * @param $error PEAR_error object.
     */
    public function _pear_error_callback($error)
    {
        if ($this->_is_false_error($error)) {
            return;
        }

        $this->_dbh->setErrorHandling(PEAR_ERROR_PRINT); // prevent recursive loops.
        $this->close();
        trigger_error($this->_pear_error_message($error), E_USER_ERROR);
    }

    /*
     * Detect false errors messages from PEAR DB.
     *
     * The version of PEAR DB which ships with PHP 4.0.6 has a bug in that
     * it doesn't recognize "LOCK" and "UNLOCK" as SQL commands which don't
     * return any data.  (So when a "LOCK" command doesn't return any data,
     * DB reports it as an error, when in fact, it's not.)
     *
     * @return bool True iff error is not really an error.
     */
    private function _is_false_error($error)
    {
        if ($error->getCode() != DB_ERROR) {
            return false;
        }

        $query = $this->_dbh->last_query;

        if (!preg_match('/^\s*"?(INSERT|UPDATE|DELETE|REPLACE|CREATE'
            . '|DROP|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\s/', $query)
        ) {
            // Last query was not of the sort which doesn't return any data.
            //" <--kludge for brain-dead syntax coloring
            return false;
        }

        if (!in_array('ismanip', get_class_methods('DB'))) {
            // Pear shipped with PHP 4.0.4pl1 (and before, presumably)
            // does not have the DB::isManip method.
            return true;
        }

        if (DB::isManip($query)) {
            // If Pear thinks it's an isManip then it wouldn't have thrown
            // the error we're testing for....
            return false;
        }

        return true;
    }

    public function _pear_error_message($error)
    {
        $class = get_class($this);
        $message = "$class: fatal database error\n"
            . "\t" . $error->getMessage() . "\n"
            . "\t(" . $error->getDebugInfo() . ")\n";

        // Prevent password from being exposed during a connection error
        $safe_dsn = preg_replace(
            '| ( :// .*? ) : .* (?=@) |xs',
            '\\1:XXXXXXXX',
            $this->_dsn
        );
        return str_replace($this->_dsn, $safe_dsn, $message);
    }

    /* some variables and functions for DB backend abstraction (action=upgrade) */
    public function database()
    {
        return $this->_dbh->dsn['database'];
    }

    public function backendType()
    {
        return $this->_dbh->phptype;
    }

    public function connection()
    {
        return $this->_dbh->connection;
    }

    public function listOfTables()
    {
        return $this->_dbh->getListOf('tables');
    }
}

/**
 * This class is a generic iterator.
 *
 * WikiDB_backend_PearDB_iter only iterates over things that have
 * 'pagename', 'pagedata', etc. etc.
 *
 * Probably WikiDB_backend_PearDB_iter and this class should be merged
 * (most of the code is cut-and-paste :-( ), but I am trying to make
 * changes that could be merged easily.
 *
 * @author: Dan Frankowski
 */
class WikiDB_backend_PearDB_generic_iter extends WikiDB_backend_iterator
{
    public function __construct($backend, $query_result, $field_list = null)
    {
        if (DB::isError($query_result)) {
            // This shouldn't happen, I thought.
            $backend->_pear_error_callback($query_result);
        }

        $this->_backend = &$backend;
        $this->_result = $query_result;
        $this->_options = $field_list;
    }

    public function count()
    {
        if (!$this->_result) {
            return false;
        }
        return $this->_result->numRows();
    }

    public function next()
    {
        if (!$this->_result) {
            return false;
        }

        $record = $this->_result->fetchRow(DB_FETCHMODE_ASSOC);
        if (!$record) {
            $this->free();
            return false;
        }

        return $record;
    }

    public function reset()
    {
        if ($this->_result) {
            $this->_result->MoveFirst();
        }
    }

    public function free()
    {
        if ($this->_result) {
            $this->_result->free();
            $this->_result = false;
        }
    }

    public function asArray()
    {
        $result = array();
        while ($page = $this->next()) {
            $result[] = $page;
        }
        return $result;
    }
}

class WikiDB_backend_PearDB_iter extends WikiDB_backend_PearDB_generic_iter
{
    public function next()
    {
        $backend = &$this->_backend;
        if (!$this->_result) {
            return false;
        }

        if (!is_object($this->_result)) {
            return false;
        }

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
}

class WikiDB_backend_PearDB_search extends WikiDB_backend_search_sql
{
    // no surrounding quotes because we know it's a string
}
