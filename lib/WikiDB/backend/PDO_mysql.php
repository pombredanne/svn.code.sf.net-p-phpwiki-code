<?php
/**
 * Copyright © 2005 $ThePhpWikiProgrammingTeam
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

class WikiDB_backend_PDO_mysql extends WikiDB_backend_PDO
{
    public function __construct($dbparams)
    {
        parent::__construct($dbparams);
        $this->_dbh->query("SET NAMES 'utf8'");
    }

    public function backendType()
    {
        return 'mysql';
    }

    /**
     * Kill timed out processes. ( so far only called on about every 50-th save. )
     */
    private function _timeout()
    {
        if (empty($this->_dbparams['timeout'])) {
            return;
        }
        $sth = $this->_dbh->prepare("SHOW processlist");
        if ($sth->execute()) {
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                if ($row["db"] == $this->_dbh->dsn['database']
                    and $row["User"] == $this->_dbh->dsn['username']
                        and $row["Time"] > $this->_dbparams['timeout']
                            and $row["Command"] == "Sleep"
                ) {
                    $process_id = $row["Id"];
                    $this->query("KILL $process_id");
                }
            }
        }
    }

    /**
     * Pack tables.
     */
    public function optimize()
    {
        $this->_timeout();
        foreach ($this->_table_names as $table) {
            $this->query("OPTIMIZE TABLE $table");
        }
        return true;
    }

    public function listOfTables()
    {
        $sth = $this->_dbh->prepare("SHOW TABLES");
        $sth->execute();
        $tables = array();
        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    /*
     * offset specific syntax within mysql
     * convert from,count to SQL "LIMIT $from, $count"
     */
    public function _limit_sql($limit = false)
    {
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            if ($from) {
                $limit = " LIMIT $from, $count";
            } else {
                $limit = " LIMIT $count";
            }
        } else {
            $limit = '';
        }
        return $limit;
    }
}
