<?php //-*-php-*-
rcs_id('$Id: LDAP.php,v 1.8 2007-06-07 16:31:33 rurban Exp $');
/* Copyright (C) 2004,2007 $ThePhpWikiProgrammingTeam
 * This file is part of PhpWiki. Terms and Conditions see LICENSE. (GPL2)
 */

class _LDAPPassUser
extends _PassUser
/**
 * Define the vars LDAP_AUTH_HOST and LDAP_BASE_DN in config/config.ini
 *
 * Preferences are handled in _PassUser
 */
{
    function _init() {
        if ($this->_ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            global $LDAP_SET_OPTION;
            if (!empty($LDAP_SET_OPTION)) {
                foreach ($LDAP_SET_OPTION as $key => $value) {
                    //if (is_string($key) and defined($key))
                    //    $key = constant($key);
                    ldap_set_option($this->_ldap, $key, $value);
                }
            }
            if (LDAP_AUTH_USER)
                if (LDAP_AUTH_PASSWORD)
                    // Windows Active Directory Server is strict
                    $r = ldap_bind($this->_ldap, LDAP_AUTH_USER, LDAP_AUTH_PASSWORD); 
                else
                    $r = ldap_bind($this->_ldap, LDAP_AUTH_USER); 
            else
                $r = true; // anonymous bind allowed
            if (!$r) {
                $this->_free();
                trigger_error(sprintf(_("Unable to bind LDAP server %s using %s %s"),
				      LDAP_AUTH_HOST, LDAP_AUTH_USER, LDAP_AUTH_PASSWORD), 
                              E_USER_WARNING);
                return false;
            }
            return $this->_ldap;
        } else {
            return false;
        }
    }
    
    function _free() {
        if (isset($this->_sr)   and is_resource($this->_sr))   ldap_free_result($this->_sr);
        if (isset($this->_ldap) and is_resource($this->_ldap)) ldap_close($this->_ldap);
        unset($this->_sr);
        unset($this->_ldap);
    }

    function checkPass($submitted_password) {

        $this->_authmethod = 'LDAP';
        $userid = $this->_userid;
        if (!$this->isValidName()) {
            trigger_error(_("Invalid username."), E_USER_WARNING);
            $this->_free();
            return $this->_tryNextPass($submitted_password);
        }
        if (!$this->_checkPassLength($submitted_password)) {
            $this->_free();
            return WIKIAUTH_FORBIDDEN;
        }
        // A LDAP speciality: empty passwords are valid with ldap_bind!!!
        if (strlen($password) == 0) {
            trigger_error(_("Empty password not allowed for LDAP"), E_USER_WARNING);
            $this->_free();
            return WIKIAUTH_FORBIDDEN;
        }
        if (strstr($userid,'*')) {
            trigger_error(fmt("Invalid username '%s' for LDAP Auth", $userid), 
                          E_USER_WARNING);
            return WIKIAUTH_FORBIDDEN;
        }

        if ($ldap = $this->_init()) {
            // Need to set the right root search information. See config/config.ini
            $st_search = LDAP_SEARCH_FIELD
                ? LDAP_SEARCH_FIELD."=$userid"
                : "uid=$userid";
            if (!$this->_sr = ldap_search($ldap, LDAP_BASE_DN, $st_search)) {
		trigger_error(_("Could not search in LDAP"), E_USER_WARNING);
 		$this->_free();
                return $this->_tryNextPass($submitted_password);
            }
            $info = ldap_get_entries($ldap, $this->_sr); 
            if (empty($info["count"])) {
		if (DEBUG)
		    trigger_error(_("User not found in LDAP"), E_USER_WARNING);
            	$this->_free();
                return $this->_tryNextPass($submitted_password);
            }
            // There may be more hits with this userid.
            // Of course it would be better to narrow down the BASE_DN
            for ($i = 0; $i < $info["count"]; $i++) {
                $dn = $info[$i]["dn"];
                // The password is still plain text.
		// LDAP allows all chars but *, (, ), \, NUL
		// Quoting is done by \xx (two-digit hexcode). * <=> \2a
		// Handling '?' is unspecified
		$password = strtr($submitted_password, 
				array("*" => "\\2a",
				      "?" => "\\3f",
				      "(" => "\\28",
				      ")" => "\\29",
				      "\\" => "\\5c",
				      "\0" => "\\00"));
                // On wrong password the ldap server will return: 
                // "Unable to bind to server: Server is unwilling to perform"
                // The @ catches this error message.
                if ($r = @ldap_bind($ldap, $dn, $password)) {
                    // ldap_bind will return TRUE if everything matches
		    // Get the mail from ldap
		    if (!empty($info[$i]["mail"][0])) {
			$this->_prefs->_prefs['email']->default_value = $info[$i]["mail"][0];
		    }
            	    $this->_free();
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                }
            }
	    if (DEBUG)
		trigger_error(_("Wrong password: ") . 
			      str_repeat("*",strlen($submitted_password)), 
			      E_USER_WARNING);
            $this->_free();
        } else {
            $this->_free();
	    trigger_error(_("Could not connect to LDAP"), E_USER_WARNING);
	}

        return $this->_tryNextPass($submitted_password);
    }


    function isValidName ($userid = false) {
        if (!$userid) $userid = $this->_userid;
	// LDAP allows all chars but *, (, ), \, NUL
	// Quoting is done by \xx (two-digit hexcode). * <=> \2a
	// We are more restrictive here, but must allow explitly utf-8
        return preg_match("/^[\-\w_\.@ ]+$/u", $userid) and strlen($userid) < 64;
    }

    function userExists() {
        $userid = $this->_userid;
        if (strstr($userid, '*')) {
            trigger_error(fmt("Invalid username '%s' for LDAP Auth", $userid),
                          E_USER_WARNING);
            return false;
        }
        if ($ldap = $this->_init()) {
            // Need to set the right root search information. see ../index.php
            $st_search = LDAP_SEARCH_FIELD
                ? LDAP_SEARCH_FIELD."=$userid"
                : "uid=$userid";
            if (!$this->_sr = ldap_search($ldap, LDAP_BASE_DN, $st_search)) {
 		$this->_free();
        	return $this->_tryNextUser();
            }
            $info = ldap_get_entries($ldap, $this->_sr); 

            if ($info["count"] > 0) {
         	$this->_free();
		UpgradeUser($GLOBALS['ForbiddenUser'], $this);
                return true;
            }
        }
 	$this->_free();
        return $this->_tryNextUser();
    }

    function mayChangePass() {
        return false;
    }

}

// $Log: not supported by cvs2svn $
// Revision 1.7  2007/05/30 21:56:17  rurban
// Back to default uid for LDAP
//
// Revision 1.6  2007/05/29 16:56:15  rurban
// Allow more password und userid chars. uid => cn: default for certain testusers
//
// Revision 1.5  2005/10/10 19:43:49  rurban
// add DBAUTH_PREF_INSERT: self-creating users. by John Stevens
//
// Revision 1.4  2004/12/26 17:11:17  rurban
// just copyright
//
// Revision 1.3  2004/12/20 16:05:01  rurban
// gettext msg unification
//
// Revision 1.2  2004/12/19 00:58:02  rurban
// Enforce PASSWORD_LENGTH_MINIMUM in almost all PassUser checks,
// Provide an errormessage if so. Just PersonalPage and BogoLogin not.
// Simplify httpauth logout handling and set sessions for all methods.
// fix main.php unknown index "x" getLevelDescription() warning.
//
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
