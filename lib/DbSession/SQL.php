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
 * DB sessions for pear DB
 *
 * History
 *
 * Originally by Stanislav Shramko <stanis@movingmail.com>
 * Minor rewrite by Reini Urban <rurban@x-ray.at> for Phpwiki.
 * Quasi-major rewrite/decruft/fix by Jeff Dairiki <dairiki@dairiki.org>.
 */

class DbSession_SQL extends DbSession
{
    public $_backend_type = "SQL";

    public function __construct($dbh, $table)
    {
        $this->_dbh = $dbh;
        $this->_table = $table;

        session_set_save_handler(
            array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc')
        );
    }

    public function & _connect()
    {
        $dbh = &$this->_dbh;
        $this->_connected = is_resource($dbh->connection);
        if (!$this->_connected) {
            $res = $dbh->connect($dbh->dsn);
            if (DB::isError($res)) {
                error_log("PhpWiki::DbSession::_connect: " . $res->getMessage());
            }
        }
        return $dbh;
    }

    public function query($sql)
    {
        return $this->_dbh->query($sql);
    }

    // adds surrounding quotes
    public function quote($string)
    {
        return $this->_dbh->quote($string);
    }

    public function _disconnect()
    {
        if (0 and $this->_connected) {
            $this->_dbh->disconnect();
        }
    }

    /**
     * Opens a session.
     *
     * Actually this function is a fake for session_set_save_handle.
     * @param  string  $save_path    a path to stored files
     * @param  string  $session_name a name of the concrete file
     * @return boolean true just a variable to notify PHP that everything
     * is good.
     */
    public function open($save_path, $session_name)
    {
        //$this->log("_open($save_path, $session_name)");
        return true;
    }

    /**
     * Closes a session.
     *
     * This function is called just after <i>write</i> call.
     *
     * @return boolean true just a variable to notify PHP that everything
     * is good.
     */
    public function close()
    {
        //$this->log("_close()");
        return true;
    }

    /**
     * Reads the session data from DB.
     *
     * @param  string $id an id of current session
     * @return string
     */
    public function read($id)
    {
        //$this->log("_read($id)");
        $dbh = $this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);

        $res = $dbh->getOne("SELECT sess_data FROM $table WHERE sess_id=$qid");

        $this->_disconnect();
        if (DB::isError($res) || empty($res)) {
            return '';
        }
        if (is_a($dbh, 'DB_pgsql')) {
            $res = base64_decode($res);
        }
        if (strlen($res) > 4000) {
            // trigger_error("Overlarge session data! ".strlen($res). " gt. 4000", E_USER_WARNING);
            $res = preg_replace('/s:6:"_cache";O:12:"WikiDB_cache".+}$/', "", $res);
            $res = preg_replace('/s:12:"_cached_html";s:.+",s:4:"hits"/', 's:4:"hits"', $res);
            if (strlen($res) > 4000) {
                $res = '';
            }
        }
        return $res;
    }

    /**
     * Saves the session data into DB.
     *
     * Just  a  comment:       The  "write"  handler  is  not
     * executed until after the output stream is closed. Thus,
     * output from debugging statements in the "write" handler
     * will  never be seen in the browser. If debugging output
     * is  necessary, it is suggested that the debug output be
     * written to a file instead.
     *
     * @param  string  $id
     * @param  string  $sess_data
     * @return boolean true if data saved successfully  and false
     * otherwise.
     */
    public function write($id, $sess_data)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (defined("WIKI_XMLRPC") or defined("WIKI_SOAP")) {
            return false;
        }

        $dbh = $this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);
        $qip = $dbh->quote($request->get('REMOTE_ADDR'));
        $time = $dbh->quote(time());
        if (DEBUG and $sess_data == 'wiki_user|N;') {
            trigger_error("delete empty session $qid", E_USER_WARNING);
        }
        // postgres can't handle binary data in a TEXT field.
        if (is_a($dbh, 'DB_pgsql')) {
            $sess_data = base64_encode($sess_data);
        }
        $qdata = $dbh->quote($sess_data);

        $dbh->query("DELETE FROM $table WHERE sess_id=$qid");
        $res = $dbh->query("INSERT INTO $table"
            . " (sess_id, sess_data, sess_date, sess_ip)"
            . " VALUES ($qid, $qdata, $time, $qip)");
        $this->_disconnect();
        return !DB::isError($res);
    }

    /**
     * Destroys a session.
     *
     * Removes a session from the table.
     *
     * @param  string  $id
     * @return boolean true
     */
    public function destroy($id)
    {
        $dbh = $this->_connect();
        $table = $this->_table;
        $qid = $dbh->quote($id);

        $dbh->query("DELETE FROM $table WHERE sess_id=$qid");

        $this->_disconnect();
        return true;
    }

    /**
     * Cleans out all expired sessions.
     *
     * @param  int     $maxlifetime session's time to live.
     * @return boolean true
     */
    public function gc($maxlifetime)
    {
        $dbh = $this->_connect();
        $table = $this->_table;
        $threshold = time() - $maxlifetime;

        $dbh->query("DELETE FROM $table WHERE sess_date < $threshold");

        $this->_disconnect();
        return true;
    }

    // WhoIsOnline support
    // TODO: ip-accesstime dynamic blocking API
    public function currentSessions()
    {
        $sessions = array();
        $dbh = $this->_connect();
        $table = $this->_table;
        $res = $dbh->query("SELECT sess_data,sess_date,sess_ip FROM $table ORDER BY sess_date DESC");
        if (DB::isError($res) || empty($res)) {
            return $sessions;
        }
        while ($row = $res->fetchRow()) {
            $data = $row['sess_data'];
            $date = $row['sess_date'];
            $ip = $row['sess_ip'];
            if (preg_match('|^[a-zA-Z0-9/+=]+$|', $data)) {
                $data = base64_decode($data);
            }
            if ($date < 908437560 or $date > 1588437560) {
                $date = 0;
            }
            // session_data contains the <variable name> + "|" + <packed string>
            // we need just the wiki_user object (might be array as well)
            $user = strstr($data, "wiki_user|");
            $sessions[] = array('wiki_user' => substr($user, 10), // from "O:" onwards
                'date' => $date,
                'ip' => $ip);
        }
        $this->_disconnect();
        return $sessions;
    }
}
