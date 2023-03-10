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

/** DBA Sessions
 *  session:
 *     Index: session_id
 *     Values: date : IP : data
 *  Already open sessions, e.g. interim xmlrpc requests are
 *  are treated specially. see write().
 *  To avoid deadlocks in the session.db3 access,
 *  the db is opened and closed for each access.
 * @author: Reini Urban.
 */

class DbSession_dba extends DbSession
{
    public $_backend_type = "dba";

    public function __construct($dbh, $table)
    {
        $this->_dbh = $dbh;
        session_set_save_handler(
            array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc')
        );
    }

    public function quote($string)
    {
        return $string;
    }

    public function query($sql)
    {
        return false;
    }

    public function & _connect()
    {
        global $DBParams;
        $dbh = &$this->_dbh;
        if (!$dbh) {
            $directory = '/tmp';
            $prefix = 'wiki_';
            $dba_handler = 'db4';
            $timeout = 12;
            extract($DBParams); // overwrite the defaults
            $dbfile = "$directory/$prefix" . 'session' . '.' . $dba_handler;
            $dbh = new DbaDatabase($dbfile, 'c', $dba_handler);
            $this->_dbh = &$dbh;
        }
        return $dbh;
    }

    public function _disconnect()
    {
        if (isset($this->_dbh)) {
            $this->_dbh->close();
            unset($this->_dbh);
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
        $dbh = $this->_connect();
        $dbh->open();
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
        $this->_disconnect();
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
        $dbh = $this->_connect();
        $result = $dbh->get($id);
        if (!$result) {
            return '';
        }
        list(, , $packed) = explode('|', $result, 3);
        $this->_disconnect();
        if (strlen($packed) > 4000) {
            // trigger_error("Overlarge session data!", E_USER_WARNING);
            $packed = '';
            //$res = preg_replace('/s:6:"_cache";O:12:"WikiDB_cache".+}$/',"",$res);
        }
        return $packed;
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
        $time = time();
        $ip = $request->get('REMOTE_ADDR');
        if (strlen($sess_data) > 4000) {
            trigger_error("Overlarge session data!", E_USER_WARNING);
            $sess_data = '';
        }
        $dbh->set($id, $time . '|' . $ip . '|' . $sess_data);
        $this->_disconnect();
        return true;
    }

    public function destroy($id)
    {
        $dbh = $this->_connect();
        $dbh->delete($id);
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
        $threshold = time() - $maxlifetime;
        for ($id = $dbh->firstkey(); $id !== false; $id = $nextid) {
            $result = $dbh->get($id);
            list($date, , ) = explode('|', $result, 3);
            $nextid = $dbh->nextkey();
            if ($date < $threshold) {
                $dbh->delete($id);
            }
        }
        $dbh->optimize();
        $this->_disconnect();
        return true;
    }

    // WhoIsOnline support
    // TODO: ip-accesstime dynamic blocking API
    public function currentSessions()
    {
        $sessions = array();
        $dbh = $this->_connect();
        for ($id = $dbh->firstkey(); $id !== false; $id = $dbh->nextkey()) {
            $result = $dbh->get($id);
            list($date, $ip, $packed) = explode('|', $result, 3);
            if (!$packed) {
                continue;
            }
            // session_data contains the <variable name> + "|" + <packed string>
            // we need just the wiki_user object (might be array as well)
            if ($date < 908437560 or $date > 1588437560) {
                $date = 0;
            }
            $user = strstr($packed, "wiki_user|");
            $sessions[] = array('wiki_user' => substr($user, 10), // from "O:" onwards
                'date' => $date,
                'ip' => $ip);
        }
        $this->_disconnect();
        return $sessions;
    }
}
