<?php
/**
 * Copyright © 2007 $ThePhpWikiProgrammingTeam
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
 * @author: Reini Urban
 */
require_once 'lib/WikiDB/backend/PDO.php';

class WikiDB_backend_PDO_oci8 extends WikiDB_backend_PDO
{
    public function backendType()
    {
        return 'oci8';
    }

    public function optimize()
    {
        // Do nothing here -- Leave that for the DBA
        // Cost Based Optimizer tuning vary from version to version
        return true;
    }

    /*
     * Lock all tables we might use.
     */
    protected function _lock_tables($tables, $write_lock = true)
    {
        $dbh = &$this->_dbh;

        // Not sure if we really need to lock tables here, the Oracle row
        // locking mechanism should be more than enough
        // For the time being, lets stay on the safe side and lock...
        if ($write_lock) {
            // Next line is default behaviour, so just skip it
            // $dbh->query("SET TRANSACTION READ WRITE");
            foreach ($this->_table_names as $table) {
                $dbh->exec("LOCK TABLE $table IN EXCLUSIVE MODE");
            }
        } else {
            // Just ensure read consistency
            $dbh->exec("SET TRANSACTION READ ONLY");
        }
    }

    public function write_accesslog(&$entry)
    {
        $dbh = &$this->_dbh;
        $log_tbl = $entry->_accesslog->logtable;
        $sth = $dbh->prepare("INSERT INTO $log_tbl"
            . " (time_stamp,remote_host,remote_user,request_method,request_line,request_args,"
            . "request_file,request_uri,request_time,status,bytes_sent,referer,agent,request_duration)"
            . " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        // Either use unixtime as %d (long), or the native timestamp format.
        $datetime = date('d-M-Y H:i:s', $entry->time);
        $sth->bindParam(1, $datetime);
        $sth->bindParam(2, $entry->host, PDO::PARAM_STR, 100);
        $sth->bindParam(3, $entry->user, PDO::PARAM_STR, 50);
        $sth->bindParam(4, $entry->request_method, PDO::PARAM_STR, 10);
        $sth->bindParam(5, $entry->request, PDO::PARAM_STR, 255);
        $sth->bindParam(6, $entry->request_args, PDO::PARAM_STR, 255);
        $sth->bindParam(7, $entry->request_uri, PDO::PARAM_STR, 255);
        $ncsa_time = ncsa_time($entry->time);
        $sth->bindParam(8, $ncsa_time, PDO::PARAM_STR, 28);
        $sth->bindParam(9, $entry->time, PDO::PARAM_INT);
        $sth->bindParam(10, $entry->status, PDO::PARAM_INT);
        $sth->bindParam(11, $entry->size, PDO::PARAM_INT);
        $sth->bindParam(12, $entry->referer, PDO::PARAM_STR, 255);
        $sth->bindParam(13, $entry->user_agent, PDO::PARAM_STR, 255);
        $sth->bindParam(14, $entry->duration, PDO::PARAM_STR, 20);
        $sth->execute();
    }
}
