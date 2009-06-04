<?php //-*-php-*-
rcs_id('$Id$');
/* Copyright (C) 2007 ReiniUrban
 * This file is part of PhpWiki. Terms and Conditions see LICENSE. (GPL2)
 *
 * See http://openid.net/specs/openid-authentication-1_1.html
 */

class _OpenIDPassUser
extends _PassUser
/**
 * Preferences are handled in _PassUser
 */
{
    // This can only be called from _PassUser, because the parent class 
    // sets the pref methods, before this class is initialized.
    function _OpenIDPassUser($UserName='', $prefs=false, $file='') {
        if (!$this->_prefs and isa($this, "_OpenIDPassUser")) {
            if ($prefs) $this->_prefs = $prefs;
            if (!isset($this->_prefs->_method))
              _PassUser::_PassUser($UserName);
        }
        $this->_userid = $UserName;
        return $this;
    }

    function userExists() {
        if (!$this->isValidName($this->_userid)) {
            return $this->_tryNextUser();
        }
        $this->_authmethod = 'OpenID';
        // check the prefs for emailVerified
        if ($this->_prefs->get('emailVerified'))
            return true;
        return $this->_tryNextUser();
    }
}

// $Log: OpenID.php,v $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>