<?php //-*-php-*-
rcs_id('$Id: LDAP.php,v 1.1 2004-11-01 10:43:58 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
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
                trigger_error(sprintf("Unable to bind LDAP server %s", LDAP_AUTH_HOST), 
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
            return $this->_tryNextPass($submitted_password);
        }
        if (strstr($userid,'*')) {
            trigger_error(fmt("Invalid username '%s' for LDAP Auth",$userid), 
                          E_USER_WARNING);
            return WIKIAUTH_FORBIDDEN;
        }

        if ($ldap = $this->_init()) {
            // Need to set the right root search information. See config/config.ini
            $st_search = LDAP_SEARCH_FIELD
                ? LDAP_SEARCH_FIELD."=$userid"
                : "uid=$userid";
            if (!$this->_sr = ldap_search($ldap, LDAP_BASE_DN, $st_search)) {
 		$this->_free();
                return $this->_tryNextPass($submitted_password);
            }
            $info = ldap_get_entries($ldap, $this->_sr); 
            if (empty($info["count"])) {
            	$this->_free();
                return $this->_tryNextPass($submitted_password);
            }
            // There may be more hits with this userid.
            // Of course it would be better to narrow down the BASE_DN
            for ($i = 0; $i < $info["count"]; $i++) {
                $dn = $info[$i]["dn"];
                // The password is still plain text.
                // On wrong password the ldap server will return: 
                // "Unable to bind to server: Server is unwilling to perform"
                // The @ catches this error message.
                if ($r = @ldap_bind($ldap, $dn, $submitted_password)) {
                    // ldap_bind will return TRUE if everything matches
            	    $this->_free();
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                }
            }
            $this->_free();
        }

        return $this->_tryNextPass($submitted_password);
    }

    function userExists() {
        $userid = $this->_userid;
        if (strstr($userid,'*')) {
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

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>