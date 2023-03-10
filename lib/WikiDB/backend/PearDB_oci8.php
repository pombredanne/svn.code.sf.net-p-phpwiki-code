<?php
/**
 * Copyright © 2004 Philippe Vanhaesendonck
 * Copyright © 2004-2007 Reini Urban
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
 * Oracle extensions for the Pear DB backend.
 * @author: Philippe.Vanhaesendonck@topgame.be
 */

require_once 'lib/WikiDB/backend/PearDB_pgsql.php';

class WikiDB_backend_PearDB_oci8 extends WikiDB_backend_PearDB_pgsql
{
    public function __construct($dbparams)
    {
        // Backend constructor
        parent::__construct($dbparams);
        if (DB::isError($this->_dbh)) {
            return;
        }

        // Empty strings are NULLS
        $this->_expressions['notempty'] = "IS NOT NULL";
        $this->_expressions['iscontent'] = "DECODE(DBMS_LOB.GETLENGTH(content), NULL, 0, 0, 0, 1)";

        // Set parameters:
        $dbh = &$this->_dbh;
        // - No persistent conections (I don't like them)
        $dbh->setOption('persistent', false);
        // - Set lowercase compatibility option
        // - Set numrows as well -- sure why this is needed, but some queries
        //   are triggering DB_ERROR_NOT_CAPABLE
        $dbh->setOption(
            'portability',
            DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_NULL_TO_EMPTY | DB_PORTABILITY_NUMROWS
        );
    }

    /**
     * Pack tables.
     */
    public function optimize()
    {
        // Do nothing here -- Leave that for the DBA
        // Cost Based Optimizer tuning vary from version to version
        return true;
    }

    /*
     * Lock all tables we might use.
     */
    protected function _lock_tables($write_lock = true)
    {
        $dbh = &$this->_dbh;

        // Not sure if we really need to lock tables here, the Oracle row
        // locking mechanism should be more than enough
        // For the time being, lets stay on the safe side and lock...
        if ($write_lock) {
            // Next line is default behaviour, so just skip it
            // $dbh->query("SET TRANSACTION READ WRITE");
            foreach ($this->_table_names as $table) {
                $dbh->query("LOCK TABLE $table IN EXCLUSIVE MODE");
            }
        } else {
            // Just ensure read consistency
            $dbh->query("SET TRANSACTION READ ONLY");
        }
    }

    public function _quote($s)
    {
        return base64_encode($s);
    }

    public function _unquote($s)
    {
        return base64_decode($s);
    }

    public function write_accesslog(&$entry)
    {
        $dbh = &$this->_dbh;
        $log_tbl = $entry->_accesslog->logtable;
        // duration problem: sprintf "%f" might use comma e.g. "100,201" in european locales
        $dbh->query(
            "INSERT INTO $log_tbl"
                . " (time_stamp,remote_host,remote_user,request_method,request_line,request_uri,"
                . "request_args,request_time,status,bytes_sent,referer,agent,request_duration)"
                . " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array(
                // Problem: date formats are backend specific. Either use unixtime as %d (long),
                // or the native timestamp format.
                date('d-M-Y H:i:s', $entry->time),
                $entry->host,
                $entry->user,
                $entry->request_method,
                $entry->request,
                $entry->request_uri,
                $entry->request_args,
                ncsa_time($entry->time),
                $entry->status,
                $entry->size,
                $entry->referer,
                $entry->user_agent,
                $entry->duration)
        );
    }
}

class WikiDB_backend_PearDB_oci8_search extends WikiDB_backend_PearDB_search
{
    // If we want case insensitive search, one need to create a Context
    // Index on the CLOB. While it is very efficient, it requires the
    // Intermedia Text option, so let's stick to the 'simple' thing
    // Note that this does only an exact fulltext search, not using MATCH or LIKE.
    public function _fulltext_match_clause($node)
    {
        if ($this->isStoplisted($node)) {
            return "1=1";
        }
        $page = $node->sql();
        $exactword = $node->sql_quote($node->word);
        return ($this->_case_exact
            ? "pagename LIKE '$page' OR DBMS_LOB.INSTR(content, '$exactword') > 0"
            : "LOWER(pagename) LIKE '$page' OR DBMS_LOB.INSTR(content, '$exactword') > 0");
    }
}
