<?php //-*-php-*-
rcs_id('$Id: WikiUser.php,v 1.36 2003-02-21 22:50:51 dairiki Exp $');

// It is anticipated that when userid support is added to phpwiki,
// this object will hold much more information (e-mail,
// home(wiki)page, etc.) about the user.

// There seems to be no clean way to "log out" a user when using HTTP
// authentication. So we'll hack around this by storing the currently
// logged in username and other state information in a cookie.

// 2002-09-08 11:44:04 rurban
// Todo: Fix prefs cookie/session handling:
//       _userid and _homepage cookie/session vars still hold the
//       serialized string.
//       If no homepage, fallback to prefs in cookie as in 1.3.3.

define('WIKIAUTH_ANON', 0);
define('WIKIAUTH_BOGO', 1);     // any valid WikiWord is enough
define('WIKIAUTH_USER', 2);     // real auth from a database/file/server.

define('WIKIAUTH_ADMIN', 10);
define('WIKIAUTH_FORBIDDEN', 11); // Completely not allowed.

$UserPreferences = array(
                         'userid'        => new _UserPreference(''), // really store this also?
                         'passwd'        => new _UserPreference(''),
                         'email'         => new _UserPreference(''),
                         'emailVerified' => new _UserPreference_bool(),
                         'notifyPages'   => new _UserPreference(''),
                         'theme'         => new _UserPreference_theme(THEME),
                         'lang'          => new _UserPreference_language(DEFAULT_LANGUAGE),
                         'editWidth'     => new _UserPreference_int(80, 30, 150),
                         'editHeight'    => new _UserPreference_int(22, 5, 80),
                         'timeOffset'    => new _UserPreference_numeric(0, -26, 26),
                         'relativeDates' => new _UserPreference_bool()
                         );

/**
* 
*/
class WikiUser {
    var $_userid = false;
    var $_level  = false;
    var $_request, $_dbi, $_authdbi, $_homepage;
    var $_authmethod = '', $_authhow = '';

    /**
     * Constructor.
     * 
     * Populates the instance variables and calls $this->_ok() 
     * to ensure that the parameters are valid.
     * @param mixed $userid String of username or WikiUser object.
     * @param integer $authlevel Authorization level.
     */
    function WikiUser (&$request, $userid = false, $authlevel = false) {
        $this->_request = &$request;
        $this->_dbi = &$this->_request->getDbh();

        if (isa($userid, 'WikiUser')) {
            $this->_userid   = $userid->_userid;
            $this->_level    = $userid->_level;
        }
        else {
            $this->_userid = $userid;
            $this->_level = $authlevel;
        }
        if (!$this->_ok()) {
            // Paranoia: if state is at all inconsistent, log out...
            $this->_userid = false;
            $this->_level = false;
            $this->_homepage = false;
            $this->_authhow .= ' paranoia logout';
        }
        if ($this->_userid) {
            $this->_homepage = $this->_dbi->getPage($this->_userid);
        }
    }

    /**
    * Get the string indicating how the user was authenticated.
    * 
    * Get the string indicating how the user was authenticated.
    * Does not seem to be set - jbw
    * @return string The method of authentication.
    */
    function auth_how() {
        return $this->_authhow;
    }

    /**
     * Invariant
     * 
     * If the WikiUser object has a valid authorization level and the 
     * userid is a string returns true, else false.
     * @return boolean If valid level and username string true, else false
     */
    function _ok () {
        if ((in_array($this->_level, array(WIKIAUTH_BOGO,
                                           WIKIAUTH_USER,
                                           WIKIAUTH_ADMIN))
            &&
            (is_string($this->_userid)))) {
            return true;
        }
        return false;
    }

    function getId () {
        return ( $this->isSignedIn()
                 ? $this->_userid
                 : $this->_request->get('REMOTE_ADDR') ); // FIXME: globals
    }

    function getAuthenticatedId() {
        return ( $this->isAuthenticated()
                 ? $this->_userid
                 : $this->_request->get('REMOTE_ADDR') ); // FIXME: globals
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

    function hasAuthority ($require_level) {
        return $this->_level >= $require_level;
    }

    function AuthCheck ($postargs) {
        // Normalize args, and extract.
        $keys = array('userid', 'passwd', 'require_level', 'login', 'logout',
                      'cancel');
        foreach ($keys as $key)
            $args[$key] = isset($postargs[$key]) ? $postargs[$key] : false;
        extract($args);
        $require_level = max(0, min(WIKIAUTH_ADMIN, (int)$require_level));

        if ($logout)
            return new WikiUser($this->_request); // Log out
        elseif ($cancel)
            return false;        // User hit cancel button.
        elseif (!$login && !$userid)
            return false;       // Nothing to do?

        $authlevel = $this->_pwcheck($userid, $passwd);
        if (!$authlevel)
            return _("Invalid password or userid.");
        elseif ($authlevel < $require_level)
            return _("Insufficient permissions.");

        // Successful login.
        $user = new WikiUser($this->_request);
        $user->_userid = $userid;
        $user->_level = $authlevel;
        return $user;
    }

    function PrintLoginForm (&$request, $args, $fail_message = false,
                             $seperate_page = true) {
        include_once('lib/Template.php');

        $userid = '';
        $require_level = 0;
        extract($args); // fixme

        $require_level = max(0, min(WIKIAUTH_ADMIN, (int)$require_level));

        $pagename = $request->getArg('pagename');
        $login = new Template('login', $request,
                              compact('pagename', 'userid', 'require_level',
                                      'fail_message', 'pass_required'));
        if ($seperate_page) {
            $top = new Template('html', $request,
                                array('TITLE' => _("Sign In")));
            return $top->printExpansion($login);
        } else {
            return $login;
        }
    }

    /**
     * Check password.
     */
    function _pwcheck ($userid, $passwd) {
        global $WikiNameRegexp;

        if (!empty($userid) && $userid == ADMIN_USER) {
            // $this->_authmethod = 'pagedata';
            if (defined('ENCRYPTED_PASSWD') && ENCRYPTED_PASSWD)
                if ( !empty($passwd)
                     && crypt($passwd, ADMIN_PASSWD) == ADMIN_PASSWD )
                    return WIKIAUTH_ADMIN;
            if (!empty($passwd)) {
                if ($passwd == ADMIN_PASSWD)
                  return WIKIAUTH_ADMIN;
                else {
                    // maybe we forgot to enable ENCRYPTED_PASSWD?
                    if ( function_exists('crypt')
                         && crypt($passwd, ADMIN_PASSWD) == ADMIN_PASSWD ) {
                        trigger_error(_("You forgot to set ENCRYPTED_PASSWD to true. Please update your /index.php"),
                                      E_USER_WARNING);
                        return WIKIAUTH_ADMIN;
                    }
                }
            }
            return false;
        }
        // HTTP Authentication
        elseif (ALLOW_HTTP_AUTH_LOGIN && !empty($PHP_AUTH_USER)) {
            // if he ignored the password field, because he is already
            // authenticated try the previously given password.
            if (empty($passwd))
                $passwd = $PHP_AUTH_PW;
        }

        // WikiDB_User DB/File Authentication from $DBAuthParams
        // Check if we have the user. If not try other methods.
        if (ALLOW_USER_LOGIN) { // && !empty($passwd)) {
            $request = $this->_request;
            // first check if the user is known
            if ($this->exists($userid)) {
                $this->_authmethod = 'pagedata';
                return ($this->checkPassword($passwd)) ? WIKIAUTH_USER : false;
            } else {
                // else try others such as LDAP authentication:
                if (ALLOW_LDAP_LOGIN && !empty($passwd)) {
                    if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
                        $r = @ldap_bind($ldap); // this is an anonymous bind
                        $st_search = "uid=$userid";
                        // Need to set the right root search information. see ../index.php
                        $sr = ldap_search($ldap, LDAP_AUTH_SEARCH,
                                          "$st_search");
                        $info = ldap_get_entries($ldap, $sr); // there may be more hits with this userid. try every
                        for ($i = 0; $i < $info["count"]; $i++) {
                            $dn = $info[$i]["dn"];
                            // The password is still plain text.
                            if ($r = @ldap_bind($ldap, $dn, $passwd)) {
                                // ldap_bind will return TRUE if everything matches
                                ldap_close($ldap);
                                $this->_authmethod = 'LDAP';
                                return WIKIAUTH_USER;
                            }
                        }
                    } else {
                        trigger_error("Unable to connect to LDAP server "
                                      . LDAP_AUTH_HOST, E_USER_WARNING);
                    }
                }
                // imap authentication. added by limako
                if (ALLOW_IMAP_LOGIN && !empty($passwd)) {
                    $mbox = @imap_open( "{" . IMAP_AUTH_HOST . ":143}",
                                        $userid, $passwd, OP_HALFOPEN );
                    if($mbox) {
                        imap_close($mbox);
                        $this->_authmethod = 'IMAP';
                        return WIKIAUTH_USER;
                    }
                }
            }
        }
        if ( ALLOW_BOGO_LOGIN
             && preg_match('/\A' . $WikiNameRegexp . '\z/', $userid) ) {
            $this->_authmethod = 'BOGO';
            return WIKIAUTH_BOGO;
        }
        return false;
    }

    // Todo: try our WikiDB backends.
    function getPreferences() {
        // Restore saved preferences.

        // I'd rather prefer only to store the UserId in the cookie or
        // session, and get the preferences from the db or page.
        if (!($prefs = $this->_request->getCookieVar('WIKI_PREFS2')))
            $prefs = $this->_request->getSessionVar('wiki_prefs');

        //if (!$this->_userid && !empty($GLOBALS['HTTP_COOKIE_VARS']['WIKI_ID'])) {
        //    $this->_userid = $GLOBALS['HTTP_COOKIE_VARS']['WIKI_ID'];
        //}

        // before we get his prefs we should check if he is signed in
        if (USE_PREFS_IN_PAGE && $this->homePage()) { // in page metadata
            if ($pref = $this->_homepage->get('pref')) {
                //trigger_error("pref=".$pref);//debugging
                $prefs = unserialize($pref);
            }
        }
        return new UserPreferences($prefs);
    }

    // No cookies anymore for all prefs, only the userid. PHP creates
    // a session cookie in memory, which is much more efficient.
    //
    // Return the number of changed entries?
    function setPreferences($prefs, $id_only = false) {
        // update the id
        $this->_request->setSessionVar('wiki_prefs', $prefs);
        // $this->_request->setCookieVar('WIKI_PREFS2', $this->_prefs, 365);
        // simple unpacked cookie
        if ($this->_userid) setcookie('WIKI_ID', $this->_userid, 365, '/');

        // We must ensure that any password is encrypted.
        // We don't need any plaintext password.
        if (! $id_only ) {
            if ($this->isSignedIn()) {
                if ($this->isAdmin())
                    $prefs->set('passwd', '');
                // already stored in index.php, and it might be
                // plaintext! well oh well
                if ($homepage = $this->homePage()) {
                    $homepage->set('pref', serialize($prefs->_prefs));
                    return sizeof($prefs->_prefs);
                } else {
                    trigger_error("No homepage for user found. Creating one...",
                                  E_USER_WARNING);
                    $this->createHomepage($prefs);
                    //$homepage->set('pref', serialize($prefs->_prefs));
                    return sizeof($prefs->_prefs);
                }
            } else {
                trigger_error("you must be signed in", E_USER_WARNING);
            }
        }
        return 0;
    }

    // check for homepage with user flag.
    // can be overriden from the auth backends
    function exists() {
        $homepage = $this->homePage();
        return ($this->_userid && $homepage && $homepage->get('pref'));
    }

    // doesn't check for existance!!! hmm.
    // how to store metadata in not existing pages? how about versions?
    function homePage() {
        if (!$this->_userid)
            return false;
        if (!empty($this->_homepage)) {
            return $this->_homepage;
        } else {
            $this->_homepage = $this->_dbi->getPage($this->_userid);
            return $this->_homepage;
        }
    }

    // create user by checking his homepage
    function createUser ($pref, $createDefaultHomepage = true) {
        if ($this->exists())
            return;
        if ($createDefaultHomepage) {
            $this->createHomepage($pref);
        } else {
            // empty page
            include "lib/loadsave.php";
            $pageinfo = array('pagedata' => array('pref' => serialize($pref->_pref)),
                              'versiondata' => array('author' => $this->_userid),
                              'pagename' => $this->_userid,
                              'content' => _('CategoryHomepage'));
            SavePage (&$this->_request, $pageinfo, false, false);
        }
        $this->setPreferences($pref);
    }

    // create user and default user homepage
    function createHomepage ($pref) {
        $pagename = $this->_userid;
        include "lib/loadsave.php";

        // create default homepage:
        //  properly expanded template and the pref metadata
        $template = Template('homepage.tmpl', $this->_request);
        $text  = $template->getExpansion();
        $pageinfo = array('pagedata' => array('pref' => serialize($pref->_pref)),
                          'versiondata' => array('author' => $this->_userid),
                          'pagename' => $pagename,
                          'content' => $text);
        SavePage (&$this->_request, $pageinfo, false, false);

        // create Calender
        $pagename = $this->_userid . SUBPAGE_SEPARATOR . _('Preferences');
        if (! isWikiPage($pagename)) {
            $pageinfo = array('pagedata' => array(),
                              'versiondata' => array('author' => $this->_userid),
                              'pagename' => $pagename,
                              'content' => "<?plugin Calender ?>\n");
            SavePage (&$this->_request, $pageinfo, false, false);
        }

        // create Preferences
        $pagename = $this->_userid . SUBPAGE_SEPARATOR . _('Preferences');
        if (! isWikiPage($pagename)) {
            $pageinfo = array('pagedata' => array(),
                              'versiondata' => array('author' => $this->_userid),
                              'pagename' => $pagename,
                              'content' => "<?plugin UserPreferences ?>\n");
            SavePage (&$this->_request, $pageinfo, false, false);
        }
    }

    function tryAuthBackends() {
        return ''; // crypt('') will never be ''
    }

    // Auth backends must store the crypted password where?
    // Not in the preferences.
    function checkPassword($passwd) {
        $prefs = $this->getPreferences();
        $stored_passwd = $prefs->get('passwd'); // crypted
        if (empty($prefs->_prefs['passwd']))    // not stored in the page
            // allow empty passwords? At least store a '*' then.
            // try other backend. hmm.
            $stored_passwd = $this->tryAuthBackends($this->_userid);
        if (empty($stored_passwd)) {
            trigger_error(sprintf(_("Old UserPage %s without stored password updated with empty password. Set a password in your UserPreferences."),
                                  $this->_userid), E_USER_NOTICE);
            $prefs->set('passwd','*');
            return true;
        }
        if ($stored_passwd == '*')
            return true;
        if ( !empty($passwd)
             && crypt($passwd, $stored_passwd) == $stored_passwd )
            return true;
        else
            return false;
    }

    function changePassword($newpasswd, $passwd2 = false) {
        if (! $this->mayChangePassword() ) {
            trigger_error(sprintf("Attempt to change an external password for '%s'. Not allowed!",
                                  $this->_userid), E_USER_ERROR);
            return;
        }
        if ($passwd2 && $passwd2 != $newpasswd) {
            trigger_error("The second password must be the same as the first to change it",
                          E_USER_ERROR);
            return;
        }
        $prefs = $this->getPreferences();
        //$oldpasswd = $prefs->get('passwd');
        $prefs->set('passwd', crypt($newpasswd));
        $this->setPreferences($prefs);
    }

    function mayChangePassword() {
        // on external DBAuth maybe. on IMAP or LDAP not
        // on internal DBAuth yes
        if (in_array($this->_authmethod, array('IMAP', 'LDAP')))
            return false;
        if ($this->isAdmin())
            return false;
        if ($this->_authmethod == 'pagedata')
            return true;
        if ($this->_authmethod == 'authdb')
            return true;
    }
                         }

// create user and default user homepage
// FIXME: delete this, not used?
/*
function createUser ($userid, $pref) {
    global $request;
    $user = new WikiUser ($request, $userid);
    $user->createUser($pref);
}
*/

class _UserPreference
{
    function _UserPreference ($default_value) {
        $this->default_value = $default_value;
    }

    function sanify ($value) {
        return (string)$value;
    }

    function update ($value) {
    }
}

class _UserPreference_numeric
extends _UserPreference
{
    function _UserPreference_numeric ($default, $minval = false,
                                      $maxval = false) {
        $this->_UserPreference((double)$default);
        $this->_minval = (double)$minval;
        $this->_maxval = (double)$maxval;
    }

    function sanify ($value) {
        $value = (double)$value;
        if ($this->_minval !== false && $value < $this->_minval)
            $value = $this->_minval;
        if ($this->_maxval !== false && $value > $this->_maxval)
            $value = $this->_maxval;
        return $value;
    }
}

class _UserPreference_int
extends _UserPreference_numeric
{
    function _UserPreference_int ($default, $minval = false, $maxval = false) {
        $this->_UserPreference_numeric((int)$default, (int)$minval,
                                       (int)$maxval);
    }

    function sanify ($value) {
        return (int)parent::sanify((int)$value);
    }
}

class _UserPreference_bool
extends _UserPreference
{
    function _UserPreference_bool ($default = false) {
        $this->_UserPreference((bool)$default);
    }

    function sanify ($value) {
        if (is_array($value)) {
            /* This allows for constructs like:
             *
             *   <input type="hidden" name="pref[boolPref][]" value="0" />
             *   <input type="checkbox" name="pref[boolPref][]" value="1" />
             *
             * (If the checkbox is not checked, only the hidden input
             * gets sent. If the checkbox is sent, both inputs get
             * sent.)
             */
            foreach ($value as $val) {
                if ($val)
                    return true;
            }
            return false;
        }
        return (bool) $value;
    }
}

class _UserPreference_language
extends _UserPreference
{
    function _UserPreference_language ($default = DEFAULT_LANGUAGE) {
        $this->_UserPreference($default);
    }

    function sanify ($value) {
        // FIXME: check for valid locale
        return (string) $value;
    }
}

class _UserPreference_theme
extends _UserPreference
{
    function _UserPreference_theme ($default = THEME) {
        $this->_UserPreference($default);
    }

    function sanify ($value) {
        if (file_exists($this->_themefile($value)))
            return $value;
        return $this->default_value;
    }

    function update ($newvalue) {
        global $Theme;
        include_once($this->_themefile($newvalue));
        if (empty($Theme))
            include_once($this->_themefile(THEME));
    }

    function _themefile ($theme) {
        return "themes/$theme/themeinfo.php";
    }
}

// don't save default preferences for efficiency.
class UserPreferences {
    function UserPreferences ($saved_prefs = false) {
        $this->_prefs = array();

        if (isa($saved_prefs, 'UserPreferences') && $saved_prefs->_prefs) {
            foreach ($saved_prefs->_prefs as $name => $value)
                $this->set($name, $value);
        } elseif (is_array($saved_prefs)) {
            foreach ($saved_prefs as $name => $value)
                $this->set($name, $value);
        }
    }

    function _getPref ($name) {
        global $UserPreferences;
        if (!isset($UserPreferences[$name])) {
            if ($name == 'passwd2') return false;
            trigger_error("$name: unknown preference", E_USER_NOTICE);
            return false;
        }
        return $UserPreferences[$name];
    }

    function get ($name) {
        if (isset($this->_prefs[$name]))
            return $this->_prefs[$name];
        if (!($pref = $this->_getPref($name)))
            return false;
        return $pref->default_value;
    }

    function set ($name, $value) {
        if (!($pref = $this->_getPref($name)))
            return false;

        $newvalue = $pref->sanify($value);
        $oldvalue = $this->get($name);

        // update on changes
        if ($newvalue != $oldvalue)
            $pref->update($newvalue);

        // don't set default values to save space (in cookies, db and
        // sesssion)
        if ($value == $pref->default_value)
            unset($this->_prefs[$name]);
        else
            $this->_prefs[$name] = $newvalue;
    }

    function hash () {
        return hash($this->_prefs);
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.35  2003/02/16 20:04:47  dairiki
// Refactor the HTTP validator generation/checking code.
//
// This also fixes a number of bugs with yesterdays validator mods.
//
// Revision 1.34  2003/02/15 02:21:54  dairiki
// API Change!  Explicit $request argument added to contructor for WikiUser.
//
// This seemed the best way to fix a problem whereby the WikiDB
// was being opened twice.  (Which while being merely inefficient
// when using an SQL backend causes hangage when using a dba backend.)
//
// Revision 1.33  2003/01/22 03:21:40  zorloc
// Modified WikiUser constructor to move the DB request for the homepage to
// the end of the logic to prevent it from being requested and then dropped.
// Added more phpdoc comments.
//
// Revision 1.32  2003/01/21 07:40:50  zorloc
// Modified WikiUser::_ok() -- Inverted the logic so the default is to return
// false and to return true only in the desired condition.  Added phpdoc
// comments
//
// Revision 1.31  2003/01/15 05:37:20  carstenklapp
// code reformatting
//
// Revision 1.30  2003/01/15 04:59:27  carstenklapp
// Bugfix: Previously stored preferences were not loading when user
// signed in. (Fixed... I hope.)
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
