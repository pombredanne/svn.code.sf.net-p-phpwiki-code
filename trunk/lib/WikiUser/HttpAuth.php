<?php //-*-php-*-
rcs_id('$Id: HttpAuth.php,v 1.1 2004-11-01 10:43:58 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
 */

/**
 * We have two possibilities here.
 * 1) The webserver location is already HTTP protected (usually Basic). Then just 
 *    use the username and do nothing
 * 2) The webserver location is not protected, so we enforce basic HTTP Protection
 *    by sending a 401 error and let the client display the login dialog.
 *    This makes only sense if HttpAuth is the last method in USER_AUTH_ORDER,
 *    since the other methods cannot be transparently called after this enforced 
 *    external dialog.
 *    Try the available auth methods (most likely Bogo) and sent this header back.
 *    header('Authorization: Basic '.base64_encode("$userid:$passwd")."\r\n";
 */
class _HttpAuthPassUser
extends _PassUser
{
    function _HttpAuthPassUser($UserName='',$prefs=false) {
        if ($prefs) $this->_prefs = $prefs;
        if (!isset($this->_prefs->_method))
           _PassUser::_PassUser($UserName);
        if ($UserName) $this->_userid = $UserName;
        $this->_authmethod = 'HttpAuth';
        if ($this->userExists())
            return $this;
        else 
            return $GLOBALS['ForbiddenUser'];
    }

    function _http_username() {
        if (!isset($_SERVER))
            $_SERVER =& $GLOBALS['HTTP_SERVER_VARS'];
	if (!empty($_SERVER['PHP_AUTH_USER']))
	    return $_SERVER['PHP_AUTH_USER'];
	if (!empty($_SERVER['REMOTE_USER']))
	    return $_SERVER['REMOTE_USER'];
        if (!empty($GLOBALS['HTTP_ENV_VARS']['REMOTE_USER']))
	    return $GLOBALS['HTTP_ENV_VARS']['REMOTE_USER'];
	if (!empty($GLOBALS['REMOTE_USER']))
	    return $GLOBALS['REMOTE_USER'];
	return '';
    }
    
    //force http auth authorization
    function userExists() {
        // todo: older php's
        $username = $this->_http_username();
        if (empty($username) or strtolower($username) != strtolower($this->_userid)) {
            header('WWW-Authenticate: Basic realm="'.WIKI_NAME.'"');
            header('HTTP/1.0 401 Unauthorized'); 
            exit;
        }
        $this->_userid = $username;
        // we should check if he is a member of admin, 
        // because HttpAuth has its own logic.
        $this->_level = WIKIAUTH_USER;
        if ($this->isAdmin())
            $this->_level = WIKIAUTH_ADMIN;
        return $this;
    }
        
    function checkPass($submitted_password) {
        return $this->userExists() 
            ? ($this->isAdmin() ? WIKIAUTH_ADMIN : WIKIAUTH_USER)
            : WIKIAUTH_ANON;
    }

    function mayChangePass() {
        return false;
    }

    // hmm... either the server dialog or our own.
    function PrintLoginForm (&$request, $args, $fail_message = false,
                             $seperate_page = true) {
        header('WWW-Authenticate: Basic realm="'.WIKI_NAME.'"');
        header('HTTP/1.0 401 Unauthorized'); 
        exit;
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