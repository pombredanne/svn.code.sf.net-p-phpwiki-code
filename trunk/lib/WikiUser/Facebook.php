<?php //-*-php-*-
rcs_id('$Id: Facebook.php 6184 2008-08-22 10:33:41Z vargenau $');
/* Copyright (C) 2009 Reini Urban
 * This file is part of PhpWiki. Terms and Conditions see LICENSE. (GPL2)
 *
 * From http://developeronline.blogspot.com/2008/10/using-perl-against-facebook-part-i.html:
 * GET 'http://www.facebook.com/login.php', and rest our virtual browser there to collect the cookies
 * POST to 'https://login.facebook.com/login.php' with the proper parameters
 */

// requires the openssl extension
require_once("lib/HttpClient.php");

class _FacebookPassUser
extends _PassUser {
    /**
     * Preferences are handled in _PassUser
     */
    function checkPass($password) {
        $userid = $this->_userid;
        if (!loadPhpExtension('openssl')) {
            trigger_error(_("The PECL openssl extension cannot be loaded"),
                          E_USER_WARNING);
            return $this->_tryNextUser();
        }
        $web = new HttpClient("www.facebook.com", 80);
        if (DEBUG & _DEBUG_LOGIN) $web->setDebug(true);
        // collect cookies from http://www.facebook.com/login.php
        $firstlogin = $web->get("/login.php");
        if (!$firstlogin) {
            trigger_error(_("Facebook connect failed with %d %s", $this->status, $this->errormsg),
                          E_USER_WARNING);
        }
        // Switch from http to https://login.facebook.com/login.php
        $web->post = 443;
        if (!$web->post("/login.php", array('user'=>$userid, 'pass'=>$password))) {
            trigger_error(_("Facebook login failed with %d %s", $this->status, $this->errormsg),
                          E_USER_WARNING);
        }
        $this->_authmethod = 'Facebook';
	if (DEBUG & _DEBUG_LOGIN) trigger_error(get_class($this)."::checkPass => $retval",
                                                E_USER_WARNING);
        if ($retval) {
            $this->_level = WIKIAUTH_USER;
        } else {
            $this->_level = WIKIAUTH_ANON;
        }
        return $this->_level;
    }

    function userExists() {
	if (DEBUG & _DEBUG_LOGIN) trigger_error(get_class($this)."::userExists => true (dummy)", E_USER_WARNING);
        return true;
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