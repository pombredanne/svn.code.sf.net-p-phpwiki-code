<?php //-*-php-*-
rcs_id('$Id: IMAP.php,v 1.1 2004-11-01 10:43:58 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
 */

class _IMAPPassUser
extends _PassUser
/**
 * Define the var IMAP_AUTH_HOST in config/config.ini (with port probably)
 *
 * Preferences are handled in _PassUser
 */
{
    function checkPass($submitted_password) {
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        $userid = $this->_userid;
        $mbox = @imap_open( "{" . IMAP_AUTH_HOST . "}",
                            $userid, $submitted_password, OP_HALFOPEN );
        if ($mbox) {
            imap_close($mbox);
            $this->_authmethod = 'IMAP';
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        } else {
            trigger_error(_("Unable to connect to IMAP server "). IMAP_AUTH_HOST, 
                          E_USER_WARNING);
        }

        return $this->_tryNextPass($submitted_password);
    }

    //CHECKME: this will not be okay for the auth policy strict
    function userExists() {
        return true;

        if (checkPass($this->_prefs->get('passwd')))
            return true;
        return $this->_tryNextUser();
    }

    function mayChangePass() {
        return false;
    }
}

// $Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>