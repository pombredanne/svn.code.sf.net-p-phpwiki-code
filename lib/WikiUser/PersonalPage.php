<?php //-*-php-*-
rcs_id('$Id: PersonalPage.php,v 1.2 2004-11-05 20:53:36 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
 */

/**
 * This class is only to simplify the auth method dispatcher.
 * It inherits almost all all methods from _PassUser.
 */
class _PersonalPagePassUser
extends _PassUser
{
    var $_authmethod = 'PersonalPage';

    function userExists() {
        return $this->_HomePagehandle and $this->_HomePagehandle->exists();
    }

    /** A PersonalPagePassUser requires PASSWORD_LENGTH_MINIMUM.
     *  BUT if the user already has a homepage with an empty password 
     *  stored, allow login but warn him to change it.
     */
    function checkPass($submitted_password) {
        if ($this->userExists()) {
            $stored_password = $this->_prefs->get('passwd');
            if (empty($stored_password)) {
            	if (PASSWORD_LENGTH_MINIMUM > 0) {
                  trigger_error(sprintf(
                    _("PersonalPage login method:\n").
                    _("You stored an empty password in your '%s' page.\n").
                    _("Your access permissions are only for a BogoUser.\n").
                    _("Please set a password in UserPreferences."),
                                        $this->_userid), E_USER_WARNING);
                  $this->_level = WIKIAUTH_BOGO;
            	} else {
                  trigger_error(sprintf(
                    _("PersonalPage login method:\n").
                    _("You stored an empty password in your '%s' page.\n").
                    _("Given password ignored.\n").
                    _("Please set a password in UserPreferences."),
                                        $this->_userid), E_USER_WARNING);
                  $this->_level = WIKIAUTH_USER;
            	}
                return $this->_level;
            }
            if ($this->_checkPass($submitted_password, $stored_password))
                return ($this->_level = WIKIAUTH_USER);
            return _PassUser::checkPass($submitted_password);
        }
        return WIKIAUTH_ANON;
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