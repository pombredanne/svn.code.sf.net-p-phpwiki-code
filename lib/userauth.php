<?php rcs_id('$Id: userauth.php,v 1.7 2001-07-20 17:40:12 dairiki Exp $');

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
   function WikiUser ($auth_mode = '') {
      // Restore from cookie.
      global $WIKI_AUTH, $REMOTE_HOST, $REMOTE_ADDR;
      if (empty($WIKI_AUTH)) 
      {
	 $this->userid = '';
	 $this->state = 'login';
	 $this->realm = 'PhpWiki0000';
      }
      else
	 $this = unserialize(fix_magic_quotes_gpc($WIKI_AUTH));

      if ($this->state == 'authorized' && $auth_mode == 'LOGIN')
      {
	 // ...logout
	 $this->realm++;
	 $this->state = 'loggedout';
      }
      
      if ($auth_mode != 'LOGOUT')
      {
	 $user = $this->_get_authenticated_userid();

	 if (!$user && $auth_mode != 'ANON_OK')
	    $warning = $this->_demand_http_authentication(); //NORETURN
      }

      if (empty($user))
      {
	 // Authentication failed
	 if ($this->state == 'authorized')
	    $this->realm++;
	 $this->state = 'loggedout';
	 $this->userid = empty($REMOTE_HOST) ? $REMOTE_ADDR : $REMOTE_HOST;
      }
      else
      {
	 // Successful authentication
	 $this->state = 'authorized';
	 $this->userid = $user;
      }

      // Save state to cookie.
      setcookie('WIKI_AUTH', serialize($this), 0, '/');
      if (isset($warning))
	 echo $warning;
   }

   function id () {
      return $this->userid;
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
      global $WIKI_PREFS;
      $WIKI_PREFS = serialize($prefs);
      $expires = time() + 365 * 24 * 3600; // expire in a year
      setcookie('WIKI_PREFS', $WIKI_PREFS, $expires, '/');
   }

   function getPreferences () {
      $prefs = array('edit_area.width' => 80,
		     'edit_area.height' => 22);

      $prefcookie = unserialize(fix_magic_quotes_gpc($GLOBALS['WIKI_PREFS']));

      while (list($k, $v) = each($prefs))
	 if (!empty($prefcookie[$k]))
	    $prefs[$k] = $prefcookie[$k];

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
	 // Else, as long as the user hasn't changed, fine.
	 if ($user && $user != $this->userid)
	    return false;
	 return $user;
      }
   }

   function _get_http_authenticated_userid () {
      global $PHP_AUTH_USER, $PHP_AUTH_PW;
      global $WikiNameRegexp;
      
      if (empty($PHP_AUTH_USER))
	 return false;

      if ($PHP_AUTH_USER == ADMIN_USER) {
	 if (empty($PHP_AUTH_PW) || $PHP_AUTH_PW != ADMIN_PASSWD)
	    return false;
      }
      else if (! ALLOW_BOGO_LOGIN) {
	 return false;
      }
      else if (! preg_match('/\A' . $WikiNameRegexp . '\z/', $PHP_AUTH_USER)) { 	
	 return false;
      }
	 
      return $PHP_AUTH_USER;
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
      setcookie('WIKI_AUTH', serialize($this), 0, '/');
      header('WWW-Authenticate: Basic realm="' . $this->realm . '"');
      header("HTTP/1.0 401 Unauthorized");
      if (ACCESS_LOG)
	 $LogEntry->status = 401;
      echo "<p>" . gettext ("You entered an invalid login or password.") . "\n";
      if (ALLOW_BOGO_LOGIN) {
	 echo "<p>";
	 echo gettext ("You can log in using any valid WikiWord as a user ID.") . "\n";
	 echo gettext ("(Any password will work, except, of course for the admin user.)") . "\n";
      }
      
      ExitWiki();
   }
}

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
