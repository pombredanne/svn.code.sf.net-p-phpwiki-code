<?php
/**
 * Copyright © 2001-2002 Jeff Dairiki
 * Copyright © 2002 Lawrence Akka
 * Copyright © 2003 Carsten Klapp
 * Copyright © 2002,2004-2010 Reini Urban
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

require_once 'lib/PageType.php';

/**
 * The classes in the file define the interface to the
 * page database.
 *
 * @package WikiDB
 * @author Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Minor enhancements by Reini Urban
 */

/**
 * Force the creation of a new revision.
 * @see WikiDB_Page::createRevision()
 */
if (!defined('WIKIDB_FORCE_CREATE')) {
    define('WIKIDB_FORCE_CREATE', -1);
}

/**
 * Abstract base class for the database used by PhpWiki.
 *
 * A <tt>WikiDB</tt> is a container for <tt>WikiDB_Page</tt>s which in
 * turn contain <tt>WikiDB_PageRevision</tt>s.
 *
 * Conceptually a <tt>WikiDB</tt> contains all possible
 * <tt>WikiDB_Page</tt>s, whether they have been initialized or not.
 * Since all possible pages are already contained in a WikiDB, a call
 * to WikiDB::getPage() will never fail (barring bugs and
 * e.g. filesystem or SQL database problems.)
 *
 * Also each <tt>WikiDB_Page</tt> always contains at least one
 * <tt>WikiDB_PageRevision</tt>: the default content (e.g. "Describe
 * [PageName] here.").  This default content has a version number of
 * zero.
 *
 * <tt>WikiDB_PageRevision</tt>s have read-only semantics. One can
 * only create new revisions or delete old ones --- one cannot modify
 * an existing revision.
 */
class WikiDB
{
    /**
     * @var WikiDB_backend $_backend
     */
    public $_backend;

    /**
     * @see open()
     */
    public function __construct(&$backend, $dbparams)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $this->_backend =& $backend;
        // don't do the following with the auth_dsn!
        if (isset($dbparams['auth_dsn'])) {
            return;
        }

        $this->_cache = new WikiDB_cache($backend);
        if (!empty($request)) {
            $request->_dbi = $this;
        }

        // If the database doesn't yet have a timestamp, initialize it now.
        if ($this->get('_timestamp') === false) {
            $this->touch();
        }

        // devel checking.
        if (DEBUG & _DEBUG_SQL) {
            $this->_backend->check();
        }
        // might be changed when opening the database fails
        $this->readonly = defined("ISREADONLY") ? ISREADONLY : false;
    }

    /**
     * Open a WikiDB database.
     *
     * This function inspects its arguments to determine the proper
     * subclass of WikiDB to instantiate, and then it instantiates it.
     *
     * @param array $dbparams Database configuration parameters (hash).
     * Some pertinent parameters are:
     * <dl>
     * <dt> dbtype
     * <dd> The back-end type.  Current supported types are:
     *   <dl>
     *   <dt> SQL
     *     <dd> Generic SQL backend based on the PEAR/DB database abstraction
     *       library. (More stable and conservative)
     *   <dt> dba
     *     <dd> Dba based backend. The default and by far the fastest.
     *   <dt> file
     *     <dd> flat files
     *   </dl>
     *
     * <dt> dsn
     * <dd> (Used by the SQL backends.)
     *      The DSN specifying which database to connect to.
     *
     * <dt> prefix
     * <dd> Prefix to be prepended to database tables (and file names).
     *
     * <dt> directory
     * <dd> (Used by the dba backend.)
     *      Which directory db files reside in.
     *
     * <dt> timeout
     * <dd> Used only by the dba backend so far.
     *      And: When optimizing mysql it closes timed out mysql processes.
     *      otherwise only used for dba: Timeout in seconds for opening (and
     *      obtaining lock) on the dbm file.
     *
     * <dt> dba_handler
     * <dd> (Used by the dba backend.)
     *
     *      Which dba handler to use.
     *
     * <dt> readonly
     * <dd> Either set by config.ini: ISREADONLY = true or detected automatically
     *      when a database can be read but cannot be updated.
     * </dl>
     *
     * @return WikiDB A WikiDB object.
     **/
    public static function open($dbparams)
    {
        $dbtype = $dbparams['dbtype'];
        include_once("lib/WikiDB/$dbtype.php");

        $class = 'WikiDB_' . $dbtype;
        return new $class($dbparams);
    }

    /**
     * Close database connection.
     *
     * The database may no longer be used after it is closed.
     *
     * Closing a WikiDB invalidates all <tt>WikiDB_Page</tt>s,
     * <tt>WikiDB_PageRevision</tt>s and <tt>WikiDB_PageIterator</tt>s
     * which have been obtained from it.
     */
    public function close()
    {
        $this->_backend->close();
        $this->_cache->close();
    }

    /**
     * Get a WikiDB_Page from a WikiDB.
     *
     * A {@link WikiDB} consists of the (infinite) set of all possible pages,
     * therefore this method never fails.
     *
     * @param  string      $pagename Which page to get.
     * @return WikiDB_Page The requested WikiDB_Page.
     */
    public function getPage($pagename)
    {
        $pagename = (string)$pagename;
        assert($pagename != '');
        return new WikiDB_Page($this, $pagename);
    }

    /**
     * Determine whether page exists (in non-default form).
     *
     * <pre>
     *   $is_page = $dbi->isWikiPage($pagename);
     * </pre>
     * is equivalent to
     * <pre>
     *   $page = $dbi->getPage($pagename);
     *   $current = $page->getCurrentRevision();
     *   $is_page = ! $current->hasDefaultContents();
     * </pre>
     * however isWikiPage may be implemented in a more efficient
     * manner in certain back-ends.
     *
     * @param  string  $pagename string Which page to check.
     * @return boolean True if the page actually exists with
     * non-default contents in the WikiDataBase.
     */
    public function isWikiPage($pagename)
    {
        $page = $this->getPage($pagename);
        return ($page and $page->exists());
    }

    /**
     * Delete page from the WikiDB.
     *
     * Deletes the page from the WikiDB with the possibility to revert and diff.
     * //Also resets all page meta-data to the default values.
     *
     * Note: purgePage() effectively destroys all revisions of the page from the WikiDB.
     *
     * @param string $pagename Name of page to delete.
     * @see purgePage
     * @return mixed
     */
    public function deletePage($pagename)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return false;
        }
        // don't create empty revisions of already purged pages.
        if ($this->_backend->get_latest_version($pagename)) {
            $result = $this->_cache->delete_page($pagename);
        } else {
            $result = -1;
        }

        /* Generate notification emails */
        if (defined('ENABLE_MAILNOTIFY') and ENABLE_MAILNOTIFY) {
            include_once 'lib/MailNotify.php';
            $MailNotify = new MailNotify($pagename);
            $MailNotify->onDeletePage($this, $pagename);
        }

        //How to create a RecentChanges entry with explaining summary? Dynamically
        /*
        $page = $this->getPage($pagename);
        $current = $page->getCurrentRevision();
        $meta = $current->_data;
        $version = $current->getVersion();
        $meta['summary'] = _("removed");
        $page->save($current->getPackedContent(), $version + 1, $meta);
        */
        return $result;
    }

    /**
     * Completely remove the page from the WikiDB, without undo possibility.
     * @param string $pagename Name of page to delete.
     * @return bool
     * @see deletePage
     */
    public function purgePage($pagename)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return false;
        }
        $result = $this->_cache->purge_page($pagename);
        $this->deletePage($pagename); // just for the notification
        return $result;
    }

    /**
     * Retrieve all pages.
     *
     * Gets the set of all pages with non-default contents.
     *
     * @param bool $include_empty Optional. Normally pages whose most
     * recent revision has empty content are considered to be
     * non-existant. Unless $include_defaulted is set to true, those
     * pages will not be returned.
     * @param string $sortby Optional. "+-column,+-column2".
     *        If false the result is faster in natural order.
     * @param string $limit Optional. Encoded as "$offset,$count".
     *         $offset defaults to 0.
     * @param string $exclude  Optional comma-separated list of pagenames.
     *
     * @return WikiDB_PageIterator A WikiDB_PageIterator which contains all pages
     *     in the WikiDB which have non-default contents.
     */
    public function getAllPages($include_empty = false, $sortby = '', $limit = '', $exclude = '')
    {
        $result = $this->_backend->get_all_pages($include_empty, $sortby, $limit, $exclude);
        return new WikiDB_PageIterator(
            $this,
            $result,
            array('include_empty' => $include_empty,
                  'exclude' => $exclude,
                  'limit' => $result->limit())
        );
    }

    /**
     * @param boolean $include_empty If true include also empty pages
     * @param string  $exclude:      comma-separated list of pagenames.
     *             TBD: array of pagenames
     * @return integer
     *
     */
    public function numPages($include_empty = false, $exclude = '')
    {
        if (method_exists($this->_backend, 'numPages')) {
            // FIXME: currently are all args ignored.
            $count = $this->_backend->numPages($include_empty, $exclude);
        } else {
            // FIXME: exclude ignored.
            $iter = $this->getAllPages($include_empty, false, false, $exclude);
            $count = $iter->count();
            $iter->free();
        }
        return (int)$count;
    }

    /**
     * Title search.
     *
     * Search for pages containing (or not containing) certain words
     * in their names.
     *
     * Pages are returned in alphabetical order whenever it is
     * practical to do so.
     * TODO: Sort by ranking. Only postgresql with tsearch2 can do ranking so far.
     *
     * @param TextSearchQuery $search A TextSearchQuery object
     * @param string $sortby Optional. "+-column,+-column2".
     *        If false the result is faster in natural order.
     * @param string $limit Optional. Encoded as "$offset,$count".
     *         $offset defaults to 0.
     * @param  string $exclude Optional comma-separated list of pagenames.
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing the matching pages.
     * @see TextSearchQuery
     */
    public function titleSearch($search, $sortby = 'pagename', $limit = '', $exclude = '')
    {
        $result = $this->_backend->text_search($search, false, $sortby, $limit, $exclude);
        $options = array('exclude' => $exclude,
            'limit' => $limit);
        //if (isset($result->_count)) $options['count'] = $result->_count;
        return new WikiDB_PageIterator($this, $result, $options);
    }

    /**
     * Full text search.
     *
     * Search for pages containing (or not containing) certain words
     * in their entire text (this includes the page content and the
     * page name).
     *
     * Pages are returned in alphabetical order whenever it is
     * practical to do so.
     * TODO: Sort by ranking. Only postgresql with tsearch2 can do ranking so far.
     *
     * @param TextSearchQuery $search A TextSearchQuery object.
     * @param string $sortby Optional. "+-column,+-column2".
     *        If false the result is faster in natural order.
     * @param string $limit Optional. Encoded as "$offset,$count".
     *         $offset defaults to 0.
     * @param  string $exclude Optional comma-separated list of pagenames.
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing the matching pages.
     * @see TextSearchQuery
     */
    public function fullSearch($search, $sortby = 'pagename', $limit = '', $exclude = '')
    {
        $result = $this->_backend->text_search($search, true, $sortby, $limit, $exclude);
        return new WikiDB_PageIterator(
            $this,
            $result,
            array('exclude' => $exclude,
                'limit' => $limit,
                'stoplisted' => $result->stoplisted
            )
        );
    }

    /**
     * Find the pages with the greatest hit counts.
     *
     * Pages are returned in reverse order by hit count.
     *
     * @param int $limit The maximum number of pages to return.
     * Set $limit to zero to return all pages.  If $limit < 0, pages will
     * be sorted in decreasing order of popularity.
     * @param string $sortby Optional. "+-column,+-column2".
     *        If false the result is faster in natural order.
     *
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing the matching
     * pages.
     */
    public function mostPopular($limit = 20, $sortby = '-hits')
    {
        $result = $this->_backend->most_popular($limit, $sortby);
        return new WikiDB_PageIterator($this, $result);
    }

    /**
     * Find recent page revisions.
     *
     * Revisions are returned in reverse order by creation time.
     *
     * @param array $params This hash is used to specify various optional
     *   parameters:
     * <dl>
     * <dt> limit
     *    <dd> (integer) At most this many revisions will be returned.
     * <dt> since
     *    <dd> (integer) Only revisions since this time (unix-timestamp)
     *        will be returned.
     * <dt> include_minor_revisions
     *    <dd> (boolean) Also include minor revisions.  (Default is not to.)
     * <dt> exclude_major_revisions
     *    <dd> (boolean) Don't include non-minor revisions.
     *         (Exclude_major_revisions implies include_minor_revisions.)
     * <dt> include_all_revisions
     *    <dd> (boolean) Return all matching revisions for each page.
     *         Normally only the most recent matching revision is returned
     *         for each page.
     * </dl>
     *
     * @return WikiDB_PageRevisionIterator A WikiDB_PageRevisionIterator
     * containing the matching revisions.
     */
    public function mostRecent($params = array())
    {
        $result = $this->_backend->most_recent($params);
        return new WikiDB_PageRevisionIterator($this, $result);
    }

    /**
     * @param string $exclude
     * @param string $sortby Optional. "+-column,+-column2".
     *        If empty string the result is faster in natural order.
     * @param string $limit Optional. Encoded as "$offset,$count".
     *         $offset defaults to 0.
     * @return WikiDB_backend_dumb_WantedPagesIter A generic iterator containing rows of
     *         (duplicate) pagename, wantedfrom.
     */
    public function wantedPages($exclude = '', $sortby = '', $limit = '')
    {
        return $this->_backend->wanted_pages($exclude, $sortby, $limit);
    }

    /**
     * Generic interface to the link table. Esp. useful to search for rdf triples as in
     * SemanticSearch and ListRelations.
     *
     * @param object $pages   A TextSearchQuery object.
     * @param object $search  A TextSearchQuery object.
     * @param string $linktype One of "linkto", "linkfrom", "relation", "attribute".
     *   linktype parameter:
     * <dl>
     * <dt> "linkto"
     *    <dd> search for simple out-links
     * <dt> "linkfrom"
     *    <dd> in-links, i.e BackLinks
     * <dt> "relation"
     *    <dd> the first part in a <>::<> link
     * <dt> "attribute"
     *    <dd> the first part in a <>:=<> link
     * </dl>
     * @param mixed $relation An optional TextSearchQuery to match the
     * relation name. Ignored on simple in-out links.
     *
     * @return Iterator A generic iterator containing links to pages or values.
     *                  hash of "pagename", "linkname", "linkvalue.
     */
    public function linkSearch($pages, $search, $linktype, $relation = false)
    {
        return $this->_backend->link_search($pages, $search, $linktype, $relation);
    }

    /**
     * Return a simple list of all defined relations (and attributes), mainly
     * for the SemanticSearch autocompletion.
     *
     * @param bool $also_attributes
     * @param bool $only_attributes
     * @param bool $sorted
     * @return array of strings
     */
    public function listRelations($also_attributes = false, $only_attributes = false, $sorted = true)
    {
        if (method_exists($this->_backend, "list_relations")) {
            return $this->_backend->list_relations($also_attributes, $only_attributes, $sorted);
        }
        // dumb, slow fallback. no iter, so simply define it here.
        $relations = array();
        $iter = $this->getAllPages();
        while ($page = $iter->next()) {
            $reliter = $page->getRelations();
            $names = array();
            while ($rel = $reliter->next()) {
                // if there's no pagename it's an attribute
                $names[] = $rel->getName();
            }
            $relations = array_merge($relations, $names);
            $reliter->free();
        }
        $iter->free();
        if ($sorted) {
            sort($relations);
            reset($relations);
        }
        return $relations;
    }

    /**
     * Call the appropriate backend method.
     *
     * @param  string  $from            Page to rename
     * @param  string  $to              New name
     * @param  boolean $updateWikiLinks If the text in all pages should be replaced.
     * @return boolean true or false
     */
    public function renamePage($from, $to, $updateWikiLinks = false)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return false;
        }
        assert(is_string($from) && $from != '');
        assert(is_string($to) && $to != '');
        $result = false;
        $oldpage = $this->getPage($from);
        $newpage = $this->getPage($to);
        //update all WikiLinks in existing pages
        //non-atomic! i.e. if rename fails the links are not undone
        if ($updateWikiLinks) {
            $lookbehind = "/(?<=[\W:])\Q";
            $lookahead = "\E(?=[\W:])/";
            require_once 'lib/plugin/WikiAdminSearchReplace.php';
            $links = $oldpage->getBackLinks();
            while ($linked_page = $links->next()) {
                WikiPlugin_WikiAdminSearchReplace::replaceHelper(
                        $this,
                        $linked_page->getName(),
                        $lookbehind . $from . $lookahead,
                        $to,
                        true
                    );
            }
            // FIXME: Disabled to avoid recursive modification when renaming
            // a page like 'PageFoo to 'PageFooTwo'
            if (0) {
                $links = $newpage->getBackLinks();
                while ($linked_page = $links->next()) {
                    WikiPlugin_WikiAdminSearchReplace::replaceHelper(
                        $this,
                        $linked_page->getName(),
                        $lookbehind . $from . $lookahead,
                        $to,
                        true
                    );
                }
            }
        }
        $result = $this->_backend->rename_page($from, $to);
        /* Generate notification emails? */
        if ($result and ENABLE_MAILNOTIFY) {
            $notify = $this->get('notify');
            if (!empty($notify) and is_array($notify)) {
                include_once 'lib/MailNotify.php';
                $MailNotify = new MailNotify($from);
                $MailNotify->onRenamePage($this, $from, $to);
            }
        }
        return $result;
    }

    /** Get timestamp when database was last modified.
     *
     * @return string A string consisting of two integers,
     * separated by a space.  The first is the time in
     * unix timestamp format, the second is a modification
     * count for the database.
     *
     * The idea is that you can cast the return value to an
     * int to get a timestamp, or you can use the string value
     * as a good hash for the entire database.
     */
    public function getTimestamp()
    {
        $ts = $this->get('_timestamp');
        return sprintf("%d %d", $ts[0], $ts[1]);
    }

    /**
     * Update the database timestamp.
     */
    public function touch()
    {
        $ts = $this->get('_timestamp');
        $this->set('_timestamp', array(time(), $ts[1] + 1));
    }

    /**
     * Access WikiDB global meta-data.
     *
     * NOTE: this is currently implemented in a hackish and
     * not very efficient manner.
     *
     * @param string $key Which meta data to get.
     * Some reserved meta-data keys are:
     * <dl>
     * <dt>'_timestamp' <dd> Data used by getTimestamp().
     * </dl>
     *
     * @return mixed The requested value, or false if the requested data
     * is not set.
     */
    public function get($key)
    {
        if (!$key || $key[0] == '%') {
            return false;
        }
        /*
         * Hack Alert: We can use any page (existing or not) to store
         * this data (as long as we always use the same one.)
         */
        $gd = $this->getPage('global_data');
        $data = $gd->get('__global');

        if ($data && isset($data[$key])) {
            return $data[$key];
        } else {
            return false;
        }
    }

    /**
     * Set global meta-data.
     *
     * NOTE: this is currently implemented in a hackish and
     * not very efficient manner.
     *
     * @see get
     *
     * @param string $key    Meta-data key to set.
     * @param string $newval New value.
     */
    public function set($key, $newval)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }
        if (!$key || $key[0] == '%') {
            return;
        }

        $gd = $this->getPage('global_data');
        $data = $gd->get('__global');
        if ($data === false) {
            $data = array();
        }

        if (empty($newval)) {
            unset($data[$key]);
        } else {
            $data[$key] = $newval;
        }

        $gd->set('__global', $data);
    }

    /* TODO: these are really backend methods */

    // SQL result: for simple select or create/update queries
    // returns the database specific resource type
    public function genericSqlQuery($sql, $args = false)
    {
        echo "<pre>";
        printSimpleTrace(debug_backtrace());
        echo "</pre>\n";
        trigger_error("no SQL database", E_USER_ERROR);
        return false;
    }

    // SQL iter: for simple select or create/update queries
    // returns the generic iterator object (count,next)
    public function genericSqlIter($sql, $field_list = null)
    {
        echo "<pre>";
        printSimpleTrace(debug_backtrace());
        echo "</pre>\n";
        trigger_error("no SQL database", E_USER_ERROR);
        return false;
    }

    // see backend upstream methods
    public function quote($s)
    {
        return $s;
    }

    public function isOpen()
    {
        return false; /* so far only needed for sql so false it.
                         later we have to check dba also */
    }

    public function getParam($param)
    {
        global $DBParams;
        if (isset($DBParams[$param])) {
            return $DBParams[$param];
        } elseif ($param == 'prefix') {
            return '';
        } else {
            return false;
        }
    }

    public function getAuthParam($param)
    {
        global $DBAuthParams;
        if (isset($DBAuthParams[$param])) {
            return $DBAuthParams[$param];
        } elseif ($param == 'USER_AUTH_ORDER') {
            return $GLOBALS['USER_AUTH_ORDER'];
        } elseif ($param == 'USER_AUTH_POLICY') {
            return $GLOBALS['USER_AUTH_POLICY'];
        } else {
            return array();
        }
    }
}

/**
 * A base class which representing a wiki-page within a
 * WikiDB.
 *
 * A WikiDB_Page contains a number (at least one) of
 * WikiDB_PageRevisions.
 */
class WikiDB_Page
{
    public $score;
    public $_wikidb;
    public $_pagename;

    public function __construct(&$wikidb, $pagename)
    {
        $this->_wikidb = &$wikidb;
        $this->_pagename = $pagename;
    }

    /**
     * Get the name of the wiki page.
     *
     * @return string The page name.
     */
    public function getName()
    {
        return $this->_pagename;
    }

    // To reduce the memory footprint for larger sets of pagelists,
    // we don't cache the content (only true or false) and
    // we purge the pagedata (_cached_html) also
    public function exists()
    {
        if (isset($this->_wikidb->_cache->_id_cache[$this->_pagename])) {
            return true;
        }
        $current = $this->getCurrentRevision(false);
        if (!$current) {
            return false;
        }
        return !$current->hasDefaultContents();
    }

    public function getVersion()
    {
        $backend = &$this->_wikidb->_backend;
        $pagename = &$this->_pagename;
        return $backend->get_latest_version($pagename);
    }

    /**
     * Delete an old revision of a WikiDB_Page.
     *
     * Deletes the specified revision of the page.
     * It is a fatal error to attempt to delete the current revision.
     *
     * @param integer $version Which revision to delete.  (You can also
     *  use a WikiDB_PageRevision object here.)
     */
    public function deleteRevision($version)
    {
        if ($this->_wikidb->readonly) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }
        $backend = &$this->_wikidb->_backend;
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;

        $version = $this->_coerce_to_version($version);
        if ($version == 0) {
            return;
        }

        $backend->lock(array('page', 'version'));
        $latestversion = $cache->get_latest_version($pagename);
        if ($latestversion && ($version == $latestversion)) {
            $backend->unlock(array('page', 'version'));
            trigger_error(sprintf(
                "Attempt to delete most recent revision of “%s”",
                $pagename
            ), E_USER_ERROR);
            return;
        }

        $cache->delete_versiondata($pagename, $version);
        $backend->unlock(array('page', 'version'));
    }

    /*
     * Delete a revision, or possibly merge it with a previous
     * revision.
     *
     * The idea is this:
     * Suppose an author make a (major) edit to a page.  Shortly
     * after that the same author makes a minor edit (e.g. to fix
     * spelling mistakes he just made.)
     *
     * Now some time later, where cleaning out old saved revisions,
     * and would like to delete his minor revision (since there's
     * really no point in keeping minor revisions around for a long
     * time.)
     *
     * Note that the text after the minor revision probably represents
     * what the author intended to write better than the text after
     * the preceding major edit.
     *
     * So what we really want to do is merge the minor edit with the
     * preceding edit.
     *
     * We will only do this when:
     * <ul>
     * <li>The revision being deleted is a minor one, and
     * <li>It has the same author as the immediately preceding revision.
     * </ul>
     */
    public function mergeRevision($version)
    {
        if ($this->_wikidb->readonly) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }
        $backend = &$this->_wikidb->_backend;
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;

        $version = $this->_coerce_to_version($version);
        if ($version == 0) {
            return;
        }

        $backend->lock(array('version'));
        $latestversion = $cache->get_latest_version($pagename);
        if ($latestversion && $version == $latestversion) {
            $backend->unlock(array('version'));
            trigger_error(sprintf(
                "Attempt to merge most recent revision of “%s”",
                $pagename
            ), E_USER_ERROR);
            return;
        }

        $versiondata = $cache->get_versiondata($pagename, $version, true);
        if (!$versiondata) {
            // Not there? ... we're done!
            $backend->unlock(array('version'));
            return;
        }

        if ($versiondata['is_minor_edit']) {
            $previous = $backend->get_previous_version($pagename, $version);
            if ($previous) {
                $prevdata = $cache->get_versiondata($pagename, $previous);
                if ($prevdata['author_id'] == $versiondata['author_id']) {
                    // This is a minor revision, previous version is
                    // by the same author. We will merge the
                    // revisions.
                    $cache->update_versiondata(
                        $pagename,
                        $previous,
                        array('%content' => $versiondata['%content'],
                            '_supplanted' => $versiondata['_supplanted'])
                    );
                }
            }
        }

        $cache->delete_versiondata($pagename, $version);
        $backend->unlock(array('version'));
    }

    /**
     * Create a new revision of a {@link WikiDB_Page}.
     *
     * @param int $version Version number for new revision.
     * To ensure proper serialization of edits, $version must be
     * exactly one higher than the current latest version.
     * (You can defeat this check by setting $version to
     * {@link WIKIDB_FORCE_CREATE} --- not usually recommended.)
     *
     * @param string $content Contents of new revision.
     *
     * @param array $metadata Metadata for new revision (hash).
     * All values in the hash should be scalars (strings or integers).
     *
     * @param array $links List of linkto=>pagename, relation=>pagename which this page links to (hash).
     *
     * @return false|WikiDB_PageRevision Returns the new WikiDB_PageRevision object. If
     * $version was incorrect, returns false
     */
    public function createRevision($version, &$content, $metadata, $links)
    {
        if ($this->_wikidb->readonly) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return false;
        }
        $backend = &$this->_wikidb->_backend;
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;
        $cache->invalidate_cache($pagename);

        $backend->lock(array('version', 'page', 'recent', 'link', 'nonempty'));

        $latestversion = $backend->get_latest_version($pagename);
        $newversion = ($latestversion ? $latestversion : 0) + 1;
        assert($newversion >= 1);

        if ($version != WIKIDB_FORCE_CREATE and $version != $newversion) {
            $backend->unlock(array('version', 'page', 'recent', 'link', 'nonempty'));
            return false;
        }

        $data = $metadata;

        foreach ($data as $key => $val) {
            if (empty($val) || $key[0] == '_' || $key[0] == '%') {
                unset($data[$key]);
            }
        }

        assert(!empty($data['author']));
        if (empty($data['author_id'])) {
            @$data['author_id'] = $data['author'];
        }

        if (empty($data['mtime'])) {
            $data['mtime'] = time();
        }

        if ($latestversion and $version != WIKIDB_FORCE_CREATE) {
            // Ensure mtimes are monotonic.
            $pdata = $cache->get_versiondata($pagename, $latestversion);
            if ($data['mtime'] < $pdata['mtime']) {
                trigger_error(sprintf(
                    _("%s: Date of new revision is %s"),
                    $pagename,
                    "'non-monotonic'"
                ));
                $data['orig_mtime'] = $data['mtime'];
                $data['mtime'] = $pdata['mtime'];
            }

            // FIXME: use (possibly user specified) 'mtime' time or
            // time()?
            $cache->update_versiondata(
                $pagename,
                $latestversion,
                array('_supplanted' => $data['mtime'])
            );
        }

        $data['%content'] = &$content;

        $cache->set_versiondata($pagename, $newversion, $data);

        //$cache->update_pagedata($pagename, array(':latestversion' => $newversion,
        //':deleted' => empty($content)));

        $backend->set_links($pagename, $links);

        $backend->unlock(array('version', 'page', 'recent', 'link', 'nonempty'));

        return new WikiDB_PageRevision($this->_wikidb, $pagename, $newversion, $data);
    }

    /** A higher-level interface to createRevision.
     *
     * This takes care of computing the links, and storing
     * a cached version of the transformed wiki-text.
     *
     * @param string $wikitext The page content.
     *
     * @param int $version Version number for new revision.
     * To ensure proper serialization of edits, $version must be
     * exactly one higher than the current latest version.
     * (You can defeat this check by setting $version to
     * {@link WIKIDB_FORCE_CREATE} --- not usually recommended.)
     *
     * @param array $meta Meta-data for new revision.
     *
     * @param mixed $formatted
     *
     * @return mixed
     */
    public function save($wikitext, $version, $meta, $formatted = null)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if ($this->_wikidb->readonly) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return false;
        }
        if (is_null($formatted)) {
            $formatted = new TransformedText($this, $wikitext, $meta);
        }
        $type = $formatted->getType();
        $meta['pagetype'] = $type->getName();
        $links = $formatted->getWikiPageLinks(); // linkto => relation
        $attributes = array();
        foreach ($links as $link) {
            if ($link['linkto'] === "" and !empty($link['relation'])) {
                $attributes[$link['relation']] = $this->getAttribute($link['relation']);
            }
        }
        $meta['attribute'] = $attributes;

        $backend = &$this->_wikidb->_backend;
        $newrevision = $this->createRevision($version, $wikitext, $meta, $links);
        if ($newrevision and !WIKIDB_NOCACHE_MARKUP) {
            $this->set('_cached_html', $formatted->pack());
        }

        // FIXME: probably should have some global state information
        // in the backend to control when to optimize.
        //
        // We're doing this here rather than in createRevision because
        // postgresql can't optimize while locked.
        if ((DEBUG & _DEBUG_SQL)
            or (DATABASE_OPTIMISE_FREQUENCY > 0 and
                (time() % DATABASE_OPTIMISE_FREQUENCY == 0))
        ) {
            $backend->optimize();
        }

        /* Generate notification emails? */
        if (ENABLE_MAILNOTIFY and is_a($newrevision, 'WikiDB_PageRevision')) {
            // Save didn't fail because of concurrent updates.
            $notify = $this->_wikidb->get('notify');
            if (!empty($notify)
                and is_array($notify)
            ) {
                include_once 'lib/MailNotify.php';
                $MailNotify = new MailNotify($newrevision->getName());
                $MailNotify->onChangePage($this->_wikidb, $wikitext, $version, $meta);
            }
            $newrevision->_transformedContent = $formatted;
        }
        // more pagechange callbacks: (in a hackish manner for now)
        if (ENABLE_RECENTCHANGESBOX
            and empty($meta['is_minor_edit'])
                and !in_array(
                    $request->getArg('action'),
                    array('loadfile', 'upgrade')
                )
        ) {
            require_once 'lib/WikiPlugin.php';
            $w = new WikiPluginLoader();
            $p = $w->getPlugin("RecentChanges");
            $p->box_update(false, $request, $this->_pagename);
        }
        return $newrevision;
    }

    /**
     * Get the most recent revision of a page.
     *
     * @param bool $need_content
     * @return WikiDB_PageRevision The current WikiDB_PageRevision object.
     */
    public function getCurrentRevision($need_content = true)
    {
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;

        // Prevent deadlock in case of memory exhausted errors
        // Pure selection doesn't really need locking here.
        //   sf.net bug#927395
        // I know it would be better to lock, but with lots of pages this deadlock is more
        // severe than occasionally get not the latest revision.
        // In spirit to wikiwiki: read fast, edit slower.
        //$backend->lock();
        $version = $cache->get_latest_version($pagename);
        // getRevision gets the content also!
        $revision = $this->getRevision($version, $need_content);
        //$backend->unlock();
        assert($revision);
        return $revision;
    }

    /**
     * Get a specific revision of a WikiDB_Page.
     *
     * @param int $version Which revision to get.
     * @param bool $need_content
     * @return WikiDB_PageRevision The requested WikiDB_PageRevision object, or
     * false if the requested revision does not exist in the {@link WikiDB}.
     * Note that version zero of any page always exists.
     */
    public function getRevision($version, $need_content = true)
    {
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;

        if ((!$version) or ($version == 0) or ($version == -1)) { // 0 or false
            return new WikiDB_PageRevision($this->_wikidb, $pagename, 0);
        }

        assert($version > 0);
        $vdata = $cache->get_versiondata($pagename, $version, $need_content);
        if (!$vdata) {
            return new WikiDB_PageRevision($this->_wikidb, $pagename, 0);
        }
        return new WikiDB_PageRevision(
            $this->_wikidb,
            $pagename,
            $version,
            $vdata
        );
    }

    /**
     * Get previous page revision.
     *
     * This method find the most recent revision before a specified
     * version.
     *
     * @param bool|int|WikiDB_PageRevision $version Find most recent revision before this version.
     *
     * @param bool $need_content
     *
     * @return WikiDB_PageRevision|bool The requested WikiDB_PageRevision object, or false if the
     * requested revision does not exist in the {@link WikiDB}.  Note that
     * unless $version is greater than zero, a revision (perhaps version zero,
     * the default revision) will always be found.
     */
    public function getRevisionBefore($version = false, $need_content = true)
    {
        $backend = &$this->_wikidb->_backend;
        $pagename = &$this->_pagename;
        if ($version === false) {
            $version = $this->_wikidb->_cache->get_latest_version($pagename);
        } else {
            $version = $this->_coerce_to_version($version);
        }

        if ($version == 0) {
            return false;
        }
        //$backend->lock();
        $previous = $backend->get_previous_version($pagename, $version);
        $revision = $this->getRevision($previous, $need_content);
        //$backend->unlock();
        assert($revision);
        return $revision;
    }

    /**
     * Get all revisions of the WikiDB_Page.
     *
     * This does not include the version zero (default) revision in the
     * returned revision set.
     *
     * @return WikiDB_PageRevisionIterator A
     *   WikiDB_PageRevisionIterator containing all revisions of this
     *   WikiDB_Page in reverse order by version number.
     */
    public function getAllRevisions()
    {
        $backend = &$this->_wikidb->_backend;
        $revs = $backend->get_all_revisions($this->_pagename);
        return new WikiDB_PageRevisionIterator($this->_wikidb, $revs);
    }

    /**
     * Find pages which link to or are linked from a page.
     * relations: $backend->get_links is responsible to add the relation to the pagehash
     * as 'linkrelation' key as pagename. See WikiDB_PageIterator::next
     *   if (isset($next['linkrelation']))
     *
     * @param bool $reversed Which links to find: true for backlinks (default).
     * @param bool $include_empty
     * @param string $sortby
     * @param string $limit
     * @param string $exclude
     * @param bool $want_relations
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing
     * all matching pages.
     */
    public function getLinks(
        $reversed = true,
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = '',
        $want_relations = false
    )
    {
        $backend = &$this->_wikidb->_backend;
        $result = $backend->get_links(
            $this->_pagename,
            $reversed,
            $include_empty,
            $sortby,
            $limit,
            $exclude,
            $want_relations
        );
        return new WikiDB_PageIterator(
            $this->_wikidb,
            $result,
            array('include_empty' => $include_empty,
                'sortby' => $sortby,
                'limit' => $limit,
                'exclude' => $exclude,
                'want_relations' => $want_relations)
        );
    }

    /**
     * All Links from other pages to this page.
     */
    public function getBackLinks($include_empty = false, $sortby = '', $limit = '', $exclude = '')
    {
        return $this->getLinks(true, $include_empty, $sortby, $limit, $exclude);
    }

    /**
     * Forward Links: All Links from this page to other pages.
     */
    public function getPageLinks($include_empty = false, $sortby = '', $limit = '', $exclude = '')
    {
        return $this->getLinks(false, $include_empty, $sortby, $limit, $exclude);
    }

    /**
     * Relations: All links from this page to other pages with relation <> 0.
     * is_a:=page or population:=number
     */
    public function getRelations($sortby = '', $limit = '', $exclude = '')
    {
        $backend = &$this->_wikidb->_backend;
        $result = $backend->get_links(
            $this->_pagename,
            false,
            true,
            $sortby,
            $limit,
            $exclude,
            true
        );
        // we do not care for the linked page versiondata, just the pagename and linkrelation
        return new WikiDB_PageIterator(
            $this->_wikidb,
            $result,
            array('include_empty' => true,
                'sortby' => $sortby,
                'limit' => $limit,
                'exclude' => $exclude,
                'want_relations' => true)
        );
    }

    /**
     * possibly faster link existance check. not yet accelerated.
     */
    public function existLink($link, $reversed = false)
    {
        $backend = &$this->_wikidb->_backend;
        if (method_exists($backend, 'exists_link')) {
            return $backend->exists_link($this->_pagename, $link, $reversed);
        }
        //$cache = &$this->_wikidb->_cache;
        // TODO: check cache if it is possible
        $iter = $this->getLinks($reversed);
        while ($page = $iter->next()) {
            if ($page->getName() == $link) {
                return $page;
            }
        }
        $iter->free();
        return false;
    }

    /* Semantic relations are links with the relation pointing to another page,
       the so-called "RDF Triple".
       [San Diego] is%20a::city
       => "At the page San Diego there is a relation link of 'is a' to the page 'city'."
     */

    /* Semantic attributes for a page.
       [San Diego] population:=1,305,736
       Attributes are links with the relation pointing to another page.
    */

    /**
     * Access WikiDB_Page non version-specific meta-data.
     *
     * @param string $key Which meta data to get.
     * Some reserved meta-data keys are:
     * <dl>
     * <dt>'date'  <dd> Created as unixtime
     * <dt>'locked'<dd> Is page locked? 'yes' or 'no'
     * <dt>'hits'  <dd> Page hit counter.
     * <dt>'_cached_html' <dd> Transformed CachedMarkup object, serialized + optionally gzipped.
     *                         In SQL stored now in an extra column.
     * Optional data:
     * <dt>'pref'  <dd> Users preferences, stored only in homepages.
     * <dt>'owner' <dd> Default: first author_id. We might add a group with a dot here:
     *                  E.g. "owner.users"
     * <dt>'perm'  <dd> Permission flag to authorize read/write/execution of
     *                  page-headers and content.
    + <dt>'moderation'<dd> ModeratedPage data. Handled by plugin/ModeratedPage
     * <dt>'rating' <dd> Page rating. Handled by plugin/RateIt
     * </dl>
     *
     * @return mixed The requested value, or false if the requested data
     * is not set.
     */
    public function get($key)
    {
        $cache = &$this->_wikidb->_cache;
        $backend = &$this->_wikidb->_backend;
        if (!$key || $key[0] == '%') {
            return false;
        }
        // several new SQL backends optimize this.
        if (!WIKIDB_NOCACHE_MARKUP
            and $key == '_cached_html'
                and method_exists($backend, 'get_cached_html')
        ) {
            return $backend->get_cached_html($this->_pagename);
        }
        $data = $cache->get_pagedata($this->_pagename);
        return isset($data[$key]) ? $data[$key] : false;
    }

    /**
     * Get all the page meta-data as a hash.
     *
     * @return array The page meta-data (hash).
     */
    public function getMetaData()
    {
        $cache = &$this->_wikidb->_cache;
        $data = $cache->get_pagedata($this->_pagename);
        $meta = array();
        foreach ($data as $key => $val) {
            if ( /*!empty($val) &&*/
                $key[0] != '%'
            ) {
                $meta[$key] = $val;
            }
        }
        return $meta;
    }

    /**
     * Set page meta-data.
     *
     * @see get
     *
     * @param string $key    Meta-data key to set.
     * @param string $newval New value.
     */
    public function set($key, $newval)
    {
        $cache = &$this->_wikidb->_cache;
        $backend = &$this->_wikidb->_backend;
        $pagename = &$this->_pagename;

        assert($key && $key[0] != '%');

        // several new SQL backends optimize this.
        if (!WIKIDB_NOCACHE_MARKUP
            and $key == '_cached_html'
                and method_exists($backend, 'set_cached_html')
        ) {
            if ($this->_wikidb->readonly) {
                if (DEBUG) {
                    trigger_error("readonly database", E_USER_WARNING);
                }
                return;
            }
            $backend->set_cached_html($pagename, $newval);
            return;
        }

        $data = $cache->get_pagedata($pagename);

        if (!empty($newval)) {
            if (!empty($data[$key]) && $data[$key] == $newval) {
                return;
            } // values identical, skip update.
        } else {
            if (empty($data[$key])) {
                return;
            } // values identical, skip update.
        }

        if (isset($this->_wikidb->readonly) and ($this->_wikidb->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }
        $cache->update_pagedata($pagename, array($key => $newval));
    }

    /**
     * Increase page hit count.
     *
     * FIXME: IS this needed?  Probably not.
     *
     * This is a convenience function.
     * <pre> $page->increaseHitCount(); </pre>
     * is functionally identical to
     * <pre> $page->set('hits',$page->get('hits')+1); </pre>
     * but less expensive (ignores the pagadata string)
     *
     * Note that this method may be implemented in more efficient ways
     * in certain backends.
     */
    public function increaseHitCount()
    {
        if ($this->_wikidb->readonly) {
            if (DEBUG) {
                trigger_error("readonly database");
            }
            return;
        }
        if (method_exists($this->_wikidb->_backend, 'increaseHitCount')) {
            $this->_wikidb->_backend->increaseHitCount($this->_pagename);
        } else {
            @$newhits = $this->get('hits') + 1;
            $this->set('hits', $newhits);
        }
    }

    /**
     * Return a string representation of the WikiDB_Page
     *
     * This is really only for debugging.
     *
     * @return string Printable representation of the WikiDB_Page.
     */
    public function asString()
    {
        ob_start();
        printf("[%s:%s\n", get_class($this), $this->getName());
        print_r($this->getMetaData());
        echo "]\n";
        $strval = ob_get_contents();
        ob_end_clean();
        return $strval;
    }

    /**
     * @param int|WikiDB_PageRevision $version_or_pagerevision
     * Takes either the version number (an int) or a WikiDB_PageRevision object.
     * @return integer The version number.
     */
    private function _coerce_to_version($version_or_pagerevision)
    {
        if (is_int($version_or_pagerevision)) {
            return $version_or_pagerevision;
        }
        if (method_exists($version_or_pagerevision, "getContent")) {
            $version = $version_or_pagerevision->getVersion();
        } else {
            $version = (int)$version_or_pagerevision;
        }

        assert($version >= 0);
        return $version;
    }

    public function isUserPage($include_empty = true)
    {
        if (!$include_empty and !$this->exists()) {
            return false;
        }
        return $this->get('pref') ? true : false;
    }

    // May be empty. Either the stored owner (/Chown), or the first authorized author
    public function getOwner()
    {
        if ($owner = $this->get('owner')) {
            return $owner;
        }
        // check all revisions forwards for the first author_id
        $backend = &$this->_wikidb->_backend;
        $pagename = &$this->_pagename;
        $latestversion = $backend->get_latest_version($pagename);
        for ($v = 1; $v <= $latestversion; $v++) {
            $rev = $this->getRevision($v, false);
            if ($rev and $owner = $rev->get('author_id')) {
                return $owner;
            }
        }
        return '';
    }

    // The authenticated author of the first revision or empty if not authenticated then.
    public function getCreator()
    {
        if ($current = $this->getRevision(1, false)) {
            return $current->get('author_id');
        } else {
            return '';
        }
    }

    // The authenticated author of the current revision.
    public function getAuthor()
    {
        if ($current = $this->getCurrentRevision(false)) {
            return $current->get('author_id');
        } else {
            return '';
        }
    }

    /* Semantic Web value, not stored in the links.
     * todo: unify with some unit knowledge
     */
    public function setAttribute($relation, $value)
    {
        $attr = $this->get('attributes');
        if (empty($attr)) {
            $attr = array($relation => $value);
        } else {
            $attr[$relation] = $value;
        }
        $this->set('attributes', $attr);
    }

    public function getAttribute($relation)
    {
        $meta = $this->get('attributes');
        if (empty($meta)) {
            return '';
        } else {
            return $meta[$relation];
        }
    }
}

/**
 * This class represents a specific revision of a WikiDB_Page within
 * a WikiDB.
 *
 * A WikiDB_PageRevision has read-only semantics. You may only create
 * new revisions (and delete old ones) --- you cannot modify existing
 * revisions.
 */
class WikiDB_PageRevision
{
    public $_wikidb;
    public $_pagename;
    public $_version;
    public $_data;
    public $_transformedContent = false; // set by WikiDB_Page::save()

    public function __construct(&$wikidb, $pagename, $version, $versiondata = array())
    {
        $this->_wikidb = &$wikidb;
        $this->_pagename = $pagename;
        $this->_version = $version;
        $this->_data = $versiondata ? $versiondata : array();
        $this->_transformedContent = false; // set by WikiDB_Page::save()
    }

    /**
     * Get the WikiDB_Page which this revision belongs to.
     *
     * @return WikiDB_Page The WikiDB_Page which this revision belongs to.
     */
    public function getPage()
    {
        return new WikiDB_Page($this->_wikidb, $this->_pagename);
    }

    /**
     * Get the version number of this revision.
     *
     * @return int The version number of this revision.
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * Determine whether this revision has defaulted content.
     *
     * The default revision (version 0) of each page, as well as any
     * pages which are created with empty content have their content
     * defaulted to something like:
     * <pre>
     *   Describe [ThisPage] here.
     * </pre>
     *
     * @return boolean Returns true if the page has default content.
     */
    public function hasDefaultContents()
    {
        $data = &$this->_data;
        if (!isset($data['%content'])) {
            return true;
        }
        if ($data['%content'] === true) {
            return false;
        }
        return $data['%content'] === false or $data['%content'] === "";
    }

    /**
     * Get the content as an array of lines.
     *
     * @return array An array of lines.
     * The lines should contain no trailing white space.
     */
    public function getContent()
    {
        return explode("\n", $this->getPackedContent());
    }

    /**
     * Get the pagename of the revision.
     *
     * @return string pagename.
     */
    public function getPageName()
    {
        return $this->_pagename;
    }

    public function getName()
    {
        return $this->_pagename;
    }

    /**
     * Determine whether revision is the latest.
     *
     * @return boolean True iff the revision is the latest (most recent) one.
     */
    public function isCurrent()
    {
        if (!isset($this->_iscurrent)) {
            $page = $this->getPage();
            $current = $page->getCurrentRevision(false);
            $this->_iscurrent = $this->getVersion() == $current->getVersion();
        }
        return $this->_iscurrent;
    }

    /**
     * Get the transformed content of a page.
     *
     * @param bool $pagetype_override
     * @return object An XmlContent-like object containing the page transformed
     * contents.
     */
    public function getTransformedContent($pagetype_override = false)
    {
        if ($pagetype_override) {
            // Figure out the normal page-type for this page.
            $type = PageType::GetPageType($this->get('pagetype'));
            if ($type->getName() == $pagetype_override) {
                $pagetype_override = false;
            } // Not really an override...
        }

        if ($pagetype_override) {
            // Overriden page type, don't cache (or check cache).
            return new TransformedText(
                $this->getPage(),
                $this->getPackedContent(),
                $this->getMetaData(),
                $pagetype_override
            );
        }

        $possibly_cache_results = true;

        if (WIKIDB_NOCACHE_MARKUP) {
            if (WIKIDB_NOCACHE_MARKUP == 'purge') {
                // flush cache for this page.
                $page = $this->getPage();
                $page->set('_cached_html', '');
            }
            $possibly_cache_results = false;
        } elseif (!$this->_transformedContent) {
            //$backend->lock();
            if ($this->isCurrent()) {
                $page = $this->getPage();
                $this->_transformedContent = TransformedText::unpack($page->get('_cached_html'));
            } else {
                $possibly_cache_results = false;
            }
            //$backend->unlock();
        }

        if (!$this->_transformedContent) {
            $this->_transformedContent
                = new TransformedText(
                    $this->getPage(),
                    $this->getPackedContent(),
                    $this->getMetaData()
                );

            if ($possibly_cache_results and !WIKIDB_NOCACHE_MARKUP) {
                // If we're still the current version, cache the transformed page.
                //$backend->lock();
                if ($this->isCurrent()) {
                    $page->set('_cached_html', $this->_transformedContent->pack());
                }
                //$backend->unlock();
            }
        }

        return $this->_transformedContent;
    }

    /**
     * Get the content as a string.
     *
     * @return string The page content.
     * Lines are separated by new-lines.
     */
    public function getPackedContent()
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $data = &$this->_data;

        if (empty($data['%content'])
            || (!$this->_wikidb->isWikiPage($this->_pagename)
                && $this->isCurrent())
        ) {
            include_once 'lib/InlineParser.php';

            // A feature similar to taglines at http://www.wlug.org.nz/
            // Lib from http://www.aasted.org/quote/
            if (defined('FORTUNE_DIR')
                and is_dir(FORTUNE_DIR)
                    and in_array(
                        $request->getArg('action'),
                        array('create', 'edit')
                    )
            ) {
                include_once 'lib/fortune.php';
                $fortune = new Fortune();
                $quote = $fortune->quoteFromDir(FORTUNE_DIR);
                if ($quote != -1) {
                    $quote = "<verbatim>\n"
                        . str_replace("\n<br>", "\n", $quote)
                        . "</verbatim>\n\n";
                } else {
                    $quote = "";
                }
                return $quote
                    . sprintf(
                        _("Describe %s here."),
                        "[" . WikiEscape($this->_pagename) . "]"
                    );
            }
            // Replace empty content with default value.
            return sprintf(
                _("Describe %s here."),
                "[" . WikiEscape($this->_pagename) . "]"
            );
        }

        // There is (non-default) content.
        assert($this->_version > 0);

        if (!is_string($data['%content'])) {
            // Content was not provided to us at init time.
            // (This is allowed because for some backends, fetching
            // the content may be expensive, and often is not wanted
            // by the user.)
            //
            // In any case, now we need to get it.
            $data['%content'] = $this->_get_content();
            assert(is_string($data['%content']));
        }

        return $data['%content'];
    }

    public function _get_content()
    {
        $cache = &$this->_wikidb->_cache;
        $pagename = $this->_pagename;
        $version = $this->_version;

        assert($version > 0);

        $newdata = $cache->get_versiondata($pagename, $version, true);
        if ($newdata) {
            assert(is_string($newdata['%content']));
            return $newdata['%content'];
        } else {
            // else revision has been deleted... What to do?
            return __sprintf(
                "Oops! Revision %s of %s seems to have been deleted!",
                $version,
                $pagename
            );
        }
    }

    /**
     * Get meta-data for this revision.
     *
     * @param string $key Which meta-data to access.
     *
     * Some reserved revision meta-data keys are:
     * <dl>
     * <dt> 'mtime' <dd> Time this revision was created (seconds since midnight Jan 1, 1970.)
     *        The 'mtime' meta-value is normally set automatically by the database
     *        backend, but it may be specified explicitly when creating a new revision.
     * <dt> orig_mtime
     *  <dd> To ensure consistency of RecentChanges, the mtimes of the versions
     *       of a page must be monotonically increasing.  If an attempt is
     *       made to create a new revision with an mtime less than that of
     *       the preceeding revision, the new revisions timestamp is force
     *       to be equal to that of the preceeding revision.  In that case,
     *       the originally requested mtime is preserved in 'orig_mtime'.
     * <dt> '_supplanted' <dd> Time this revision ceased to be the most recent.
     *        This meta-value is <em>always</em> automatically maintained by the database
     *        backend.  (It is set from the 'mtime' meta-value of the superceding
     *        revision.)  '_supplanted' has a value of 'false' for the current revision.
     *
     * FIXME: this could be refactored:
     * <dt> author
     *  <dd> Author of the page (as he should be reported in, e.g. RecentChanges.)
     * <dt> author_id
     *  <dd> Authenticated author of a page.  This is used to identify
     *       the distinctness of authors when cleaning old revisions from
     *       the database.
     * <dt> 'is_minor_edit' <dd> Set if change was marked as a minor revision by the author.
     * <dt> 'summary' <dd> Short change summary entered by page author.
     * </dl>
     *
     * Meta-data keys must be valid C identifers (they have to start with a letter
     * or underscore, and can contain only alphanumerics and underscores.)
     *
     * @return string The requested value, or false if the requested value
     * is not defined.
     */
    public function get($key)
    {
        if (!$key || $key[0] == '%') {
            return false;
        }
        $data = &$this->_data;
        return isset($data[$key]) ? $data[$key] : false;
    }

    /**
     * Get all the revision page meta-data as a hash.
     *
     * @return array The revision meta-data.
     */
    public function getMetaData()
    {
        $meta = array();
        foreach ($this->_data as $key => $val) {
            if (!empty($val) && $key[0] != '%') {
                $meta[$key] = $val;
            }
        }
        return $meta;
    }

    /**
     * Return a string representation of the revision.
     *
     * This is really only for debugging.
     *
     * @return string Printable representation of the WikiDB_Page.
     */
    public function asString()
    {
        ob_start();
        printf("[%s:%d\n", get_class($this), $this->get('version'));
        print_r($this->_data);
        echo $this->getPackedContent() . "\n]\n";
        $strval = ob_get_contents();
        ob_end_clean();
        return $strval;
    }
}

/**
 * Class representing a sequence of WikiDB_Pages.
 * TODO: Enhance to php5 iterators
 * TODO:
 *   apply filters for options like 'sortby', 'limit', 'exclude'
 *   for simple queries like titleSearch, where the backend is not ready yet.
 */
class WikiDB_PageIterator
{
    public $_iter;
    public $_wikidb;
    public $_options;

    public function __construct(&$wikidb, &$iter, $options = array())
    {
        $this->_iter = $iter; // a WikiDB_backend_iterator
        $this->_wikidb = &$wikidb;
        $this->_options = $options;
    }

    public function count()
    {
        return $this->_iter->count();
    }

    public function limit()
    {
        return empty($this->_options['limit']) ? 0 : $this->_options['limit'];
    }

    /**
     * Get next WikiDB_Page in sequence.
     *
     * @return false|WikiDB_Page
     */
    public function next()
    {
        if (!($next = $this->_iter->next())) {
            return false;
        }

        $pagename = &$next['pagename'];
        if (!is_string($pagename)) { // Bug #1327912 fixed by Joachim Lous
            trigger_error("WikiDB_PageIterator->next pagename", E_USER_WARNING);
        }

        if (!$pagename) {
            if (isset($next['linkrelation'])
                or isset($next['pagedata']['linkrelation'])
            ) {
                return false;
            }
        }

        // There's always hits, but we cache only if more
        // (well not with file and dba)
        if (isset($next['pagedata']) and count($next['pagedata']) > 1) {
            $this->_wikidb->_cache->cache_data($next);
        // cache existing page id's since we iterate over all links in GleanDescription
            // and need them later for LinkExistingWord
        } elseif ($this->_options and array_key_exists('include_empty', $this->_options)
            and !$this->_options['include_empty'] and isset($next['id'])
        ) {
            $this->_wikidb->_cache->_id_cache[$next['pagename']] = $next['id'];
        }
        $page = new WikiDB_Page($this->_wikidb, $pagename);
        if (isset($next['linkrelation'])) {
            $page->set('linkrelation', $next['linkrelation']);
        }
        if (isset($next['score'])) {
            $page->score = $next['score'];
        }
        return $page;
    }

    /**
     * Release resources held by this iterator.
     *
     * The iterator may not be used after free() is called.
     *
     * There is no need to call free(), if next() has returned false.
     * (I.e. if you iterate through all the pages in the sequence,
     * you do not need to call free() --- you only need to call it
     * if you stop before the end of the iterator is reached.)
     */
    public function free()
    {
        // $this->_iter->free();
    }

    public function reset()
    {
        $this->_iter->reset();
    }

    public function asArray()
    {
        $result = array();
        while ($page = $this->next()) {
            $result[] = $page;
        }
        $this->reset();
        return $result;
    }
}

/**
 * A class which represents a sequence of WikiDB_PageRevisions.
 * TODO: Enhance to php5 iterators
 */
class WikiDB_PageRevisionIterator
{
    public $_revisions;
    public $_wikidb;
    public $_options;

    public function __construct(&$wikidb, &$revisions, $options = false)
    {
        $this->_revisions = $revisions;
        $this->_wikidb = &$wikidb;
        $this->_options = $options;
    }

    public function count()
    {
        return $this->_revisions->count();
    }

    /**
     * Get next WikiDB_PageRevision in sequence.
     *
     * @return false|WikiDB_PageRevision
     * The next WikiDB_PageRevision in the sequence.
     * Return false in case of error.
     */
    public function next()
    {
        if (!($next = $this->_revisions->next())) {
            return false;
        }

        //$this->_wikidb->_cache->cache_data($next);

        $pagename = $next['pagename'];
        $version = $next['version'];
        $versiondata = $next['versiondata'];
        if (DEBUG) {
            if (!(is_string($pagename) and $pagename != '')) {
                trigger_error("empty pagename", E_USER_WARNING);
                return false;
            }
        } else {
            assert(is_string($pagename) and $pagename != '');
        }
        if (DEBUG) {
            if (!is_array($versiondata)) {
                trigger_error("empty versiondata", E_USER_WARNING);
                return false;
            }
        } else {
            assert(is_array($versiondata));
        }
        if (DEBUG) {
            if (!($version > 0)) {
                trigger_error("invalid version", E_USER_WARNING);
                return false;
            }
        } else {
            assert($version > 0);
        }

        return new WikiDB_PageRevision($this->_wikidb, $pagename, $version, $versiondata);
    }

    /**
     * Release resources held by this iterator.
     *
     * The iterator may not be used after free() is called.
     *
     * There is no need to call free(), if next() has returned false.
     * (I.e. if you iterate through all the revisions in the sequence,
     * you do not need to call free() --- you only need to call it
     * if you stop before the end of the iterator is reached.)
     */
    public function free()
    {
        $this->_revisions->free();
    }

    public function asArray()
    {
        $result = array();
        while ($rev = $this->next()) {
            $result[] = $rev;
        }
        $this->free();
        return $result;
    }
}

/** pseudo iterator
 */
class WikiDB_Array_PageIterator
{
    public $_dbi;
    public $_pages;

    public function __construct($pagenames)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $this->_dbi = $request->getDbh();
        $this->_pages = $pagenames;
        reset($this->_pages);
    }

    public function next()
    {
        $c = current($this->_pages);
        next($this->_pages);
        return $c !== false ? $this->_dbi->getPage($c) : false;
    }

    public function count()
    {
        return count($this->_pages);
    }

    public function reset()
    {
        reset($this->_pages);
    }

    public function free()
    {
    }

    public function asArray()
    {
        reset($this->_pages);
        return $this->_pages;
    }
}

class WikiDB_Array_generic_iter
{
    public $_array;

    public function __construct($result)
    {
        // $result may be either an array or a query result
        if (is_array($result)) {
            $this->_array = $result;
        } elseif (is_object($result)) {
            $this->_array = $result->asArray();
        } else {
            $this->_array = array();
        }
        if (!empty($this->_array)) {
            reset($this->_array);
        }
    }

    public function next()
    {
        $c = current($this->_array);
        next($this->_array);
        return $c !== false ? $c : false;
    }

    public function count()
    {
        return count($this->_array);
    }

    public function reset()
    {
        reset($this->_array);
    }

    public function free()
    {
    }

    public function asArray()
    {
        if (!empty($this->_array)) {
            reset($this->_array);
        }
        return $this->_array;
    }
}

/**
 * Data cache used by WikiDB.
 *
 * FIXME: Maybe rename this to caching_backend (or some such).
 *
 * @access private
 */
class WikiDB_cache
{
    // FIXME: beautify versiondata cache.  Cache only limited data?

    public $_backend;
    public $_pagedata_cache;
    public $_versiondata_cache;
    public $_glv_cache;
    public $_id_cache;
    public $readonly;

    public function __construct(&$backend)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $this->_backend = &$backend;
        $this->_pagedata_cache = array();
        $this->_versiondata_cache = array();
        array_push($this->_versiondata_cache, array());
        $this->_glv_cache = array();
        $this->_id_cache = array(); // formerly ->_dbi->_iwpcache (nonempty pages => id)

        if (isset($request->_dbi)) {
            $this->readonly = $request->_dbi->readonly;
        }
    }

    public function close()
    {
        $this->_pagedata_cache = array();
        $this->_versiondata_cache = array();
        $this->_glv_cache = array();
        $this->_id_cache = array();
    }

    public function get_pagedata($pagename)
    {
        assert(is_string($pagename) && $pagename != '');
        $cache = &$this->_pagedata_cache;
        if (!isset($cache[$pagename]) || !is_array($cache[$pagename])) {
            $cache[$pagename] = $this->_backend->get_pagedata($pagename);
            if (empty($cache[$pagename])) {
                $cache[$pagename] = array();
            }
        }
        return $cache[$pagename];
    }

    public function update_pagedata($pagename, $newdata)
    {
        assert(is_string($pagename) && $pagename != '');
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }

        $this->_backend->update_pagedata($pagename, $newdata);

        if (!empty($this->_pagedata_cache[$pagename])
            and is_array($this->_pagedata_cache[$pagename])
        ) {
            $cachedata = &$this->_pagedata_cache[$pagename];
            foreach ($newdata as $key => $val) {
                $cachedata[$key] = $val;
            }
        } else {
            $this->_pagedata_cache[$pagename] = $newdata;
        }
    }

    public function invalidate_cache($pagename)
    {
        unset($this->_pagedata_cache[$pagename]);
        unset($this->_versiondata_cache[$pagename]);
        unset($this->_glv_cache[$pagename]);
        unset($this->_id_cache[$pagename]);
        //unset ($this->_backend->_page_data);
    }

    public function delete_page($pagename)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return false;
        }
        $result = $this->_backend->delete_page($pagename);
        $this->invalidate_cache($pagename);
        return $result;
    }

    public function purge_page($pagename)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return false;
        }
        $result = $this->_backend->purge_page($pagename);
        $this->invalidate_cache($pagename);
        return $result;
    }

    // FIXME: ugly and wrong. may overwrite full cache with partial cache
    public function cache_data($data)
    {
        //if (isset($data['pagedata']))
        //    $this->_pagedata_cache[$data['pagename']] = $data['pagedata'];
    }

    public function get_versiondata($pagename, $version, $need_content = false)
    {
        //  FIXME: Seriously ugly hackage
        $readdata = false;
        assert(is_string($pagename) && $pagename != '');
        $nc = $need_content ? '1' : '0';
        $cache = &$this->_versiondata_cache;
        if (!isset($cache[$pagename][$version][$nc])
            || !(is_array($cache[$pagename]))
            || !(is_array($cache[$pagename][$version]))
        ) {
            $cache[$pagename][$version][$nc] =
                $this->_backend->get_versiondata($pagename, $version, $need_content);
            $readdata = true;
            // If we have retrieved all data, we may as well set the cache for
            // $need_content = false
            if ($need_content) {
                $cache[$pagename][$version]['0'] =& $cache[$pagename][$version]['1'];
            }
        }
        $vdata = $cache[$pagename][$version][$nc];

        if ($readdata && is_array($vdata) && !empty($vdata['%pagedata'])) {
            if (empty($this->_pagedata_cache)) {
                $this->_pagedata_cache = array();
            }
            $this->_pagedata_cache[$pagename] = $vdata['%pagedata'];
        }
        return $vdata;
    }

    public function set_versiondata($pagename, $version, $data)
    {
        //unset($this->_versiondata_cache[$pagename][$version]);

        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }
        $this->_backend->set_versiondata($pagename, $version, $data);
        // Update the cache
        $this->_versiondata_cache[$pagename][$version]['1'] = $data;
        $this->_versiondata_cache[$pagename][$version]['0'] = $data;
        // Is this necessary?
        unset($this->_glv_cache[$pagename]);
    }

    public function update_versiondata($pagename, $version, $data)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }
        $this->_backend->update_versiondata($pagename, $version, $data);
        // Update the cache
        $this->_versiondata_cache[$pagename][$version]['1'] = $data;
        // FIXME: hack
        $this->_versiondata_cache[$pagename][$version]['0'] = $data;
        // Is this necessary?
        unset($this->_glv_cache[$pagename]);
    }

    public function delete_versiondata($pagename, $version)
    {
        if (!empty($this->readonly)) {
            if (DEBUG) {
                trigger_error("readonly database", E_USER_WARNING);
            }
            return;
        }
        $this->_backend->delete_versiondata($pagename, $version);
        if (isset($this->_versiondata_cache[$pagename][$version])) {
            unset($this->_versiondata_cache[$pagename][$version]);
        }
        // dirty latest version cache only if latest version gets deleted
        if (isset($this->_glv_cache[$pagename]) and $this->_glv_cache[$pagename] == $version) {
            unset($this->_glv_cache[$pagename]);
        }
    }

    public function get_latest_version($pagename)
    {
        assert(is_string($pagename) && $pagename != '');
        $cache = &$this->_glv_cache;
        if (!isset($cache[$pagename])) {
            $cache[$pagename] = $this->_backend->get_latest_version($pagename);
            if (empty($cache[$pagename])) {
                $cache[$pagename] = 0;
            }
        }
        return $cache[$pagename];
    }
}
