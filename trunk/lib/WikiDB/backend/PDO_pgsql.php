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

class WikiDB_backend_PDO_pgsql extends WikiDB_backend_PDO
{
    public function backendType()
    {
        return 'pgsql';
    }

    /*
     * offset specific syntax within pgsql
     * convert from,count to SQL "LIMIT $count OFFSET $from"
     */
    public function _limit_sql($limit = false)
    {
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            if ($from) {
                $limit = " LIMIT $count OFFSET $from";
            } else {
                $limit = " LIMIT $count";
            }
        } else {
            $limit = '';
        }
        return $limit;
    }
}
