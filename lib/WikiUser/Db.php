<?php
/**
 * Copyright © 2004 Reini Urban
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
 * Baseclass for PearDB PassUser's
 * Authenticate against a database, to be able to use shared users.
 *   internal: no different $DbAuthParams['dsn'] defined, or
 *   external: different $DbAuthParams['dsn']
 * The magic is done in the symbolic SQL statements in config/config.ini, similar to
 * libnss-mysql.
 *
 * We support only the SQL backends.
 * The other WikiDB backends (flat, dba, ...) should be used for pages,
 * not for auth stuff. If one would like to use e.g. dba for auth, he should
 * use PearDB (SQL) with the right $DBAuthParam['auth_dsn'].
 * (Not supported yet, since we require SQL. SQLite would make since when
 * it will come to PHP)
 *
 * @tables: user, pref
 *
 * Preferences are handled in the parent class _PassUser, because the
 * previous classes may also use DB pref_select and pref_update.
 *
 * Flat files auth is handled by the auth method "File".
 */
class _DbPassUser extends _PassUser
{
    public $_authselect;
    public $_authupdate;
    public $_authcreate;

    // This can only be called from _PassUser, because the parent class
    // sets the auth_dbi and pref methods, before this class is initialized.
    public function __construct($UserName = '', $prefs = false)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (!$this->_prefs) {
            if ($prefs) {
                $this->_prefs = $prefs;
            }
        }
        if (!isset($this->_prefs->_method)) {
            parent::__construct($UserName);
        } elseif (!$this->isValidName($UserName)) {
            trigger_error(_("Invalid username."), E_USER_WARNING);
            return false;
        }
        $this->_authmethod = 'Db';
        //$this->getAuthDbh();
        //$this->_auth_crypt_method = @$GLOBALS['DBAuthParams']['auth_crypt_method'];
        $dbi =& $request->_dbi;
        $dbtype = $dbi->getParam('dbtype');
        if ($dbtype == 'SQL') {
            include_once 'lib/WikiUser/PearDb.php';
            return new _PearDbPassUser($UserName, $this->_prefs);
        } elseif ($dbtype == 'PDO') {
            include_once 'lib/WikiUser/PdoDb.php';
            return new _PdoDbPassUser($UserName, $this->_prefs);
        }
        return false;
    }

    /* Since we properly quote the username, we allow most chars here.
       Just " ; and ' is forbidden, max length: 48 as defined in the schema.
    */
    public function isValidName($userid = false)
    {
        if (!$userid) {
            $userid = $this->_userid;
        }
        if (strcspn($userid, ";'\"") != strlen($userid)) {
            return false;
        }
        if (strlen($userid) > 48) {
            return false;
        }
        return true;
    }

    public function mayChangePass()
    {
        return !isset($this->_authupdate);
    }
}
