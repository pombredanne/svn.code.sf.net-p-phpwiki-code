<?php
/**
 * Copyright © 2001 Jeff Dairiki
 * Copyright © 2001-2002 Carsten Klapp
 * Copyright © 2004-2006,2009-2010 Reini Urban
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

require_once 'lib/ErrorManager.php';

if (isWindows()) {
    define('DBA_DATABASE_DEFAULT_TIMEOUT', 60);
} else {
    define('DBA_DATABASE_DEFAULT_TIMEOUT', 5);
}

class DbaDatabase
{
    public $_file;
    public $_handler;
    public $_timeout;
    /**
     * @var resource $_dbh
     */
    public $_dbh;
    public $readonly;
    public $_dba_open_error;

    /**
     * @param string $filename
     * @param bool $mode
     * @param string $handler
     */
    public function __construct($filename, $mode = false, $handler = 'db4')
    {
        $this->_file = $filename;
        $this->_handler = $handler;
        $this->_timeout = DBA_DATABASE_DEFAULT_TIMEOUT;
        $this->_dbh = false;
        if (!in_array($handler, dba_handlers())) {
            $this->_error(
                sprintf(
                    _("The DBA handler %s is unsupported!") . "\n" .
                        _("Supported handlers are: %s"),
                    $handler,
                    join(",", dba_handlers())
                )
            );
        }
        $this->readonly = false;
        if ($mode) {
            $this->open($mode);
        }
    }

    public function set_timeout($timeout)
    {
        $this->_timeout = $timeout;
    }

    public function open($mode = 'w')
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if ($this->_dbh) {
            return true;
        } // already open.

        $watchdog = $this->_timeout;

        global $ErrorManager;
        $this->_dba_open_error = false;
        $ErrorManager->pushErrorHandler(new WikiMethodCb($this, '_dba_open_error_handler'));

        // oops, you don't have DBA support.
        if (!function_exists("dba_open")) {
            echo "You don't seem to have DBA support compiled into PHP.";
        }

        if (ISREADONLY) {
            $mode = 'r';
        }

        if ((strlen($mode) == 1)) {
            if ($this->_handler != 'gdbm') { // gdbm does it internally
                $mode .= "d"; // else use internal locking
            }
        }
        while (($dbh = dba_open($this->_file, $mode, $this->_handler)) < 1) {
            if ($watchdog <= 0) {
                break;
            }
            // "c" failed, try "w" instead.
            if ($mode == "w"
                and file_exists($this->_file)
                    and (isWindows() or !is_writable($this->_file))
            ) {
                // try to continue with read-only
                if (!defined("ISREADONLY")) {
                    define("ISREADONLY", true);
                }
                $request->_dbi->readonly = true;
                $this->readonly = true;
                $mode = "r";
            }
            if (substr($mode, 0, 1) == "c" and file_exists($this->_file) and !ISREADONLY) {
                $mode = "w";
            }
            // conflict: wait some random time to unlock (as with ethernet)
            $secs = 0.5 + ((float)rand(1, 32767) / 32767);
            sleep($secs);
            $watchdog -= $secs;
            if (strlen($mode) == 2) {
                $mode = substr($mode, 0, -1);
            }
        }
        $ErrorManager->popErrorHandler();

        if (!$dbh) {
            if (($error = $this->_dba_open_error)) {
                $error->errno = E_USER_ERROR;
                $error->errstr .= "\nfile: " . $this->_file
                    . "\nmode: " . $mode
                    . "\nhandler: " . $this->_handler;
                // try to continue with read-only
                if (!defined("ISREADONLY")) {
                    define("ISREADONLY", true);
                }
                $request->_dbi->readonly = true;
                $this->readonly = true;
                if (!file_exists($this->_file)) {
                    $ErrorManager->handleError($error);
                    flush();
                }
            } else {
                trigger_error("dba_open failed", E_USER_ERROR);
            }
        }
        $this->_dbh = $dbh;
        return !empty($dbh);
    }

    public function close()
    {
        if ($this->_dbh) {
            dba_close($this->_dbh);
        }
        $this->_dbh = false;
    }

    public function exists($key)
    {
        return dba_exists($key, $this->_dbh);
    }

    public function fetch($key)
    {
        $val = dba_fetch($key, $this->_dbh);
        if ($val === false) {
            $this->_error("fetch($key)");
        }
        return $val;
    }

    public function insert($key, $val)
    {
        if (!dba_insert($key, $val, $this->_dbh)) {
            $this->_error("insert($key)");
        }
    }

    public function replace($key, $val)
    {
        if (!dba_replace($key, $val, $this->_dbh)) {
            $this->_error("replace($key)");
        }
    }

    public function firstkey()
    {
        return dba_firstkey($this->_dbh);
    }

    public function nextkey()
    {
        return dba_nextkey($this->_dbh);
    }

    public function delete($key)
    {
        if ($this->readonly) {
            return;
        }
        if (!dba_delete($key, $this->_dbh)) {
            $this->_error("delete($key)");
        }
    }

    public function get($key)
    {
        return dba_fetch($key, $this->_dbh);
    }

    public function set($key, $val)
    {
        $dbh = &$this->_dbh;
        if ($this->readonly) {
            return;
        }
        if (dba_exists($key, $dbh)) {
            if ($val !== false) {
                if (!dba_replace($key, $val, $dbh)) {
                    $this->_error("store[replace]($key)");
                }
            } else {
                if (!dba_delete($key, $dbh)) {
                    $this->_error("store[delete]($key)");
                }
            }
        } else {
            if (!dba_insert($key, $val, $dbh)) {
                $this->_error("store[insert]($key)");
            }
        }
    }

    public function sync()
    {
        if (!dba_sync($this->_dbh)) {
            $this->_error("sync()");
        }
    }

    public function optimize()
    {
        if (!dba_optimize($this->_dbh)) {
            $this->_error("optimize()");
        }
        return 1;
    }

    private function _error($mes)
    {
        trigger_error("$this->_file: dba error: $mes", E_USER_ERROR);
    }

    public function _dump()
    {
        $dbh = &$this->_dbh;
        for ($key = $this->firstkey(); $key; $key = $this->nextkey()) {
            printf("%10s: %s\n", $key, $this->fetch($key));
        }
    }

    public function _dba_open_error_handler($error)
    {
        $this->_dba_open_error = $error;
        return true;
    }
}
