<?php rcs_id('$Id: WikiUser.php,v 1.10 2002-01-19 07:21:58 dairiki Exp $');

// It is anticipated that when userid support is added to phpwiki,
// this object will hold much more information (e-mail, home(wiki)page,
// etc.) about the user.
   
// There seems to be no clean way to "log out" a user when using
// HTTP authentication.
// So we'll hack around this by storing the currently logged
// in username and other state information in a cookie.

define('WIKIAUTH_ANON', 0);
define('WIKIAUTH_BOGO', 1);
define('WIKIAUTH_USER', 2);     // currently unused.
define('WIKIAUTH_ADMIN', 10);

class WikiUser 
{
    var $_userid = false;
    var $_level  = false;

    /**
     * Constructor.
     */
    function WikiUser (&$request) {
        $this->_request = &$request;

        // Restore from session state.
        $this->_restore();

        $login_args = $request->getArg('login');
        if ($login_args) {
            $request->setArg('login', false);
            if ($request->get('REQUEST_METHOD') == 'POST')
                $this->_handleLoginPost($login_args);
        }
    }

    function _handleLoginPost ($postargs) {
        if (!is_array($postargs))
            return;

        $keys = array('userid', 'password', 'require_level', 'login', 'logout', 'cancel');
        foreach ($keys as $key) 
            $args[$key] = isset($postargs[$key]) ? $postargs[$key] : false;
        extract($args);
        $require_level = max(0, min(WIKIAUTH_ADMIN, (int) $require_level));

        if ($logout) {
            // logout button
            $this->logout();
            return;
        }
        if ($cancel)
            return;             // user hit cancel button.
        if (!$login && !$userid)
            return;
        
        if ($this->attemptLogin($userid, $password, $require_level))
            return;             // login succeeded

        if ($this->_pwcheck($userid, $password))
            $failmsg = _("Insufficient permissions.");
        elseif ($password !== false)
            $failmsg = _("Invalid password or userid.");
        else
            $failmsg = '';

        $this->showLoginForm($require_level, $userid, $failmsg);
    }
        
    /**
     */
    function requireAuth ($require_level) {
        if ($require_level > $this->_level)
            $this->showLoginForm($require_level);
    }
    
    function showLoginForm ($require_level = 0, $default_user = false, $fail_message = '') {

        include_once('lib/Template.php');
        
        $login = new WikiTemplate('login');

        $login->qreplace('REQUIRE', $require_level);
        
        if (!empty($default_user))
            $login->qreplace('DEFAULT_USERID', $default_user);
        elseif (!empty($this->_failed_userid))
            $login->qreplace('DEFAULT_USERID', $this->_failed_userid);

        if ($fail_message)
            $login->qreplace('FAILURE_MESSAGE', $fail_message);

        // FIXME: Need message: You must sign/log in before you can '%s' '%s'.
        $top = new WikiTemplate('top');
        $top->replace('TITLE', _("Sign In"));
        $top->replace('HEADER', _("Please Sign In"));

        $top->printExpansion($login);
        ExitWiki();
    }
      
    /**
     * Logout the current user (if any).
     */
    function logout () {
        $this->_level = false;
        $this->_userid = false;
        $this->_save();
    }

    /**
     * Attempt to log in.
     *
     * @param $userid string Username.
     * @param $password string Password.
     * @return bool True iff log in was successful.
     */
    function attemptLogin ($userid, $password = false, $require_level = 0) {
        $level = $this->_pwcheck ($userid, $password);
        if ($level === false) {
            // bad password
            return false;
        }
        if ($level < $require_level) {
            // insufficient access
            return false;
        }
        
        // Success!
        $this->_login($userid, $level);
        return $this->isSignedIn();
    }

        
            
    function getId () {
        return ( $this->isSignedIn()
                 ? $this->_userid
                 : $this->_request->get('REMOTE_ADDR') ); 
    }

    function getAuthenticatedId() {
        return ( $this->isAuthenticated()
                 ? $this->_userid
                 : $this->_request->get('REMOTE_ADDR') ); 
    }

    function isSignedIn () {
        return $this->_level >= WIKIAUTH_BOGO;
    }
        
    function isAuthenticated () {
        return $this->_level >= WIKIAUTH_USER;
    }
	 
    function isAdmin () {
        return $this->_level == WIKIAUTH_ADMIN;
    }

    /**
     * Login with given access level
     *
     * No check for correct password is done.
     */
    function _login ($userid, $level = WIKIAUTH_BOGO) {
        $this->_userid = $userid;
        $this->_level = $level;
        $this->_save();
    }

    /**
     * Check password.
     */
    function _pwcheck ($userid, $passwd) {
        global $WikiNameRegexp;
        
        if (!empty($userid) && $userid == ADMIN_USER) {
            if (!empty($passwd) && $passwd == ADMIN_PASSWD)
                return WIKIAUTH_ADMIN;
            return false;
        }
        elseif (ALLOW_BOGO_LOGIN
                && preg_match('/\A' . $WikiNameRegexp . '\z/', $userid)) {
            return WIKIAUTH_BOGO;
        }
        return false;
    }
    


    // This is a bit of a hack:
    function setPreferences ($prefs) {
        $req = &$this->_request;
        $req->setCookieVar('WIKI_PREFS', $prefs, 365); // expire in a year.
    }

    function getPreferences () {
        $req = &$this->_request;

        $prefs = array('edit_area.width' => 80,
                       'edit_area.height' => 22);

        $saved = $req->getCookieVar('WIKI_PREFS');
        
        if (is_array($saved)) {
            foreach ($saved as $key => $val) {
                if (isset($prefs[$key]) && !empty($val))
                    $prefs[$key] = $val;
            }
        }

        // Some sanity checks. (FIXME: should move somewhere else)
        if (!($prefs['edit_area.width'] >= 30 && $prefs['edit_area.width'] <= 150))
            $prefs['edit_area.width'] = 80;
        if (!($prefs['edit_area.height'] >= 5 && $prefs['edit_area.height'] <= 80))
            $prefs['edit_area.height'] = 22;
        return $prefs;
    }
   


    function _copy($saved) {
        if (!is_array($saved) || !isset($saved['userid']) || !isset($saved['level']))
            return false;

        $this->_userid = $saved['userid'];
        $this->_level = $saved['level'];
        return true;
    }
       
    function _restore () {
        $req = &$this->_request;
        
        if ( $this->_copy($req->getSessionVar('auth_state')) )
            return;
        if ( $this->_ok() )
            return;
        
        // Default state: logged out.
        $this->_userid = false;
        $this->_level = false;
    }

    function _save () {
        $req = &$this->_request;

        
        $saved = array('userid' => $this->_userid,
                       'level' => $this->_level);
        
        $req->setSessionVar('auth_state', $saved);
    }

    /** Invariant
     */
    function _ok () {
        if (empty($this->_userid) || empty($this->_level)) {
            // This is okay if truly logged out.
            return $this->_userid === false && $this->_level === false;
        }
        // User is logged in...
        
        // Check for valid authlevel.
        if (!in_array($this->_level, array(WIKIAUTH_BOGO, WIKIAUTH_USER, WIKIAUTH_ADMIN)))
            return false;

        // Check for valid userid.
        if (!is_string($this->_userid))
            return false;
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
