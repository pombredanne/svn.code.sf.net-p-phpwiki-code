<?php
/**
 * Copyright © 2004-2010 Reini Urban
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

/*
 * Pagedata
 *
 * maintained by WikiPage
 *  //:latestversion
 *  //:deleted (*)     (Set if latest content is empty.)
 *  //:pagename (*)
 *
 *  hits
 *  locked
 *
 * Versiondata
 *
 *  %content (?should this be here?)
 *  _supplanted : Time version ceased to be the current version
 *
 *  mtime (*)   : Time of version edit.
 *  orig_mtime
 *  is_minor_edit (*)
 *  author      : nominal author
 *  author_id   : authenticated author
 *  summary
 *
 *  //version
 *  //created (*)
 *  //%superceded
 *
 *  //:serial
 *
 *   (types are scalars: strings, ints, bools)
 */

/**
 * A WikiDB_backend handles the storage and retrieval of data for a WikiDB.
 *
 * It does not have to be this way, of course, but the standard WikiDB uses
 * a WikiDB_backend.  (Other WikiDB's could be written which use some other
 * method to access their underlying data store.)
 *
 * The interface outlined here seems to work well with both RDBM based
 * and flat DBM/hash based methods of data storage.
 *
 * Though it contains some default implementation of certain methods,
 * this is an abstract base class.  It is expected that most efficient
 * backends will override nearly all the methods in this class.
 *
 * @see WikiDB
 */

abstract class WikiDB_backend
{
    public $_sortby;
    public $_dbh;

    /**
     * Get page meta-data from database.
     *
     * @param string $pagename Page name.
     * @return array hash
     * Returns a hash containing the page meta-data.
     * Returns an empty array if there is no meta-data for the requested page.
     * Keys which might be present in the hash are:
     * <dl>
     *  <dt> locked  <dd> If the page is locked.
     *  <dt> hits    <dd> The page hit count.
     *  <dt> created <dd> Unix time of page creation. (FIXME: Deprecated: I
     *                    don't think we need this...)
     * </dl>
     */
    abstract public function get_pagedata($pagename);

    /**
     * Update the page meta-data.
     *
     * Set page meta-data.
     *
     * Only meta-data whose keys are preset in $newdata is affected.
     *
     * For example:
     * <pre>
     *   $backend->update_pagedata($pagename, array('locked' => 1));
     * </pre>
     * will set the value of 'locked' to 1 for the specified page, but it
     * will not affect the value of 'hits' (or whatever other meta-data
     * may have been stored for the page.)
     *
     * To delete a particular piece of meta-data, set its value to false.
     * <pre>
     *   $backend->update_pagedata($pagename, array('locked' => false));
     * </pre>
     *
     * @param string $pagename Page name.
     * @param array $newdata hash New meta-data.
     */
    abstract public function update_pagedata($pagename, $newdata);

    /**
     * Get the current version number for a page.
     *
     * @param string $pagename Page name.
     * @return int The latest version number for the page.  Returns zero if
     *  no versions of a page exist.
     */
    abstract public function get_latest_version($pagename);

    /**
     * Get preceding version number.
     *
     * @param string $pagename Page name.
     * @param int $version Find version before this one.
     * @return int The version number of the version in the database which
     *  immediately precedes $version.
     */
    abstract public function get_previous_version($pagename, $version);

    /**
     * Get revision meta-data and content.
     *
     * @param string $pagename Page name.
     * @param int $version Which version to get.
     * @param bool $want_content
     *  Indicates the caller really wants the page content.  If this
     *  flag is not set, the backend is free to skip fetching of the
     *  page content (as that may be expensive).  If the backend omits
     *  the content, the backend might still want to set the value of
     *  '%content' to the empty string if it knows there's no content.
     *
     * @return array|bool The version data, or false if specified version does not exist.
     *
     * Some keys which might be present in the $versiondata hash are:
     * <dl>
     * <dt> %content
     *  <dd> This is a pseudo-meta-data element (since it's actually
     *       the page data, get it?) containing the page content.
     *       If the content was not fetched, this key may not be present.
     * </dl>
     * For description of other version meta-data see WikiDB_PageRevision::get().
     * @see WikiDB_PageRevision::get
     */
    abstract public function get_versiondata($pagename, $version, $want_content = false);

    /**
     * Create a new page revision.
     *
     * If the given ($pagename,$version) is already in the database,
     * this method completely overwrites any stored data for that version.
     *
     * @param string $pagename string Page name.
     * @param int $version New revisions content.
     * @param array $data hash New revision metadata.
     *
     * @see get_versiondata
     */
    abstract public function set_versiondata($pagename, $version, $data);

    /**
     * Update page version meta-data.
     *
     * If the given ($pagename,$version) is already in the database,
     * this method only changes those meta-data values whose keys are
     * explicitly listed in $newdata.
     *
     * @param string $pagename Page name.
     * @param int $version New revisions content.
     * @param array $newdata hash New revision metadata.
     * @see set_versiondata, get_versiondata
     */
    public function update_versiondata($pagename, $version, $newdata)
    {
        $data = $this->get_versiondata($pagename, $version, true);
        if (!$data) {
            assert($data);
            return;
        }
        foreach ($newdata as $key => $val) {
            if (empty($val)) {
                unset($data[$key]);
            } else {
                $data[$key] = $val;
            }
        }
        $this->set_versiondata($pagename, $version, $data);
    }

    /**
     * Delete an old revision of a page.
     *
     * Note that one is never allowed to delete the most recent version,
     * but that this requirement is enforced by WikiDB not by the backend.
     *
     * In fact, to be safe, backends should probably allow the deletion of
     * the most recent version.
     *
     * @param string $pagename Page name.
     * @param int $version int Version to delete.
     */
    abstract public function delete_versiondata($pagename, $version);

    /**
     * Rename page in the database.
     *
     * @param string $pagename Current page name
     * @param string $to       Future page name
     */
    abstract public function rename_page($pagename, $to);

    /**
     * Delete page from the database with backup possibility.
     * This should remove all links (from the named page) from
     * the link database.
     *
     * @param string $pagename Page name.
     * i.e save_page('') and DELETE nonempty id
     * Can be undone and is seen in RecentChanges.
     */
    abstract public function delete_page($pagename);

    /**
     * Delete page (and all its revisions) from the database.
     *
     * @param string $pagename Page name.
     */
    abstract public function purge_page($pagename);

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
     */
    abstract public function get_links(
        $pagename,
        $reversed = true,
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = '',
        $want_relations = false
    );

    /**
     * Set links for page.
     *
     * @param string $pagename Page name
     * @param array  $links    List of page(names) which page links to.
     */
    abstract public function set_links($pagename, $links);

    /**
     * Get all revisions of a page.
     *
     * @param string $pagename The page name.
     * @return object A WikiDB_backend_iterator.
     */
    public function get_all_revisions($pagename)
    {
        include_once 'lib/WikiDB/backend/dumb/AllRevisionsIter.php';
        return new WikiDB_backend_dumb_AllRevisionsIter($this, $pagename);
    }

    /**
     * Get all pages in the database.
     *
     * Pages should be returned in alphabetical order if that is
     * feasible.
     *
     * @param bool $include_empty
     * If set, even pages with no content will be returned
     * --- but still only if they have at least one revision (not
     * counting the default revision 0) entered in the database.
     *
     * Normally pages whose current revision has empty content
     * are not returned as these pages are considered to be
     * non-existing.
     *
     * @param string $sortby
     * @param string $limit
     * @param string $exclude
     * @return object A WikiDB_backend_iterator.
     */
    abstract public function get_all_pages(
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    );

    /**
     * Title or full text search.
     *
     * Pages should be returned in alphabetical order if that is
     * feasible.
     *
     * @param object $search object A TextSearchQuery object describing the parsed query string,
     *                       with efficient methods for SQL and PCRE match.
     *
     * @param bool $fulltext If true, a full text search is performed,
     *  otherwise a title search is performed.
     *
     * @param string $sortby
     * @param string $limit
     * @param string $exclude
     *
     * @return object A WikiDB_backend_iterator.
     *
     * @see WikiDB::titleSearch
     */
    public function text_search(
        $search,
        $fulltext = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        // This method implements a simple linear search
        // through all the pages in the database.
        //
        // It is expected that most backends will overload
        // this method with something more efficient.
        include_once 'lib/WikiDB/backend/dumb/TextSearchIter.php';
        // ignore $limit
        $pages = $this->get_all_pages(false, $sortby, '', $exclude);
        return new WikiDB_backend_dumb_TextSearchIter(
            $this,
            $pages,
            $search,
            $fulltext,
            array('limit' => $limit,
                'exclude' => $exclude)
        );
    }

    /**
     *
     * @param object $pages      A TextSearchQuery object.
     * @param object $linkvalue  A TextSearchQuery object for the link values
     *                          (linkto, relation or backlinks or attribute values).
     * @param string $linktype   One of the 4 link types.
     * @param object|bool $relation   A TextSearchQuery object or false.
     * @param array $options    Currently ignored. hash of sortby, limit, exclude.
     * @return object A WikiDB_backend_iterator.
     * @see WikiDB::linkSearch
     */
    public function link_search($pages, $linkvalue, $linktype, $relation = false, $options = array())
    {
        include_once 'lib/WikiDB/backend/dumb/LinkSearchIter.php';
        $pageiter = $this->text_search($pages);
        return new WikiDB_backend_dumb_LinkSearchIter($this, $pageiter, $linkvalue, $linktype, $relation, $options);
    }

    /**
     * Find pages with highest hit counts.
     *
     * Find the pages with the highest hit counts.  The pages should
     * be returned in reverse order by hit count.
     *
     * @param  int $limit No more than this many pages
     * @param  string $sortby
     * @return object  A WikiDB_backend_iterator.
     */
    public function most_popular($limit = 20, $sortby = '-hits')
    {
        // This is method fetches all pages, then
        // sorts them by hit count.
        // (Not very efficient.)
        //
        // It is expected that most backends will overload
        // method with something more efficient.
        include_once 'lib/WikiDB/backend/dumb/MostPopularIter.php';
        $pages = $this->get_all_pages(false, $sortby);
        return new WikiDB_backend_dumb_MostPopularIter($this, $pages, $limit);
    }

    /**
     * Find recent changes.
     *
     * @param array $params hash See WikiDB::mostRecent for a description
     *  of parameters which can be included in this hash.
     * @return object A WikiDB_backend_iterator.
     * @see WikiDB::mostRecent
     */
    public function most_recent($params)
    {
        // This method is very inefficient and searches through
        // all pages for the most recent changes.
        //
        // It is expected that most backends will overload
        // method with something more efficient.
        include_once 'lib/WikiDB/backend/dumb/MostRecentIter.php';
        $pages = $this->get_all_pages(true, '-mtime');
        return new WikiDB_backend_dumb_MostRecentIter($this, $pages, $params);
    }

    public function wanted_pages($exclude = '', $sortby = '', $limit = '')
    {
        include_once 'lib/WikiDB/backend/dumb/WantedPagesIter.php';
        $allpages = $this->get_all_pages();
        return new WikiDB_backend_dumb_WantedPagesIter($this, $allpages, $exclude, $sortby, $limit);
    }

    /**
     * Lock backend database.
     *
     * Calls may be nested.
     *
     * @param array $tables
     * @param bool $write_lock Unless this is set to false, a write lock
     *     is acquired, otherwise a read lock.  If the backend doesn't support
     *     read locking, then it should make a write lock no matter which type
     *     of lock was requested.
     *
     *     All backends <em>should</em> support write locking.
     */
    abstract public function lock($tables = array(), $write_lock = true);

    /**
     * Unlock backend database.
     *
     * @param array $tables
     * @param bool $force Normally, the database is not unlocked until
     *  unlock() is called as many times as lock() has been.  If $force is
     *  set to true, the the database is unconditionally unlocked.
     */
    abstract public function unlock($tables = array(), $force = false);

    /**
     * Close database.
     */
    abstract public function close();

    /**
     * Synchronize with filesystem.
     *
     * This should flush all unwritten data to the filesystem.
     */
    public function sync()
    {
    }

    /**
     * Optimize the database.
     *
     * @return bool
     */
    public function optimize()
    {
        return true;
    }

    /**
     * Check database integrity.
     *
     * This should check the validity of the internal structure of the database.
     * Errors should be reported via:
     * <pre>
     *   trigger_error("Message goes here.", E_USER_WARNING);
     * </pre>
     *
     * @param bool $args
     * @return bool True iff database is in a consistent state.
     */
    public function check($args = false)
    {
        return true;
    }

    /**
     * Put the database into a consistent state
     * by reparsing and restoring all pages.
     *
     * This should put the database into a consistent state.
     * (I.e. rebuild indexes, etc...)
     *
     * @param bool $args
     * @return bool True iff successful.
     */
    public function rebuild($args = false)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $dbh = $request->getDbh();
        $iter = $dbh->getAllPages();
        while ($page = $iter->next()) {
            $current = $page->getCurrentRevision();
            $pagename = $page->getName();
            $meta = $current->_data;
            $version = $current->getVersion();
            $content =& $meta['%content'];
            $formatted = new TransformedText($page, $content, $current->getMetaData());
            $type = $formatted->getType();
            $meta['pagetype'] = $type->getName();
            $links = $formatted->getWikiPageLinks(); // linkto => relation
            $this->lock(array('version', 'page', 'recent', 'link', 'nonempty'));
            $this->set_versiondata($pagename, $version, $meta);
            $this->set_links($pagename, $links);
            $this->unlock(array('version', 'page', 'recent', 'link', 'nonempty'));
        }
        return true;
    }

    public function _parse_searchwords($search)
    {
        $search = strtolower(trim($search));
        if (!$search) {
            return array(array(), array());
        }

        $words = preg_split('/\s+/', $search);
        $exclude = array();
        foreach ($words as $key => $word) {
            if ($word[0] == '-' && $word != '-') {
                $word = substr($word, 1);
                $exclude[] = preg_quote($word);
                unset($words[$key]);
            }
        }
        return array($words, $exclude);
    }

    /*
     * Split the given limit parameter into offset,limit. (offset is optional. default: 0)
     * Duplicate the PageList function here to avoid loading the whole PageList.php
     * Usage:
     *   list($offset,$count) = $this->limit($args['limit']);
     */
    public static function limit($limit)
    {
        if (strstr($limit, ',')) {
            list($from, $limit) = explode(',', $limit);
            if ((!empty($from) && !is_numeric($from)) or (!empty($limit) && !is_numeric($limit))) {
                trigger_error(_("Illegal “limit” argument: must be an integer or two integers separated by comma"));
                return array(0, 0);
            }
            return array($from, $limit);
        } else {
            if (!empty($limit) && !is_numeric($limit)) {
                trigger_error(_("Illegal “limit” argument: must be an integer or two integers separated by comma"));
                return array(0, 0);
            }
            return array(0, $limit);
        }
    }

    /*
     * Handle sortby requests for the DB iterator and table header links.
     * Prefix the column with + or - like "+pagename","-mtime", ...
     * supported actions: 'flip_order' "mtime" => "+mtime" => "-mtime" ...
     *                    'db'         "-pagename" => "pagename DESC"
     * In PageList all columns are sortable. (patch by Dan Frankowski)
     * Here with the backend only some, the rest is delayed to PageList.
     * (some kind of DumbIter)
     * Duplicate the PageList function here to avoid loading the whole
     * PageList.php, and it forces the backend specific sortable_columns()
     */
    public function sortby($column, $action, $sortable_columns = array())
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (empty($column)) {
            return '';
        }
        // Support multiple comma-delimited sortby args: "+hits,+pagename"
        if (strstr($column, ',')) {
            $result = array();
            foreach (explode(',', $column) as $col) {
                if ($col) {
                    if (empty($this)) {
                        $res = WikiDB_backend::sortby($col, $action);
                    } else {
                        $res = $this->sortby($col, $action);
                    }
                    if ($res) {
                        $result[] = $res;
                    }
                }
            }
            return join(",", $result);
        }
        if (substr($column, 0, 1) == '+') {
            $order = '+';
            $column = substr($column, 1);
        } elseif (substr($column, 0, 1) == '-') {
            $order = '-';
            $column = substr($column, 1);
        }
        // default order: +pagename, -mtime, -hits
        if (empty($order)) {
            if (in_array($column, array('mtime', 'hits'))) {
                $order = '-';
            } else {
                $order = '+';
            }
        }
        if ($action == 'flip_order') {
            return ($order == '+' ? '-' : '+') . $column;
        } elseif ($action == 'init') {
            $this->_sortby[$column] = $order;
            return $order . $column;
        } elseif ($action == 'check') {
            return (!empty($this->_sortby[$column]) or
                ($request->getArg('sortby') and
                    strstr($request->getArg('sortby'), $column)));
        } elseif ($action == 'db') {
            // native sort possible?
            if (!empty($this) and !$sortable_columns) {
                $sortable_columns = $this->sortable_columns();
            }
            if (in_array($column, $sortable_columns)) {
                // asc or desc: +pagename, -pagename
                return $column . ($order == '+' ? ' ASC' : ' DESC');
            } else {
                return '';
            }
        }
        return '';
    }

    public function sortable_columns()
    {
        return array('pagename' /*,'mtime','author_id','author'*/);
    }

    // adds surrounding quotes
    public function quote($s)
    {
        return "'" . $s . "'";
    }

    // no surrounding quotes because we know it's a string
    public function qstr($s)
    {
        return $s;
    }

    public function isSQL()
    {
        return in_array(DATABASE_TYPE, array('SQL', 'PDO'));
    }

    public function backendType()
    {
        return DATABASE_TYPE;
    }

    public function write_accesslog(&$entry)
    {
        if (!$this->isSQL()) {
            return;
        }
        $dbh = &$this->_dbh;
        $log_tbl = $entry->_accesslog->logtable;
        // duration problem: sprintf "%f" might use comma e.g. "100,201" in european locales
        $dbh->query(
            "INSERT INTO $log_tbl"
                . " (time_stamp,remote_host,remote_user,request_method,request_line,request_args,"
                . "request_uri,request_time,status,bytes_sent,referer,agent,request_duration)"
                . " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array(
                // Problem: date formats are backend specific. Either use unixtime as %d (long),
                // or the native timestamp format.
                $entry->time,
                $entry->host,
                $entry->user,
                $entry->request_method,
                $entry->request,
                $entry->request_args,
                $entry->request_uri,
                $entry->ncsa_time($entry->time),
                $entry->status,
                (int)$entry->size,
                $entry->referer,
                $entry->user_agent,
                $entry->duration)
        );
    }
}

/**
 * Iterator returned by backend methods which (possibly) return
 * multiple records.
 *
 * FIXME: This might be two separate classes: page_iter and version_iter.
 * For the versions we have WikiDB_backend_dumb_AllRevisionsIter.
 */
abstract class WikiDB_backend_iterator
{
    public $_options;

    /**
     * Get the next record in the iterator set.
     *
     * This returns a hash. The hash may contain the following keys:
     * <dl>
     * <dt> pagename <dt> (string) the page name or linked page name on link iterators
     * <dt> version  <dt> (int) the version number
     * <dt> pagedata <dt> (hash) page meta-data (as returned from backend::get_pagedata().)
     * <dt> versiondata <dt> (hash) page meta-data (as returned from backend::get_versiondata().)
     * <dt> linkrelation <dt> (string) the page naming the relation (e.g. isa:=page <=> isa)
     *
     * If this is a page iterator, it must contain the 'pagename' entry --- the others
     * are optional.
     *
     * If this is a version iterator, the 'pagename', 'version', <strong>and</strong> 'versiondata'
     * entries are mandatory.  ('pagedata' is optional.)
     *
     * If this is a link iterator, the 'pagename' is mandatory, 'linkrelation' is optional.
     */
    abstract public function next();

    public function count()
    {
        if (!empty($this->_pages)) {
            return count($this->_pages);
        } else {
            return 0;
        }
    }

    public function asArray()
    {
        if (!empty($this->_pages)) {
            reset($this->_pages);
            return $this->_pages;
        } else {
            $result = array();
            while ($page = $this->next()) {
                $result[] = $page;
            }
            return $result;
        }
    }

    /**
     * limit - if empty the pagelist iterator will do nothing.
     * Some backends limit the result set itself (dba, file, flatfile),
     * Some SQL based leave it to WikiDB/PageList - deferred filtering in the iterator.
     */
    public function limit()
    {
        return empty($this->_options['limit']) ? 0 : $this->_options['limit'];
    }

    /**
     * Release resources held by this iterator.
     */
    public function free()
    {
    }
}

/**
 * search baseclass, pcre-specific
 */
class WikiDB_backend_search
{
    public function __construct($search, &$dbh)
    {
        $this->_dbh = $dbh;
        $this->_case_exact = $search->_case_exact;
        $this->_stoplist =& $search->_stoplist;
        $this->stoplisted = array();
    }

    public function _quote($word)
    {
        return preg_quote($word, "/");
    }

    //TODO: use word anchors
    public function EXACT($word)
    {
        return "^" . $this->_quote($word) . "$";
    }

    public function STARTS_WITH($word)
    {
        return "^" . $this->_quote($word);
    }

    public function ENDS_WITH($word)
    {
        return $this->_quote($word) . "$";
    }

    public function WORD($word)
    {
        return $this->_quote($word);
    }

    public function REGEX($word)
    {
        return $word;
    }

    //TESTME
    public function _pagename_match_clause($node)
    {
        $method = $node->op;
        $word = $this->$method($node->word);
        return "preg_match(\"/\".$word.\"/\"" . ($this->_case_exact ? "i" : "") . ")";
    }

    /* Eliminate stoplist words.
     *  Keep a list of Stoplisted words to inform the poor user.
     */
    public function isStoplisted($node)
    {
        // check only on WORD or EXACT fulltext search
        if ($node->op != 'WORD' and $node->op != 'EXACT') {
            return false;
        }
        if (preg_match("/^" . $this->_stoplist . "$/i", $node->word)) {
            array_push($this->stoplisted, $node->word);
            return true;
        }
        return false;
    }
}

/**
 * search baseclass, sql-specific
 */
class WikiDB_backend_search_sql extends WikiDB_backend_search
{
    public function _pagename_match_clause($node)
    {
        // word already quoted by TextSearchQuery_node_word::sql_quote()
        $word = $node->sql();
        if ($word == '%') { // ALL shortcut
            return "1=1";
        } else {
            return ($this->_case_exact
                ? "pagename LIKE '$word'"
                : "LOWER(pagename) LIKE '$word'");
        }
    }

    public function _fulltext_match_clause($node)
    {
        // force word-style %word% for fulltext search
        $dbh = &$this->_dbh;
        $word = strtolower($node->word);
        $word = '%' . $dbh->escapeSimple($word) . '%';
        // eliminate stoplist words
        if ($this->isStoplisted($node)) {
            return "1=1"; // and (pagename or 1) => and 1
        } else {
            return $this->_pagename_match_clause($node)
                // probably convert this MATCH AGAINST or SUBSTR/POSITION without wildcards
                . ($this->_case_exact ? " OR content LIKE '$word'"
                    : " OR LOWER(content) LIKE '$word'");
        }
    }
}
