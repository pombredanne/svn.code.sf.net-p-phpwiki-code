<?php rcs_id('$Id: WikiUser.php,v 1.5 2001-12-06 20:44:13 dairiki Exp $');

// It is anticipated that when userid support is added to phpwiki,
// this object will hold much more information (e-mail, home(wiki)page,
// etc.) about the user.
   
// There seems to be no clean way to "log out" a user when using
// HTTP authentication.
// So we'll hack around this by storing the currently logged
// in username and other state information in a cookie.
class WikiUser 
{
    // Arg $login_mode:
    //   default:  Anonymous users okay.
    //   'ANON_OK': Anonymous access is fine.
    //   'REQUIRE_AUTH': User must be authenticated.
    //   'LOGOUT':  Force logout.
    //   'LOGIN':   Force authenticated login.
    function WikiUser (&$request, $auth_mode = '') {
        $this->_request = &$request;
        // Restore from cookie.
        $this->_restore();

        // don't check for HTTP auth if there's nothing to worry about
        //
        // FIXME: the addition of this short-cut introduced a security hole.
        //        Since $this->_restore can potentially restore $this from a
        //        user provided cookie, a carefully constructed cookie can
        //        be used to effectively log in (even as admin) without
        //        a password.
        //
        //        For now, I'm disabling the code which saves/restores $this
        //        in a cookie.  (Login state is still preserved in session vars.)
        //        I'll work on a longer term solution.
        
        if (  $this->state == 'authorized' 
              && $auth_mode != 'LOGIN' 
              && $auth_mode != 'LOGOUT'  )
            return;   

        if ($this->state == 'authorized' && $auth_mode == 'LOGIN') {
            // ...logout
            $this->realm++;
            $this->state = 'loggedout';
        }
      
        if ($auth_mode != 'LOGOUT') {
            $user = $this->_get_authenticated_userid();

            if (!$user && $auth_mode != 'ANON_OK')
                $warning = $this->_demand_http_authentication(); //NORETURN
        }
        
        if (empty($user)) {
            // Authentication failed
            if ($this->state == 'authorized')
                $this->realm++;
            $this->state = 'loggedout';
            $this->userid = $request->get('REMOTE_HOST');
        }
        else {
            // Successful authentication
            $this->state = 'authorized';
            $this->userid = $user;
        }

        // Save state to cookie and/or session registry.
        $this->_save($request);

        if (isset($warning))
            echo $warning;
    }

    function id () {
        return $this->userid;
    }

    function authenticated_id() {
        if ($this->is_authenticated())
            return $this->id();
        else
            return $this->_request->get('REMOTE_ADDR');
    }

    function is_authenticated () {
        return $this->state == 'authorized';
    }
	 
    function is_admin () {
        return $this->is_authenticated() && $this->userid == ADMIN_USER;
    }

    function must_be_admin ($action = "") {
        if (! $this->is_admin()) 
            {
                if ($action)
                    $to_what = sprintf(gettext("to perform action '%s'"), $action);
                else
                    $to_what = gettext("to do that");
                ExitWiki(gettext("You must be logged in as an administrator")
                         . " $to_what");
            }
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
   
    function _get_authenticated_userid () {
        if ( ! ($user = $this->_get_http_authenticated_userid()) )
            return false;
       
        switch ($this->state) {
        case 'login':
            // Either we just asked for a password, or cookies are not enabled.
            // In either case, proceed with successful login.
            return $user;
        case 'loggedout':
            // We're logged out.  Ignore http authed user.
            return false;
        default:
            // FIXME: Can't reset auth cache on Mozilla (and probably others),
            // so for now, just trust the saved state
            return $this->userid;
          
            // Else, as long as the user hasn't changed, fine.
            if ($user && $user != $this->userid)
                return false;
            return $user;
        }
    }

    function _get_http_authenticated_userid () {
        global $WikiNameRegexp;

        $userid = $this->_request->get('PHP_AUTH_USER');
        $passwd = $this->_request->get('PHP_AUTH_PW');

        if (!empty($userid) && $userid == ADMIN_USER) {
            if (!empty($passwd) && $passwd == ADMIN_PASSWD)
                return $userid;
        }
        elseif (ALLOW_BOGO_LOGIN
                && preg_match('/\A' . $WikiNameRegexp . '\z/', $userid)) {
            // FIXME: this shouldn't count as authenticated.
            return $userid;
        }
        return false;
    }
   
    function _demand_http_authentication () {
        if (!defined('ADMIN_USER') || !defined('ADMIN_PASSWD')
            || ADMIN_USER == '' || ADMIN_PASSWD =='') {
            return
                "<p><b>"
                . gettext("You must set the administrator account and password before you can log in.")
                . "</b></p>\n";
        }

        // Request password
        $this->userid = '';
        $this->state = 'login';
      
        $this->_save();
        $request = &$this->_request;
        header('WWW-Authenticate: Basic realm="' . $this->realm . '"');
        $request->setStatus("HTTP/1.0 401 Unauthorized");
        echo "<p>" . gettext ("You entered an invalid login or password.") . "\n";
        if (ALLOW_BOGO_LOGIN) {
            echo "<p>";
            echo gettext ("You can log in using any valid WikiWord as a user ID.") . "\n";
            echo gettext ("(Any password will work, except, of course for the admin user.)") . "\n";
        }
      
        ExitWiki();
    }

    function _copy($object) {
        if (!is_object($object))
            return false;
        if (strtolower(get_class($object)) != 'wikiuser')
            return false;

        $this->userid = $object->userid;
        $this->state = $object->state;
        $this->realm = $object->realm;
        return true;
    }
       
    function _restore() {
        $req = &$this->_request;
        
        if ( $this->_copy($req->getSessionVar('auth_state')) )
            return;
        // FIXME: Disable restore from cookie (see note in WikiUser().)
        //elseif ( $this->_copy($req->getCookieVar('WIKI_AUTH')) )
        //    return;
        else {
            // Default state.
            $this->userid = '';
            $this->state = 'login';
            $this->realm = 'PhpWiki0000';
        }
    }

    function _save() {
        $req = &$this->_request;

        $req->setSessionVar('auth_state', $this);
        // FIXME: Disable restore from cookie (see note in WikiUser().)
        //$req->setCookieVar('WIKI_AUTH', $this);
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
