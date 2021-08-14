<?php
/**
 * Copyright © 2010 Reini Urban
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

/**
 * MS SQL extensions for the ADODB DB backend.
 */

require_once 'lib/WikiDB/backend/ADODB.php';

class WikiDB_backend_ADODB_mssqlnative
    extends WikiDB_backend_ADODB
{
    function __construct($dbparams)
    {
        // Lowercase Assoc arrays
        define('ADODB_ASSOC_CASE', 0);

        // Backend constructor
        parent::__construct($dbparams);

        // Empty strings in MSSQL?  NULLS?
        $this->_expressions['notempty'] = "NOT LIKE ''";
        //doesn't work if content is of the "text" type http://msdn2.microsoft.com/en-us/library/ms188074.aspx
        $this->_expressions['iscontent'] = "dbo.hasContent({$this->_table_names['version_tbl']}.content)";

        $this->_prefix = isset($dbparams['prefix']) ? $dbparams['prefix'] : '';

    }

    /**
     * Pack tables.
     */
    function optimize()
    {
        // Do nothing here -- Leave that for the DB
        // Cost Based Optimizer tuning vary from version to version
        return true;
    }

    // Search callabcks
    // Page name
    function _sql_match_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $word";
    }

    // Fulltext -- case sensitive :-\
    function _fullsearch_sql_match_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $wordq = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $wordq "
            . "OR CHARINDEX(content, '$word') > 0";
    }

    /*
     * Serialize data
     */
    function _serialize($data)
    {
        if (empty($data))
            return '';
        assert(is_array($data));
        return addslashes(serialize($data));
    }

    /*
     * Unserialize data
     */
    function _unserialize($data)
    {
        return empty($data) ? array() : unserialize(stripslashes($data));
    }

    /**
     * Set links for page.
     *
     * @param string $pagename Page name
     * @param array  $links    List of page(names) which page links to.
     */
    function set_links($pagename, $links)
    {
        // FIXME: optimize: mysql can do this all in one big INSERT/REPLACE.

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock(array('link'));
        $pageid = $this->_get_pageid($pagename, true);

        $oldlinks = $dbh->getAssoc("SELECT $link_tbl.linkto as id, page.pagename FROM $link_tbl"
            . " JOIN page ON ($link_tbl.linkto = page.id)"
            . " WHERE linkfrom=$pageid");
        // Delete current links,
        $dbh->Execute("DELETE FROM $link_tbl WHERE linkfrom=$pageid");
        // and insert new links. Faster than checking for all single links
        if ($links) {
            foreach ($links as $link) {
                $linkto = $link['linkto'];
                if (isset($link['relation']))
                    $relation = $this->_get_pageid($link['relation'], true);
                else
                    $relation = 0;
                if ($linkto === "") { // ignore attributes
                    continue;
                }
                // avoid duplicates
                if (isset($linkseen[$linkto]) and !$relation) {
                    continue;
                }
                if (!$relation) {
                    $linkseen[$linkto] = true;
                }
                $linkid = $this->_get_pageid($linkto, true);
                assert($linkid);
                if ($relation) {
                    $dbh->Execute("INSERT INTO $link_tbl (linkfrom, linkto, relation)"
                        . " VALUES ($pageid, $linkid, $relation)");
                } else {
                    $dbh->Execute("INSERT INTO $link_tbl (linkfrom, linkto)"
                        . " VALUES ($pageid, $linkid)");
                }
                if ($oldlinks and array_key_exists($linkid, $oldlinks)) {
                    // This was also in the previous page
                    unset($oldlinks[$linkid]);
                }
            }
        }
        $this->unlock(array('link'));
    }
}
