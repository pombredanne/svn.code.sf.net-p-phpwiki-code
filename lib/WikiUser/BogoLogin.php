<?php //-*-php-*-
rcs_id('$Id$');
/* Copyright (C) 2004 ReiniUrban
 * This file is part of PhpWiki. Terms and Conditions see LICENSE. (GPL2)
 */

/** Without stored password. A _BogoLoginPassUser with password 
 *  is automatically upgraded to a PersonalPagePassUser.
 */
class _BogoLoginPassUser extends _PassUser {

    var $_authmethod = 'BogoLogin';
    
    function userExists() {
        if (isWikiWord($this->_userid)) {
            $this->_level = WIKIAUTH_BOGO;
            return true;
        } else {
            $this->_level = WIKIAUTH_ANON;
            return false;
        }
    }

    /** A BogoLoginUser requires no password at all
     *  But if there's one stored, we override it with the PersonalPagePassUser instead
     */
    function checkPass($submitted_password) {
        if ($this->_prefs->get('passwd')) {
            if (isset($this->_prefs->_method) and $this->_prefs->_method == 'HomePage') {
                $user = new _PersonalPagePassUser($this->_userid, $this->_prefs);
                if ($user->checkPass($submitted_password)) {
                    if (!check_php_version(5))
                        eval("\$this = \$user;");
                    // /*PHP5 patch*/$this = $user;
                    $user = UpgradeUser($this, $user);
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                } else {
                    $this->_level = WIKIAUTH_ANON;
                    return $this->_level;
                }
            } else {
                $stored_password = $this->_prefs->get('passwd');
                if ($this->_checkPass($submitted_password, $stored_password)) {
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                } elseif (USER_AUTH_POLICY === 'strict') {
                    $this->_level = WIKIAUTH_FORBIDDEN;
                    return $this->_level;
                } else {
                    return $this->_tryNextPass($submitted_password);
                }
            }
        }
        if (isWikiWord($this->_userid)) {
            $this->_level = WIKIAUTH_BOGO;
        } else {
            $this->_level = WIKIAUTH_ANON;
        }
        return $this->_level;
    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
