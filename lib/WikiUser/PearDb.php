<?php //-*-php-*-
rcs_id('$Id: PearDb.php,v 1.2 2004-11-10 15:29:21 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
 */

class _PearDbPassUser
extends _DbPassUser
/**
 * Pear DB methods
 * Now optimized not to use prepare, ...query(sprintf($sql,quote())) instead.
 * We use FETCH_MODE_ROW, so we don't need aliases in the auth_* SQL statements.
 *
 * @tables: user
 * @tables: pref
 */
{
    var $_authmethod = 'PearDb';
    function _PearDbPassUser($UserName='',$prefs=false) {
        //global $DBAuthParams;
        if (!$this->_prefs and isa($this,"_PearDbPassUser")) {
            if ($prefs) $this->_prefs = $prefs;
        }
        if (!isset($this->_prefs->_method))
            _PassUser::_PassUser($UserName);
        elseif (!$this->isValidName($UserName)) {
            trigger_error(_("Invalid username."), E_USER_WARNING);
            return false;
        }
        $this->_userid = $UserName;
        // make use of session data. generally we only initialize this every time, 
        // but do auth checks only once
        $this->_auth_crypt_method = $GLOBALS['request']->_dbi->getAuthParam('auth_crypt_method');
        return $this;
    }

    function getPreferences() {
        // override the generic slow method here for efficiency and not to 
        // clutter the homepage metadata with prefs.
        _AnonUser::getPreferences();
        $this->getAuthDbh();
        if (isset($this->_prefs->_select)) {
            $dbh = &$this->_auth_dbi;
            $db_result = $dbh->query(sprintf($this->_prefs->_select, $dbh->quote($this->_userid)));
            // patched by frederik@pandora.be
            $prefs = $db_result->fetchRow();
            $prefs_blob = @$prefs["prefs"]; 
            if ($restored_from_db = $this->_prefs->retrieve($prefs_blob)) {
                $updated = $this->_prefs->updatePrefs($restored_from_db);
                //$this->_prefs = new UserPreferences($restored_from_db);
                return $this->_prefs;
            }
        }
        if ($this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve
                ($this->_HomePagehandle->get('pref'))) {
                $updated = $this->_prefs->updatePrefs($restored_from_page);
                //$this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        // if the prefs are changed
        if ($count = _AnonUser::setPreferences($prefs, 1)) {
            //global $request;
            //$user = $request->_user;
            //unset($user->_auth_dbi);
            // this must be done in $request->_setUser, not here!
            //$request->setSessionVar('wiki_user', $user);
            $this->getAuthDbh();
            $packed = $this->_prefs->store();
            if (!$id_only and isset($this->_prefs->_update)) {
                $dbh = &$this->_auth_dbi;
                $dbh->simpleQuery(sprintf($this->_prefs->_update,
                                          $dbh->quote($packed),
                                          $dbh->quote($this->_userid)));
                //delete pageprefs:
                if ($this->_HomePagehandle and $this->_HomePagehandle->get('pref'))
                    $this->_HomePagehandle->set('pref', '');
            } else {
                //store prefs in homepage, not in cookie
                if ($this->_HomePagehandle and !$id_only)
                    $this->_HomePagehandle->set('pref', $packed);
            }
            return $count; //count($this->_prefs->unpack($packed));
        }
        return 0;
    }

    function userExists() {
        //global $DBAuthParams;
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        if (!$dbh) { // needed?
            return $this->_tryNextUser();
        }
        if (!$this->isValidName()) {
            return $this->_tryNextUser();
        }
        $dbi =& $GLOBALS['request']->_dbi;
        // Prepare the configured auth statements
        if ($dbi->getAuthParam('auth_check') and empty($this->_authselect)) {
            $this->_authselect = $this->prepare($dbi->getAuthParam('auth_check'), 
                                                array("userid", "password"));
        }
        if (empty($this->_authselect))
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_CHECK', 'SQL'),
                          E_USER_WARNING);
        //NOTE: for auth_crypt_method='crypt' no special auth_user_exists is needed
        if ($this->_auth_crypt_method == 'crypt') {
            $rs = $dbh->query(sprintf($this->_authselect, $dbh->quote($this->_userid)));
            if ($rs->numRows())
                return true;
        }
        else {
            if (! $dbi->getAuthParam('auth_user_exists'))
                trigger_error(fmt("%s is missing",'DBAUTH_AUTH_USER_EXISTS'),
                              E_USER_WARNING);
            $this->_authcheck = $this->prepare($dbi->getAuthParam('auth_user_exists'),"userid");
            $rs = $dbh->query(sprintf($this->_authcheck, $dbh->quote($this->_userid)));
            if ($rs->numRows())
                return true;
        }
        // maybe the user is allowed to create himself. Generally not wanted in 
        // external databases, but maybe wanted for the wiki database, for performance 
        // reasons
        if (empty($this->_authcreate) and $dbi->getAuthParam('auth_create')) {
            $this->_authcreate = $this->prepare($dbi->getAuthParam('auth_create'),
                                                array("userid", "password"));
        }
        if (!empty($this->_authcreate) and isset($GLOBALS['HTTP_POST_VARS']['auth']['passwd'])) {
            $passwd = $GLOBALS['HTTP_POST_VARS']['auth']['passwd'];
            $dbh->simpleQuery(sprintf($this->_authcreate,
                                      $dbh->quote($passwd),
                                      $dbh->quote($this->_userid)
                                      ));
            return true;
        }
        return $this->_tryNextUser();
    }
 
    function checkPass($submitted_password) {
        //global $DBAuthParams;
        $this->getAuthDbh();
        if (!$this->_auth_dbi) {  // needed?
            return $this->_tryNextPass($submitted_password);
        }
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        if (!isset($this->_authselect))
            $this->userExists();
        if (!isset($this->_authselect))
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_CHECK', 'SQL'),
                          E_USER_WARNING);

        //NOTE: for auth_crypt_method='crypt'  defined('ENCRYPTED_PASSWD',true) must be set
        $dbh = &$this->_auth_dbi;
        if ($this->_auth_crypt_method == 'crypt') {
            $stored_password = $dbh->getOne(sprintf($this->_authselect, 
                                                    $dbh->quote($this->_userid)));
            $result = $this->_checkPass($submitted_password, $stored_password);
        } else {
            $okay = $dbh->getOne(sprintf($this->_authselect,
                                         $dbh->quote($submitted_password),
                                         $dbh->quote($this->_userid)));
            $result = !empty($okay);
        }

        if ($result) {
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        } else {
            return $this->_tryNextPass($submitted_password);
        }
    }

    function mayChangePass() {
        return $GLOBALS['request']->_dbi->getAuthParam('auth_update');
    }

    function storePass($submitted_password) {
        if (!$this->isValidName()) {
            return false;
        }
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        $dbi =& $GLOBALS['request']->_dbi;
        if ($dbi->getAuthParam('auth_update') and empty($this->_authupdate)) {
            $this->_authupdate = $this->prepare($dbi->getAuthParam('auth_update'),
                                                array("userid", "password"));
        }
        if (empty($this->_authupdate)) {
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_UPDATE','SQL'),
                          E_USER_WARNING);
            return false;
        }

        if ($this->_auth_crypt_method == 'crypt') {
            if (function_exists('crypt'))
                $submitted_password = crypt($submitted_password);
        }
        $dbh->simpleQuery(sprintf($this->_authupdate,
                                  $dbh->quote($submitted_password),
        			  $dbh->quote($this->_userid)
                                  ));
        return true;
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.1  2004/11/01 10:43:58  rurban
// seperate PassUser methods into seperate dir (memory usage)
// fix WikiUser (old) overlarge data session
// remove wikidb arg from various page class methods, use global ->_dbi instead
// ...
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>