<?php //-*-php-*-
rcs_id('$Id: WikiUserNew.php,v 1.22 2004-02-27 05:15:40 rurban Exp $');

/**
 * This is a complete OOP rewrite of the old WikiUser code with various
 * configurable external authentification methods.
 *
 * There's only one entry point, the function WikiUser which returns 
 * a WikiUser object, which contains the user's preferences.
 * This object might get upgraded during the login step and later also.
 * There exist three preferences storage methods: cookie, homepage and db,
 * and multiple password checking methods.
 * See index.php for $USER_AUTH_ORDER[] and USER_AUTH_POLICY if 
 * ALLOW_USER_PASSWORDS is defined.
 *
 * Each user object must define the two preferences methods 
 *  getPreferences(), setPreferences(), 
 * and the following 1-4 auth methods
 *  checkPass()  must be defined by all classes,
 *  userExists() only if USER_AUTH_POLICY'=='strict' 
 *  mayChangePass()  only if the password is storable.
 *  storePass()  only if the password is storable.
 *
 * WikiUser() given no name, returns an _AnonUser (anonymous user)
 * object, who may or may not have a cookie. 
 * However, if the there's a cookie with the userid or a session, 
 * the user is upgraded to the matching user object.
 * Given a user name, returns a _BogoUser object, who may or may not 
 * have a cookie and/or PersonalPage, one of the various _PassUser objects 
 * or an _AdminUser object.
 *
 * Takes care of passwords, all preference loading/storing in the
 * user's page and any cookies. lib/main.php will query the user object to
 * verify the password as appropriate.
 *
 * Notes by 2004-01-25 03:43:45 rurban
 * Test it by defining ENABLE_USER_NEW in index.php
 * 1) Now a ForbiddenUser is returned instead of false.
 * 2) Previously ALLOW_ANON_USER = false meant that anon users cannot edit, 
 * but may browse. Now with ALLOW_ANON_USER = false he may not browse, 
 * which is needed to disable browse PagePermissions. Hmm...
 * I added now ALLOW_ANON_EDIT = true to makes things clear. 
 * (which replaces REQUIRE_SIGNIN_BEFORE_EDIT)
 *
 *  Authors: Reini Urban (the tricky parts), 
 *           Carsten Klapp (started rolling the ball)
 */

define('WIKIAUTH_FORBIDDEN', -1); // Completely not allowed.
define('WIKIAUTH_ANON', 0);       // Not signed in.
define('WIKIAUTH_BOGO', 1);       // Any valid WikiWord is enough.
define('WIKIAUTH_USER', 2);       // Bogo user with a password.
define('WIKIAUTH_ADMIN', 10);     // UserName == ADMIN_USER.

if (!defined('COOKIE_EXPIRATION_DAYS')) define('COOKIE_EXPIRATION_DAYS', 365);
if (!defined('COOKIE_DOMAIN'))          define('COOKIE_DOMAIN', '/');

if (!defined('EDITWIDTH_MIN_COLS'))     define('EDITWIDTH_MIN_COLS',     30);
if (!defined('EDITWIDTH_MAX_COLS'))     define('EDITWIDTH_MAX_COLS',    150);
if (!defined('EDITWIDTH_DEFAULT_COLS')) define('EDITWIDTH_DEFAULT_COLS', 80);

if (!defined('EDITHEIGHT_MIN_ROWS'))     define('EDITHEIGHT_MIN_ROWS',      5);
if (!defined('EDITHEIGHT_MAX_ROWS'))     define('EDITHEIGHT_MAX_ROWS',     80);
if (!defined('EDITHEIGHT_DEFAULT_ROWS')) define('EDITHEIGHT_DEFAULT_ROWS', 22);

define('TIMEOFFSET_MIN_HOURS', -26);
define('TIMEOFFSET_MAX_HOURS',  26);
if (!defined('TIMEOFFSET_DEFAULT_HOURS')) define('TIMEOFFSET_DEFAULT_HOURS', 0);

/**
 * There are be the following constants in index.php to 
 * establish login parameters:
 *
 * ALLOW_ANON_USER         default true
 * ALLOW_ANON_EDIT         default true
 * ALLOW_BOGO_LOGIN        default true
 * ALLOW_USER_PASSWORDS    default true
 * PASSWORD_LENGTH_MINIMUM default 6?
 *
 * To require user passwords for editing:
 * ALLOW_ANON_USER  = true
 * ALLOW_ANON_EDIT  = false   (before named REQUIRE_SIGNIN_BEFORE_EDIT)
 * ALLOW_BOGO_LOGIN = false
 * ALLOW_USER_PASSWORDS = true
 *
 * To establish a COMPLETELY private wiki, such as an internal
 * corporate one:
 * ALLOW_ANON_USER = false
 * (and probably require user passwords as described above). In this
 * case the user will be prompted to login immediately upon accessing
 * any page.
 *
 * There are other possible combinations, but the typical wiki (such
 * as PhpWiki.sf.net) would usually just leave all four enabled.
 *
 */

// The last object in the row is the bad guy...
if (!is_array($USER_AUTH_ORDER))
    $USER_AUTH_ORDER = array("Forbidden");
else
    $USER_AUTH_ORDER[] = "Forbidden";

// Local convenience functions.
function _isAnonUserAllowed() {
    return (defined('ALLOW_ANON_USER') && ALLOW_ANON_USER);
}
function _isBogoUserAllowed() {
    return (defined('ALLOW_BOGO_LOGIN') && ALLOW_BOGO_LOGIN);
}
function _isUserPasswordsAllowed() {
    return (defined('ALLOW_USER_PASSWORDS') && ALLOW_USER_PASSWORDS);
}


// Possibly upgrade userobject functions.
function _determineAdminUserOrOtherUser($UserName) {
    // Sanity check. User name is a condition of the definition of the
    // _AdminUser, _BogoUser and _passuser.
    if (!$UserName)
        return $GLOBALS['ForbiddenUser'];

    if ($UserName == ADMIN_USER)
        return new _AdminUser($UserName);
    else
        return _determineBogoUserOrPassUser($UserName);
}

function _determineBogoUserOrPassUser($UserName) {
    global $ForbiddenUser;

    // Sanity check. User name is a condition of the definition of
    // _BogoUser and _PassUser.
    if (!$UserName)
        return $ForbiddenUser;

    // Check for password and possibly upgrade user object.
    $_BogoUser = new _BogoUser($UserName);
    if (_isUserPasswordsAllowed()) {
        if (/*$has_password =*/ $_BogoUser->_prefs->get('passwd'))
            return new _PassUser($UserName);
        else { // PassUsers override BogoUsers if they exist
            $_PassUser = new _PassUser($UserName);
            if ($_PassUser->userExists())
                return $_PassUser;
        }
    }
    // User has no password.
    if (_isBogoUserAllowed())
        return $_BogoUser;

    // Passwords are not allowed, and Bogo is disallowed too. (Only
    // the admin can sign in).
    return $ForbiddenUser;
}

/**
 * Primary WikiUser function, called by main.php.
 * 
 * This determines the user's type and returns an appropriate user
 * object. lib/main.php then querys the resultant object for password
 * validity as necessary.
 *
 * If an _AnonUser object is returned, the user may only browse pages
 * (and save prefs in a cookie).
 *
 * To disable access but provide prefs the global $ForbiddenUser class 
 * is returned. (was previously false)
 * 
 */
function WikiUser ($UserName = '') {
    global $ForbiddenUser;

    //Maybe: Check sessionvar for username & save username into
    //sessionvar (may be more appropriate to do this in lib/main.php).
    if ($UserName) {
        $ForbiddenUser = new _ForbiddenUser($UserName);
        // Found a user name.
        return _determineAdminUserOrOtherUser($UserName);
    }
    elseif (!empty($_SESSION['userid'])) {
        // Found a user name.
        $ForbiddenUser = new _ForbiddenUser($_SESSION['userid']);
        return _determineAdminUserOrOtherUser($_SESSION['userid']);
    }
    else {
        // Check for autologin pref in cookie and possibly upgrade
        // user object to another type.
        $_AnonUser = new _AnonUser();
        if ($UserName = $_AnonUser->_userid && $_AnonUser->_prefs->get('autologin')) {
            // Found a user name.
            $ForbiddenUser = new _ForbiddenUser($UserName);
            return _determineAdminUserOrOtherUser($UserName);
        }
        else {
            $ForbiddenUser = new _ForbiddenUser();
            if (_isAnonUserAllowed())
                return $_AnonUser;
            return $ForbiddenUser; // User must sign in to browse pages.
        }
        return $ForbiddenUser;     // User must sign in with a password.
    }
    /*
    trigger_error("DEBUG: Note: End of function reached in WikiUser." . " "
                  . "Unexpectedly, an appropriate user class could not be determined.");
    return $ForbiddenUser; // Failsafe.
    */
}

function WikiUserClassname() {
    return '_WikiUser';
}

function UpgradeUser ($olduser, $user) {
    if (isa($user,'_WikiUser') and isa($olduser,'_WikiUser')) {
        // populate the upgraded class $olduser with the values from the new user object
        foreach (get_object_vars($user) as $k => $v) {
            if (!empty($v)) $olduser->$k = $v;	
        }
        $GLOBALS['request']->_user = $olduser;
        return $olduser;
    } else {
        return false;
    }
}

function UserExists ($UserName) {
    global $request;
    if (!($user = $request->getUser()))
        $user = WikiUser($UserName);
    if (!$user) 
        return false;
    if ($user->userExists($UserName)) {
        $request->_user = $user;
        return true;
    }
    if (isa($user,'_BogoUser'))
      $user = new _PassUser($UserName);
    while ($user = $user->nextClass()) {
        return $user->userExists($UserName);
        $this = $user; // does this work on all PHP version?
    }
    $request->_user = $GLOBALS['ForbiddenUser'];
    return false;
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

// Base WikiUser class.
class _WikiUser
{
    var $_userid = '';

    var $_level = WIKIAUTH_FORBIDDEN;
    var $_prefs = false;
    var $_HomePagehandle = false;

    // constructor
    function _WikiUser($UserName = '') {

        $this->_userid = $UserName;
        if ($UserName) {
            $this->_HomePagehandle = $this->hasHomePage();
        }
        $this->getPreferences();
    }

    function UserName() {
        if (!empty($this->_userid))
            return $this->_userid;
    }

    function getPreferences() {
        trigger_error("DEBUG: Note: undefined _WikiUser class trying to load prefs." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    function setPreferences($prefs, $id_only) {
        trigger_error("DEBUG: Note: undefined _WikiUser class trying to save prefs." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    function userExists() {
        return $this->hasHomePage();
    }

    function checkPass($submitted_password) {
        // By definition, an undefined user class cannot sign in.
        trigger_error("DEBUG: Warning: undefined _WikiUser class trying to sign in." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    // returns page_handle to user's home page or false if none
    function hasHomePage() {
        if ($this->_userid) {
            if ($this->_HomePagehandle) {
                return $this->_HomePagehandle->exists();
            }
            else {
                // check db again (maybe someone else created it since
                // we logged in.)
                global $request;
                $this->_HomePagehandle = $request->getPage($this->_userid);
                return $this->_HomePagehandle->exists();
            }
        }
        // nope
        return false;
    }

    // innocent helper: case-insensitive position in _auth_methods
    function array_position ($string, $array) {
        $string = strtolower($string);
        for ($found = 0; $found < count($array); $found++) {
            if (strtolower($array[$found]) == $string)
                return $found;
        }
        return false;
    }

    function nextAuthMethodIndex() {
        if (empty($this->_auth_methods)) 
            $this->_auth_methods = $GLOBALS['USER_AUTH_ORDER'];
        if (empty($this->_current_index)) {
            if (get_class($this) != '_passuser') {
            	$this->_current_method = substr(get_class($this),1,-8);
                $this->_current_index = $this->array_position($this->_current_method,
                                                              $this->_auth_methods);
            } else {
            	$this->_current_index = -1;
            }
        }
        $this->_current_index++;
        if ($this->_current_index >= count($this->_auth_methods))
            return false;
        $this->_current_method = $this->_auth_methods[$this->_current_index];
        return $this->_current_index;
    }

    function AuthMethod($index = false) {
        return $this->_auth_methods[ $index === false ? 0 : $index];
    }

    // upgrade the user object
    function nextClass() {
        if (($next = $this->nextAuthMethodIndex()) !== false) {
            $method = $this->AuthMethod($next);
            $class = "_".$method."PassUser";
            if ($user = new $class($this->_userid)) {
                // prevent from endless recursion.
                //$user->_current_method = $this->_current_method;
                //$user->_current_index = $this->_current_index;
                $user = UpgradeUser($user, $this);
            }
            return $user;
        }
    }

    //Fixme: for _HttpAuthPassUser
    function PrintLoginForm (&$request, $args, $fail_message = false,
                             $seperate_page = true) {
        include_once('lib/Template.php');
        // Call update_locale in case the system's default language is not 'en'.
        // (We have no user pref for lang at this point yet, no one is logged in.)
        update_locale(DEFAULT_LANGUAGE);
        $userid = $this->_userid;
        $require_level = 0;
        extract($args); // fixme

        $require_level = max(0, min(WIKIAUTH_ADMIN, (int)$require_level));

        $pagename = $request->getArg('pagename');
        $nocache = 1;
        $login = new Template('login', $request,
                              compact('pagename', 'userid', 'require_level',
                                      'fail_message', 'pass_required', 'nocache'));
        if ($seperate_page) {
            $top = new Template('html', $request,
                                array('TITLE' => _("Sign In")));
            return $top->printExpansion($login);
        } else {
            return $login;
        }
    }

    // signed in but probably not password checked
    function isSignedIn() {
        return (isa($this,'_BogoUser') or isa($this,'_PassUser'));
    }

    function isAuthenticated () {
        //return isa($this,'_PassUser');
        //return isa($this,'_BogoUser') || isa($this,'_PassUser');
        return $this->_level >= WIKIAUTH_BOGO; // hmm.
    }

    function isAdmin () {
        return $this->_level == WIKIAUTH_ADMIN;
    }

    function getId () {
        return ( $this->UserName()
                 ? $this->UserName()
                 : $GLOBALS['request']->get('REMOTE_ADDR') ); // FIXME: globals
    }

    function getAuthenticatedId() {
        return ( $this->isAuthenticated()
                 ? $this->_userid
                 : ''); //$GLOBALS['request']->get('REMOTE_ADDR') ); // FIXME: globals
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

        if ($logout) { // Log out
            $GLOBALS['request']->_user = new _AnonUser();
            return $GLOBALS['request']->_user; 
        } elseif ($cancel)
            return false;        // User hit cancel button.
        elseif (!$login && !$userid)
            return false;       // Nothing to do?

        $authlevel = $this->checkPass($passwd);
        if (!$authlevel)
            return _("Invalid password or userid.");
        elseif ($authlevel < $require_level)
            return _("Insufficient permissions.");

        // Successful login.
        $user = $GLOBALS['request']->_user;
        $user->_userid = $userid;
        $user->_level = $authlevel;
        return $user;
    }

}

class _AnonUser
extends _WikiUser
{
    var $_level = WIKIAUTH_ANON;

    // Anon only gets to load and save prefs in a cookie, that's it.
    function getPreferences() {
        global $request;

        if (empty($this->_prefs))
            $this->_prefs = new UserPreferences;
        $UserName = $this->UserName();
        if ($cookie = $request->getCookieVar(WIKI_NAME)) {
            if (! $unboxedcookie = $this->_prefs->retrieve($cookie)) {
                trigger_error(_("Format of UserPreferences cookie not recognised.") . " "
                              . _("Default preferences will be used."),
                              E_USER_WARNING);
            }
            // TODO: try reading userid from old PhpWiki cookie
            // formats, then delete old cookie from browser!
            //
            //else {
                // try old cookie format.
                //$cookie = $request->getCookieVar('WIKI_ID');
            //}

            /**
             * Only keep the cookie if it matches the UserName who is
             * signing in or if this really is an Anon login (no
             * username). (Remember, _BogoUser and higher inherit this
             * function too!).
             */
            if (! $UserName || $UserName == $unboxedcookie['userid']) {
                $this->_prefs = new UserPreferences($unboxedcookie);
                $this->_userid = $unboxedcookie['userid'];
            }
        }
        // initializeTheme() needs at least an empty object
        if (! $this->_prefs )
            $this->_prefs = new UserPreferences;
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        // Allow for multiple wikis in same domain. Encode only the
        // _prefs array of the UserPreference object. Ideally the
        // prefs array should just be imploded into a single string or
        // something so it is completely human readable by the end
        // user. In that case stricter error checking will be needed
        // when loading the cookie.
        if (!is_object($prefs)) {
            $prefs = new UserPreferences($prefs);
        }
        $packed = $prefs->store();
        $unpacked = $prefs->unpack($packed);
        if (!empty($unpacked)) { // check how many are different from the default_value
            setcookie(WIKI_NAME, $packed,
                      COOKIE_EXPIRATION_DAYS, COOKIE_DOMAIN);
        }
        if (count($unpacked)) {
            global $request;
            $this->_prefs = $prefs;
            $request->_prefs =& $this->_prefs; 
            $request->_user->_prefs =& $this->_prefs; 
            //$request->setSessionVar('wiki_prefs', $this->_prefs);
            $request->setSessionVar('wiki_user', $request->_user);
        }
        return count($unpacked);
    }

    function userExists() {
        return true;
    }

    function checkPass($submitted_password) {
        // By definition, the _AnonUser does not HAVE a password
        // (compared to _BogoUser, who has an EMPTY password).
        trigger_error("DEBUG: Warning: _AnonUser unexpectedly asked to checkPass()." . " "
                      . "Check isa(\$user, '_PassUser'), or: isa(\$user, '_AdminUser') etc. first." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

}

class _ForbiddenUser
extends _AnonUser
{
    var $_level = WIKIAUTH_FORBIDDEN;

    function checkPass($submitted_password) {
        return WIKIAUTH_FORBIDDEN;
    }

    function userExists() {
        if ($this->_HomePagehandle) return true;
        return false;
    }
}
class _ForbiddenPassUser
extends _ForbiddenUser
{
    function dummy() {
        return;
    }
}

/**
 * Do NOT extend _BogoUser to other classes, for checkPass()
 * security. (In case of defects in code logic of the new class!)
 */
class _BogoUser
extends _AnonUser
{
    function userExists() {
        if (isWikiWord($this->_userid)) {
            $this->_level = WIKIAUTH_BOGO;
            return true;
        } else {
            $this->_level = WIKIAUTH_ANON;
            return false;
        }
    }

    function checkPass($submitted_password) {
        // By definition, BogoUser has an empty password.
        $this->userExists();
        return $this->_level;
    }
}

class _PassUser
extends _AnonUser
/**
 * Called if ALLOW_USER_PASSWORDS and Anon and Bogo failed.
 *
 * The classes for all subsequent auth methods extend from
 * this class. 
 * This handles the auth method type dispatcher according $USER_AUTH_ORDER, 
 * the three auth method policies first-only, strict and stacked
 * and the two methods for prefs: homepage or database, 
 * if $DBAuthParams['pref_select'] is defined.
 *
 * Default is PersonalPage auth and prefs.
 * 
 *  TODO: password changing
 *  TODO: email verification
 */
{
    var $_auth_dbi, $_prefmethod, $_prefselect, $_prefupdate;
    var $_current_method, $_current_index;

    // check and prepare the auth and pref methods only once
    function _PassUser($UserName = '') {
        global $DBAuthParams, $DBParams;

        if ($UserName) {
            $this->_userid = $UserName;
            if ($this->hasHomePage())
                $this->_HomePagehandle = $GLOBALS['request']->getPage($this->_userid);
        }
        // Check the configured Prefs methods
        if (  !empty($DBAuthParams['pref_select']) ) {
            if ( $DBParams['dbtype'] == 'SQL' and !@is_int($this->_prefselect)) {
                $this->_prefmethod = 'SQL'; // really pear db
                $this->getAuthDbh();
                // preparate the SELECT statement
                $this->_prefselect = $this->_auth_dbi->prepare(
		    str_replace('"$userid"','?',$DBAuthParams['pref_select'])
	        );
            }
            if (  $DBParams['dbtype'] == 'ADODB' and !isset($this->_prefselect)) {
                $this->_prefmethod = 'ADODB'; // uses a simplier execute syntax
                $this->getAuthDbh();
                // preparate the SELECT statement
                $this->_prefselect = str_replace('"$userid"','%s',$DBAuthParams['pref_select']);
            }
        } else {
            unset($this->_prefselect);
        }
        if (  !empty($DBAuthParams['pref_update']) and 
              $DBParams['dbtype'] == 'SQL' and
              !@is_int($this->_prefupdate)) {
            $this->_prefmethod = 'SQL';
            $this->getAuthDbh();
            $this->_prefupdate = $this->_auth_dbi->prepare(
                str_replace(array('"$userid"','"$pref_blob"'),array('?','?'),$DBAuthParams['pref_update'])
	    );
        }
        if (  !empty($DBAuthParams['pref_update']) and 
              $DBParams['dbtype'] == 'ADODB' and
              !isset($this->_prefupdate)) {
            $this->_prefmethod = 'ADODB'; // uses a simplier execute syntax
            $this->getAuthDbh();
            // preparate the SELECT statement
            $this->_prefupdate = str_replace(array('"$userid"','"$pref_blob"'),array('%s','%s'),
                                             $DBAuthParams['pref_update']);
        }
        $this->getPreferences();

        // Upgrade to the next parent _PassUser class. Avoid recursion.
        if ( get_class($this) === '_passuser' ) { // hopefully PHP will keep this lowercase
            //auth policy: Check the order of the configured auth methods
            // 1. first-only: Upgrade the class here in the constructor
            // 2. old:       ignore USER_AUTH_ORDER and try to use all available methods as in the previous PhpWiki releases (slow)
            // 3. strict:    upgrade the class after checking the user existance in userExists()
            // 4. stacked:   upgrade the class after the password verification in checkPass()
            // Methods: PersonalPage, HTTP_AUTH, DB, LDAP, IMAP, File
            if (!defined('USER_AUTH_POLICY')) define('USER_AUTH_POLICY','old');
            if (defined('USER_AUTH_POLICY')) {
                // policy 1: only pre-define one method for all users
                if (USER_AUTH_POLICY === 'first-only') {
                    if ($user = $this->nextClass())
                        return $user;
                    else 
                        return $GLOBALS['ForbiddenUser'];
                }
                // use the default behaviour from the previous versions:
                elseif (USER_AUTH_POLICY === 'old') {
                    // default: try to be smart
                    if (!empty($GLOBALS['PHP_AUTH_USER'])) {
                        return new _HttpAuthPassUser($UserName);
                    } elseif (!empty($DBAuthParams['auth_check']) and 
                              (!empty($DBAuthParams['auth_dsn']) or !empty($GLOBALS ['DBParams']['dsn']))) {
                        return new _DbPassUser($UserName);
                    } elseif (defined('LDAP_AUTH_HOST') and defined('LDAP_BASE_DN') and function_exists('ldap_open')) {
                        return new _LDAPPassUser($UserName);
                    } elseif (defined('IMAP_AUTH_HOST') and function_exists('imap_open')) {
                        return new _IMAPPassUser($UserName);
                    } elseif (defined('AUTH_USER_FILE')) {
                        return new _FilePassUser($UserName);
                    } else {
                        return new _PersonalPagePassUser($UserName);
                    }
                }
                else 
                    // else use the page methods defined in _PassUser.
                    return $this;
            }
        }
    }

    function getAuthDbh () {
        global $request, $DBParams, $DBAuthParams;

        // session restauration doesn't re-connect to the database automatically, so dirty it here.
        if (($DBParams['dbtype'] == 'SQL') and isset($this->_auth_dbi) and empty($this->_auth_dbi->connection))
            unset($this->_auth_dbi);
        if (($DBParams['dbtype'] == 'ADODB') and isset($this->_auth_dbi) and empty($this->_auth_dbi->_connectionID))
            unset($this->_auth_dbi);

        if (empty($this->_auth_dbi)) {
            if (empty($DBAuthParams['auth_dsn'])) {
                if ($DBParams['dbtype'] == 'SQL')
                    $dbh = $request->getDbh(); // use phpwiki database 
                else 
                    return false;
            }
            elseif ($DBAuthParams['auth_dsn'] == $DBParams['dsn'])
                $dbh = $request->getDbh(); // same phpwiki database 
            else // use another external database handle. needs PHP >= 4.1
                $dbh = WikiDB::open($DBAuthParams);
                
            $this->_auth_dbi =& $dbh->_backend->_dbh;    
        }
        return $this->_auth_dbi;
    }

    function prepare ($stmt, $variables) {
        global $DBParams, $request;
        // preparate the SELECT statement, for ADODB and PearDB (MDB not)
        $this->getAuthDbh();
        $place = ($DBParams['dbtype'] == 'ADODB') ? '%s' : '?';
        if (is_array($variables)) {
            $new = array();
            foreach ($variables as $v) { $new[] = $place; }
        } else {
            $new = $place;
        }
        // probably prefix table names if in same database
        if (!empty($DBParams['prefix']) and 
            $this->_auth_dbi === $request->_dbi->_backend->_dbh) {
            if (!stristr($DBParams['prefix'],$stmt)) {
                //Do it automatically for the lazy admin? Esp. on sf.net it's nice to have
                trigger_error("TODO: Need to prefix the DBAuthParam tablename in index.php: $stmt",
                              E_USER_WARNING);
                $stmt = str_replace(array(" user "," pref "," member "),
                                    array(" ".$prefix."user ",
                                          " ".$prefix."prefs ",
                                          " ".$prefix."member "),$stmt);
            }
        }
        return $this->_auth_dbi->prepare(str_replace($variables,$new,$stmt));
    }

    function getPreferences() {
        if (!empty($this->_prefmethod)) {
            if ($this->_prefmethod == 'ADODB') {
                _AdoDbPassUser::_AdoDbPassUser();
                return _AdoDbPassUser::getPreferences();
            } elseif ($this->_prefmethod == 'SQL') {
                _PearDbPassUser::_PearDbPassUser();
                return _PearDbPassUser::getPreferences();
            }
        }

        // We don't necessarily have to read the cookie first. Since
        // the user has a password, the prefs stored in the homepage
        // cannot be arbitrarily altered by other Bogo users.
        _AnonUser::getPreferences();
        // User may have deleted cookie, retrieve from his
        // PersonalPage if there is one.
        if (! $this->_prefs && $this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve($this->_HomePagehandle->get('pref'))) {
                $this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        if (!empty($this->_prefmethod)) {
            if ($this->_prefmethod == 'ADODB') {
                _AdoDbPassUser::_AdoDbPassUser();
                return _AdoDbPassUser::setPreferences($prefs, $id_only);
            }
            elseif ($this->_prefmethod == 'SQL') {
                _PearDbPassUser::_PearDbPassUser();
                return _PearDbPassUser::setPreferences($prefs, $id_only);
            }
        }

        _AnonUser::setPreferences($prefs, $id_only);
        // Encode only the _prefs array of the UserPreference object
        if (!is_object($prefs)) {
            $prefs = new UserPreferences($prefs);
        }
        $packed = $prefs->store();
        $unpacked = $prefs->unpack($packed);
        if (count($unpacked)) {
            global $request;
            $this->_prefs = $prefs;
            $request->_prefs =& $this->_prefs; 
            $request->_user->_prefs =& $this->_prefs; 
            //$request->setSessionVar('wiki_prefs', $this->_prefs);
            $request->setSessionVar('wiki_user', $request->_user);
        }
        if ($this->_HomePagehandle)
            $this->_HomePagehandle->set('pref', $packed);
        return count($unpacked);    
    }

    function mayChangePass() {
        return true;
    }

    //The default method is getting the password from prefs. 
    // child methods obtain $stored_password from external auth.
    function userExists() {
        //if ($this->_HomePagehandle) return true;
        while ($user = $this->nextClass()) {
              if ($user->userExists()) {
                  $this = $user;
                  return true;
              }
              $this = $user; // prevent endless loop. does this work on all PHP's?
              // it just has to set the classname, what it correctly does.
        }
        return false;
    }

    //The default method is getting the password from prefs. 
    // child methods obtain $stored_password from external auth.
    function checkPass($submitted_password) {
        $stored_password = $this->_prefs->get('passwd');
        $result = $this->_checkPass($submitted_password, $stored_password);
        if ($result) $this->_level = WIKIAUTH_USER;

        if (!$result) {
            if (USER_AUTH_POLICY === 'strict') {
                if ($user = $this->nextClass()) {
                    if ($user = $user->userExists())
                        return $user->checkPass($submitted_password);
                }
            }
            if (USER_AUTH_POLICY === 'stacked' or USER_AUTH_POLICY === 'old') {
                if ($user = $this->nextClass())
                    return $user->checkPass($submitted_password);
            }
        }
        return $this->_level;
    }

    //TODO: remove crypt() function check from config.php:396 ??
    function _checkPass($submitted_password, $stored_password) {
        if(!empty($submitted_password)) {
            if (defined('ENCRYPTED_PASSWD') && ENCRYPTED_PASSWD) {
                // Verify against encrypted password.
                if (function_exists('crypt')) {
                    if (crypt($submitted_password, $stored_password) == $stored_password )
                        return true; // matches encrypted password
                    else
                        return false;
                }
                else {
                    trigger_error(_("The crypt function is not available in this version of PHP.") . " "
                                  . _("Please set ENCRYPTED_PASSWD to false in index.php and change ADMIN_PASSWD."),
                                  E_USER_WARNING);
                    return false;
                }
            }
            else {
                // Verify against cleartext password.
                if ($submitted_password == $stored_password)
                    return true;
                else {
                    // Check whether we forgot to enable ENCRYPTED_PASSWD
                    if (function_exists('crypt')) {
                        if (crypt($submitted_password, $stored_password) == $stored_password) {
                            trigger_error(_("Please set ENCRYPTED_PASSWD to true in index.php."),
                                          E_USER_WARNING);
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    //The default method is storing the password in prefs. 
    // Child methods (DB,File) may store in external auth also, but this 
    // must be explicitly enabled.
    // This may be called by plugin/UserPreferences or by ->SetPreferences()
    function changePass($submitted_password) {
        $stored_password = $this->_prefs->get('passwd');
        // check if authenticated
        if ($this->isAuthenticated() and $stored_password != $submitted_password) {
            $this->_prefs->set('passwd',$submitted_password);
            //update the storage (session, homepage, ...)
            $this->SetPreferences($this->_prefs);
            return true;
        }
        //Todo: return an error msg to the caller what failed? 
        // same password or no privilege
        return false;
    }

}

class _BogoLoginUser
extends _PassUser
{
    function userExists() {
        if (isWikiWord($this->_userid)) {
            $this->_level = WIKIAUTH_BOGO;
            return true;
        } else {
            $this->_level = WIKIAUTH_ANON;
            return false;
        }
    }

    function checkPass($submitted_password) {
        // A BogoLoginUser requires PASSWORD_LENGTH_MINIMUM. hmm..
        $this->userExists();
        return $this->_level;
    }
}


class _PersonalPagePassUser
extends _PassUser
/**
 * This class is only to simplify the auth method dispatcher.
 * It inherits almost all all methods from _PassUser.
 */
{
    function userExists() {
        return $this->_HomePagehandle and $this->_HomePagehandle->exists();
    }
        
    function checkPass($submitted_password) {
        if ($this->userExists()) {
            // A PersonalPagePassUser requires PASSWORD_LENGTH_MINIMUM.
            // BUT if the user already has a homepage with en empty password 
            // stored allow login but warn him to change it.
            $stored_password = $this->_prefs->get('passwd');
            if (empty($stored_password)) {
                trigger_error(sprintf(
                _("\nYou stored an empty password in your %s page.\n").
                _("Your access permissions are only for a BogoUser.\n").
                _("Please set your password in UserPreferences."),
                                        $this->_userid), E_USER_NOTICE);
                $this->_level = WIKIAUTH_BOGO;
                return $this->_level;
            }
            if ($this->_checkPass($submitted_password, $stored_password))
                return ($this->_level = WIKIAUTH_USER);
            return _PassUser::checkPass($submitted_password);
        }
        return WIKIAUTH_ANON;
    }
}

class _HttpAuthPassUser
extends _PassUser
{

    function _HttpAuthPassUser($UserName='') {
        if (!$this->_prefs)
            _PassUser::_PassUser($UserName);
        $this->_authmethod = 'HttpAuth';
        if ($this->userExists())
            return $this;
        else 
            return $GLOBALS['ForbiddenUser'];
    }

    //force http auth authorization
    function userExists() {
        // todo: older php's
        if (empty($_SERVER['PHP_AUTH_USER']) or 
            $_SERVER['PHP_AUTH_USER'] != $this->_userid) {
            header('WWW-Authenticate: Basic realm="'.WIKI_NAME.'"');
            header('HTTP/1.0 401 Unauthorized'); 
            exit;
        }
        $this->_userid = $_SERVER['PHP_AUTH_USER'];
        $this->_level = WIKIAUTH_USER;
        return $this;
    }
        
    function checkPass($submitted_password) {
        return $this->userExists() ? WIKIAUTH_USER : WIKIAUTH_ANON;
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

        include_once('lib/Template.php');
        // Call update_locale in case the system's default language is not 'en'.
        // (We have no user pref for lang at this point yet, no one is logged in.)
        update_locale(DEFAULT_LANGUAGE);
        $userid = $this->_userid;
        $require_level = 0;
        extract($args); // fixme

        $require_level = max(0, min(WIKIAUTH_ADMIN, (int)$require_level));

        $pagename = $request->getArg('pagename');
        $nocache = 1;
        $login = new Template('login', $request,
                              compact('pagename', 'userid', 'require_level',
                                      'fail_message', 'pass_required', 'nocache'));
        if ($seperate_page) {
            $top = new Template('html', $request,
                                array('TITLE' => _("Sign In")));
            return $top->printExpansion($login);
        } else {
            return $login;
        }
    }

}

class _DbPassUser
extends _PassUser
/**
 * Authenticate against a database, to be able to use shared users.
 *   internal: no different $DbAuthParams['dsn'] defined, or
 *   external: different $DbAuthParams['dsn']
 * The magic is done in the symbolic SQL statements in index.php
 *
 * We support only the SQL and ADODB backends.
 * The other WikiDB backends (flat, cvs, dba, ...) should be used for pages, 
 * not for auth stuff. If one would like to use e.g. dba for auth, he should 
 * use PearDB (SQL) with the right $DBAuthParam['auth_dsn'].
 * Flat files for auth use is handled by the auth method "File".
 *
 * Preferences are handled in the parent class.
 */
{
    var $_authselect, $_authupdate;

    // This can only be called from _PassUser, because the parent class 
    // sets the auth_dbi and pref methods, before this class is initialized.
    function _DbPassUser($UserName='') {
        if (!$this->_prefs)
            _PassUser::_PassUser($UserName);
        $this->_authmethod = 'DB';
        $this->getAuthDbh();
        $this->_auth_crypt_method = @$GLOBALS['DBAuthParams']['auth_crypt_method'];

        if ($GLOBALS['DBParams']['dbtype'] == 'ADODB') 
            return new _AdoDbPassUser($UserName);
        else 
            return new _PearDbPassUser($UserName);
    }

    function mayChangePass() {
        return !isset($this->_authupdate);
    }

}

class _PearDbPassUser
extends _DbPassUser
/**
 * Pear DB methods
 */
{
    function _PearDbPassUser($UserName='') {
        global $DBAuthParams;
        if (!$this->_prefs and isa($this,"_PearDbPassUser"))
            _PassUser::_PassUser($UserName);
        $this->getAuthDbh();
        $this->_auth_crypt_method = @$GLOBALS['DBAuthParams']['auth_crypt_method'];
        // Prepare the configured auth statements
        if (!empty($DBAuthParams['auth_check']) and !@is_int($this->_authselect)) {
            $this->_authselect = $this->_auth_dbi->prepare (
                     str_replace(array('"$userid"','"$password"'),array('?','?'),
                                  $DBAuthParams['auth_check'])
                     );
        }
        if (!empty($DBAuthParams['auth_update']) and !@is_int($this->_authupdate)) {
            $this->_authupdate = $this->_auth_dbi->prepare(
                    str_replace(array('"$userid"','"$password"'),array('?','?'),
                                $DBAuthParams['auth_update'])
                    );
        }
        return $this;
    }

    function getPreferences() {
        // override the generic slow method here for efficiency and not to 
        // clutter the homepage metadata with prefs.
        _AnonUser::getPreferences();
        $this->getAuthDbh();
        if ((! $this->_prefs) && @is_int($this->_prefselect)) {
            $db_result = $this->_auth_dbi->execute($this->_prefselect,$this->_userid);
            list($prefs_blob) = $db_result->fetchRow();
            if ($restored_from_db = $this->_prefs->retrieve($prefs_blob)) {
                $this->_prefs = new UserPreferences($restored_from_db);
                return $this->_prefs;
            }
        }
        if ((! $this->_prefs) && $this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve($this->_HomePagehandle->get('pref'))) {
                $this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        // Encode only the _prefs array of the UserPreference object
        $this->getAuthDbh();
        if (!is_object($prefs)) {
            $prefs = new UserPreferences($prefs);
        }
        $packed = $prefs->store();
        $unpacked = $prefs->unpack($packed);
        if (count($unpacked)) {
            global $request;
            $this->_prefs = $prefs;
            $request->_prefs =& $this->_prefs; 
            $request->_user->_prefs =& $this->_prefs; 
            //$request->setSessionVar('wiki_prefs', $this->_prefs);
            $request->setSessionVar('wiki_user', $request->_user);
        }
        if (@is_int($this->_prefupdate)) {
            $db_result = $this->_auth_dbi->execute($this->_prefupdate,
                                                   array($packed,$this->_userid));
        } else {
            _AnonUser::setPreferences($prefs, $id_only);
            $this->_HomePagehandle->set('pref', $packed);
        }
        return count($unpacked);
    }

    function userExists() {
        if (!$this->_authselect)
            trigger_error("Either \$DBAuthParams['auth_check'] is missing or \$DBParams['dbtype'] != 'SQL'",
                          E_USER_WARNING);
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        //NOTE: for auth_crypt_method='crypt' no special auth_user_exists is needed
        if ($this->_auth_crypt_method == 'crypt') {
            $rs = $dbh->Execute($this->_authselect,$this->_userid) ;
            if ($rs->numRows())
                return true;
        }
        else {
            if (! $GLOBALS['DBAuthParams']['auth_user_exists'])
                trigger_error("\$DBAuthParams['auth_user_exists'] is missing",
                              E_USER_WARNING);
            $this->_authcheck = str_replace('"$userid"','?',
                                             $GLOBALS['DBAuthParams']['auth_user_exists']);
            $rs = $dbh->Execute($this->_authcheck,$this->_userid) ;
            if ($rs->numRows())
                return true;
        }
        
        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass()) {
                return $user->userExists();
            }
        }
    }
 
    function checkPass($submitted_password) {
        if (!@is_int($this->_authselect))
            trigger_error("Either \$DBAuthParams['auth_check'] is missing or \$DBParams['dbtype'] != 'SQL'",
                          E_USER_WARNING);

        //NOTE: for auth_crypt_method='crypt'  defined('ENCRYPTED_PASSWD',true) must be set
        $this->getAuthDbh();
        if ($this->_auth_crypt_method == 'crypt') {
            $db_result = $this->_auth_dbi->execute($this->_authselect,$this->_userid);
            list($stored_password) = $db_result->fetchRow();
            $result = $this->_checkPass($submitted_password, $stored_password);
        } else {
            //Fixme: The prepared params get reversed somehow! 
            //cannot find the pear bug for now
            $db_result = $this->_auth_dbi->execute($this->_authselect,
                                                   array($submitted_password,$this->_userid));
            list($okay) = $db_result->fetchRow();
            $result = !empty($okay);
        }

        if ($result) {
            $this->_level = WIKIAUTH_USER;
        } else {
            if (USER_AUTH_POLICY === 'strict') {
                if ($user = $this->nextClass()) {
                    if ($user = $user->userExists())
                        return $user->checkPass($submitted_password);
                }
            }
            if (USER_AUTH_POLICY === 'stacked' or USER_AUTH_POLICY === 'old') {
                if ($user = $this->nextClass())
                    return $user->checkPass($submitted_password);
            }
        }
        return $this->_level;
    }

    function storePass($submitted_password) {
        if (!@is_int($this->_authupdate)) {
            //CHECKME
            trigger_warning("Either \$DBAuthParams['auth_update'] not defined or \$DBParams['dbtype'] != 'SQL'",
                          E_USER_WARNING);
            return false;
        }

        if ($this->_auth_crypt_method == 'crypt') {
            if (function_exists('crypt'))
                $submitted_password = crypt($submitted_password);
        }
        //Fixme: The prepared params get reversed somehow! 
        //cannot find the pear bug for now
        $db_result = $this->_auth_dbi->execute($this->_authupdate,
                                               array($submitted_password,$this->_userid));
    }

}

class _AdoDbPassUser
extends _DbPassUser
/**
 * ADODB methods
 */
{
    function _AdoDbPassUser($UserName='') {
        global $DBAuthParams;
        if (!$this->_prefs and isa($this,"_AdoDbPassUser"))
            _PassUser::_PassUser($UserName);
        $this->getAuthDbh();
        $this->_auth_crypt_method = $GLOBALS['DBAuthParams']['auth_crypt_method'];
        // Prepare the configured auth statements
        if (!empty($DBAuthParams['auth_check'])) {
            $this->_authselect = str_replace(array('"$userid"','"$password"'),array('%s','%s'),
                                              $DBAuthParams['auth_check']);
        }
        if (!empty($DBAuthParams['auth_update'])) {
            $this->_authupdate = str_replace(array('"$userid"','"$password"'),array("%s","%s"),
                                              $DBAuthParams['auth_update']);
        }
        return $this;
    }

    function getPreferences() {
        // override the generic slow method here for efficiency
        _AnonUser::getPreferences();
        $this->getAuthDbh();
        if ((! $this->_prefs) and isset($this->_prefselect)) {
            $dbh = & $this->_auth_dbi;
            $rs = $dbh->Execute(sprintf($this->_prefselect,$dbh->qstr($this->_userid)));
            if ($rs->EOF) {
                $rs->Close();
            } else {
                $prefs_blob = $rs->fields['pref_blob'];
                $rs->Close();
                if ($restored_from_db = $this->_prefs->retrieve($prefs_blob)) {
                    $this->_prefs = new UserPreferences($restored_from_db);
                    return $this->_prefs;
                }
            }
        }
        if ((! $this->_prefs) && $this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve($this->_HomePagehandle->get('pref'))) {
                $this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        if (!is_object($prefs)) {
            $prefs = new UserPreferences($prefs);
        }
        $this->getAuthDbh();
        $packed = $prefs->store();
        $unpacked = $prefs->unpack($packed);
        if (count($unpacked)) {
            global $request;
            $this->_prefs = $prefs;
            $request->_prefs =& $this->_prefs; 
            $request->_user->_prefs =& $this->_prefs; 
            //$request->setSessionVar('wiki_prefs', $this->_prefs);
            $request->setSessionVar('wiki_user', $request->_user);
        }
        if (isset($this->_prefupdate)) {
            $dbh = & $this->_auth_dbi;
            $db_result = $dbh->Execute(sprintf($this->_prefupdate,
                                               $dbh->qstr($packed),$dbh->qstr($this->_userid)));
            $db_result->Close();
        } else {
            _AnonUser::setPreferences($prefs, $id_only);
            $this->_HomePagehandle->set('pref', $serialized);
        }
        return count($unpacked);
    }
 
    function userExists() {
        if (!$this->_authselect)
            trigger_error("Either \$DBAuthParams['auth_check'] is missing or \$DBParams['dbtype'] != 'ADODB'",
                          E_USER_WARNING);
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        //NOTE: for auth_crypt_method='crypt' no special auth_user_exists is needed
        if ($this->_auth_crypt_method == 'crypt') {
            $rs = $dbh->Execute(sprintf($this->_authselect,$dbh->qstr($this->_userid)));
            if (!$rs->EOF) {
                $rs->Close();
                return true;
            } else {
                $rs->Close();
            }
        }
        else {
            if (! $GLOBALS['DBAuthParams']['auth_user_exists'])
                trigger_error("\$DBAuthParams['auth_user_exists'] is missing",
                              E_USER_WARNING);
            $this->_authcheck = str_replace('"$userid"','%s',
                                             $GLOBALS['DBAuthParams']['auth_user_exists']);
            $rs = $dbh->Execute(sprintf($this->_authcheck,$dbh->qstr($this->_userid)));
            if (!$rs->EOF) {
                $rs->Close();
                return true;
            } else {
                $rs->Close();
            }
        }
        
        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass())
                return $user->userExists();
        }
        return false;
    }

    function checkPass($submitted_password) {
        if (!isset($this->_authselect))
            trigger_error("Either \$DBAuthParams['auth_check'] is missing or \$DBParams['dbtype'] != 'ADODB'",
                          E_USER_WARNING);
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        //NOTE: for auth_crypt_method='crypt'  defined('ENCRYPTED_PASSWD',true) must be set
        if ($this->_auth_crypt_method == 'crypt') {
            $rs = $dbh->Execute(sprintf($this->_authselect,$dbh->qstr($this->_userid)));
            if (!$rs->EOF) {
                $stored_password = $rs->fields['password'];
                $rs->Close();
                $result = $this->_checkPass($submitted_password, $stored_password);
            } else {
                $rs->Close();
                $result = false;
            }
        }
        else {
            $rs = $dbh->Execute(sprintf($this->_authselect,
                                        $dbh->qstr($submitted_password),
                                        $dbh->qstr($this->_userid)));
            $okay = $rs->fields['ok'];
            $rs->Close();
            $result = !empty($okay);
        }

        if ($result) { 
            $this->_level = WIKIAUTH_USER;
        } else {
            if (USER_AUTH_POLICY === 'strict') {
                if ($user = $this->nextClass()) {
                    if ($user = $user->userExists())
                        return $user->checkPass($submitted_password);
                }
            }
            if (USER_AUTH_POLICY === 'stacked' or USER_AUTH_POLICY === 'old') {
                if ($user = $this->nextClass())
                    return $user->checkPass($submitted_password);
            }
        }
        return $this->_level;
    }

    function storePass($submitted_password) {
        if (!isset($this->_authupdate)) {
            //CHECKME
            trigger_warning("Either \$DBAuthParams['auth_update'] not defined or \$DBParams['dbtype'] != 'ADODB'",
                          E_USER_WARNING);
            return false;
        }

        if ($this->_auth_crypt_method == 'crypt') {
            if (function_exists('crypt'))
                $submitted_password = crypt($submitted_password);
        }
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        $rs = $dbh->Execute(sprintf($this->_authupdate,
                                    $dbh->qstr($submitted_password),
                                    $dbh->qstr($this->_userid)));
        $rs->Close();
    }

}

class _LDAPPassUser
extends _PassUser
/**
 * Define the vars LDAP_HOST and LDAP_BASE_DN in index.php
 *
 * Preferences are handled in _PassUser
 */
{
    function checkPass($submitted_password) {
        $this->_authmethod = 'LDAP';
        $userid = $this->_userid;
        if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            $r = @ldap_bind($ldap); // this is an anonymous bind
            // Need to set the right root search information. see ../index.php
            $sr = ldap_search($ldap, LDAP_BASE_DN, "uid=$userid");
            $info = ldap_get_entries($ldap, $sr); // there may be more hits with this userid. try every
            for ($i = 0; $i < $info["count"]; $i++) {
                $dn = $info[$i]["dn"];
                // The password is still plain text.
                if ($r = @ldap_bind($ldap, $dn, $submitted_password)) {
                    // ldap_bind will return TRUE if everything matches
                    ldap_close($ldap);
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                }
            }
        } else {
            trigger_error(fmt("Unable to connect to LDAP server %s", LDAP_AUTH_HOST), 
                          E_USER_WARNING);
            //return false;
        }

        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass()) {
                if ($user = $user->userExists())
                    return $user->checkPass($submitted_password);
            }
        }
        if (USER_AUTH_POLICY === 'stacked' or USER_AUTH_POLICY === 'old') {
            if ($user = $this->nextClass())
                return $user->checkPass($submitted_password);
        }
        return false;
    }

    function userExists() {
        $userid = $this->_userid;
        if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            $r = @ldap_bind($ldap);                 // this is an anonymous bind
            $sr = ldap_search($ldap, LDAP_BASE_DN, "uid=$userid");
            $info = ldap_get_entries($ldap, $sr);
            if ($info["count"] > 0) {
                ldap_close($ldap);
                return true;
            }
        } else {
            trigger_error(_("Unable to connect to LDAP server "). LDAP_AUTH_HOST, E_USER_WARNING);
        }

        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass())
                return $user->userExists();
        }
        return false;
    }

    function mayChangePass() {
        return false;
    }

}

class _IMAPPassUser
extends _PassUser
/**
 * Define the var IMAP_HOST in index.php
 *
 * Preferences are handled in _PassUser
 */
{
    function checkPass($submitted_password) {
        $userid = $this->_userid;
        $mbox = @imap_open( "{" . IMAP_AUTH_HOST . "}",
                            $userid, $submitted_password, OP_HALFOPEN );
        if ($mbox) {
            imap_close($mbox);
            $this->_authmethod = 'IMAP';
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        } else {
            trigger_error(_("Unable to connect to IMAP server "). IMAP_AUTH_HOST, E_USER_WARNING);
        }
        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass()) {
                if ($user = $user->userExists())
                    return $user->checkPass($submitted_password);
            }
        }
        if (USER_AUTH_POLICY === 'stacked' or USER_AUTH_POLICY === 'old') {
            if ($user = $this->nextClass())
                return $user->checkPass($submitted_password);
        }
        return false;
    }

    //CHECKME: this will not be okay for the auth policy strict
    function userExists() {
        if (checkPass($this->_prefs->get('passwd')))
            return true;
            
        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass())
                return $user->userExists();
        }
    }

    function mayChangePass() {
        return false;
    }

}

class _FilePassUser
extends _PassUser
/**
 * Check users defined in a .htaccess style file
 * username:crypt\n...
 *
 * Preferences are handled in _PassUser
 */
{
    var $_file, $_may_change;

    // This can only be called from _PassUser, because the parent class 
    // sets the pref methods, before this class is initialized.
    function _FilePassUser($UserName='',$file='') {
        if (!$this->_prefs)
            _PassUser::_PassUser($UserName);

        // read the .htaccess style file. We use our own copy of the standard pear class.
        require 'lib/pear/File_Passwd.php';
        // if passwords may be changed we have to lock them:
        $this->_may_change = defined('AUTH_USER_FILE_STORABLE') && AUTH_USER_FILE_STORABLE;
        if (empty($file) and defined('AUTH_USER_FILE'))
            $this->_file = File_Passwd(AUTH_USER_FILE, !empty($this->_may_change));
        elseif (!empty($file))
            $this->_file = File_Passwd($file, !empty($this->_may_change));
        else
            return false;
        return $this;
    }
 
    function mayChangePass() {
        return $this->_may_change;
    }

    function userExists() {
        if (isset($this->_file->users[$this->_userid]))
            return true;
            
        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass())
                return $user->userExists();
        }
    }

    function checkPass($submitted_password) {
        if ($this->_file->verifyPassword($this->_userid,$submitted_password)) {
            $this->_authmethod = 'File';
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        }
        
        if (USER_AUTH_POLICY === 'strict') {
            if ($user = $this->nextClass()) {
                if ($user = $user->userExists())
                    return $user->checkPass($submitted_password);
            }
        }
        if (USER_AUTH_POLICY === 'stacked' or USER_AUTH_POLICY === 'old') {
            if ($user = $this->nextClass())
                return $user->checkPass($submitted_password);
        }
        return false;
    }

    function storePass($submitted_password) {
        if ($this->_may_change)
            return $this->_file->modUser($this->_userid,$submitted_password);
        else 
            return false;
    }

}

/**
 * Insert more auth classes here...
 *
 */


/**
 * For security, this class should not be extended. Instead, extend
 * from _PassUser (think of this as unix "root").
 */
class _AdminUser
extends _PassUser
{
    //var $_level = WIKIAUTH_ADMIN;

    function checkPass($submitted_password) {
        $stored_password = ADMIN_PASSWD;
        if ($this->_checkPass($submitted_password, $stored_password)) {
            $this->_level = WIKIAUTH_ADMIN;
            return $this->_level;
        } else {
            $this->_level = WIKIAUTH_ANON;
            return false;
        }
    }

}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
/**
 * Various data classes for the preference types, 
 * to support get, set, sanify (range checking, ...)
 * update() will do the neccessary side-effects if a 
 * setting gets changed (theme, language, ...)
*/

class _UserPreference
{
    var $default_value;

    function _UserPreference ($default_value) {
        $this->default_value = $default_value;
    }

    function sanify ($value) {
        return (string)$value;
    }

    function get ($name) {
    	if (isset($this->{$name}))
	    return $this->{$name};
    	else 
            return $this->default_value;
    }

    function getraw ($name) {
    	if (!empty($this->{$name}))
	    return $this->{$name};
    }

    // stores the value as $this->$name, and not as $this->value (clever?)
    function set ($name, $value) {
	if ($this->get($name) != $value) {
	    $this->update($value);
	}
	if ($value != $this->default_value) {
	    $this->{$name} = $value;
        }
        else 
            unset($this->{$name});
    }

    // default: no side-effects 
    function update ($value) {
    	;
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

    // FIXME: check for valid locale
    function sanify ($value) {
        // Revert to DEFAULT_LANGUAGE if user does not specify
        // language in UserPreferences or chooses <system language>.
        if ($value == '' or empty($value))
            $value = DEFAULT_LANGUAGE;

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

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/**
 * UserPreferences
 * 
 * This object holds the $request->_prefs subobjects.
 * A simple packed array of non-default values get's stored as cookie,
 * homepage, or database, which are converted to the array of 
 * ->_prefs objects.
 * We don't store the objects, because otherwise we will
 * not be able to upgrade any subobject. And it's a waste of space also.
 */
class UserPreferences
{
    function UserPreferences ($saved_prefs = false) {
        // userid stored too, to ensure the prefs are being loaded for
        // the correct (currently signing in) userid if stored in a
        // cookie.
        $this->_prefs
            = array(
                    'userid'        => new _UserPreference(''),
                    'passwd'        => new _UserPreference(''),
                    'autologin'     => new _UserPreference_bool(),
                    'email'         => new _UserPreference(''),
                    'emailVerified' => new _UserPreference_bool(),
                    'notifyPages'   => new _UserPreference(''),
                    'theme'         => new _UserPreference_theme(THEME),
                    'lang'          => new _UserPreference_language(DEFAULT_LANGUAGE),
                    'editWidth'     => new _UserPreference_int(EDITWIDTH_DEFAULT_COLS,
                                                               EDITWIDTH_MIN_COLS,
                                                               EDITWIDTH_MAX_COLS),
                    'noLinkIcons'   => new _UserPreference_bool(),
                    'editHeight'    => new _UserPreference_int(EDITHEIGHT_DEFAULT_ROWS,
                                                               EDITHEIGHT_MIN_ROWS,
                                                               EDITHEIGHT_DEFAULT_ROWS),
                    'timeOffset'    => new _UserPreference_numeric(TIMEOFFSET_DEFAULT_HOURS,
                                                                   TIMEOFFSET_MIN_HOURS,
                                                                   TIMEOFFSET_MAX_HOURS),
                    'relativeDates' => new _UserPreference_bool()
                    );

        if (is_array($saved_prefs)) {
            foreach ($saved_prefs as $name => $value)
                $this->set($name, $value);
        }
    }

    function _getPref ($name) {
        if (!isset($this->_prefs[$name])) {
            if ($name == 'passwd2') return false;
            trigger_error("$name: unknown preference", E_USER_NOTICE);
            return false;
        }
        return $this->_prefs[$name];
    }
    
    // get the value or default_value of the subobject
    function get ($name) {
    	if ($_pref = $this->_getPref($name))
    	  return $_pref->get($name);
    	else 
    	  return false;  
        /*	
        if (is_object($this->_prefs[$name]))
            return $this->_prefs[$name]->get($name);
        elseif (($value = $this->_getPref($name)) === false)
            return false;
        elseif (!isset($value))
            return $this->_prefs[$name]->default_value;
        else return $value;
        */
    }

    // check and set the new value in the subobject
    function set ($name, $value) {
        $pref = $this->_getPref($name);
        if ($pref === false)
            return false;

        /* do it here or outside? */
        if ($name == 'passwd' and 
            defined('PASSWORD_LENGTH_MINIMUM') and 
            strlen($value) <= PASSWORD_LENGTH_MINIMUM ) {
            //TODO: How to notify the user?
            return false;
        }

        $newvalue = $pref->sanify($value);
	$pref->set($name,$newvalue);
        $this->_prefs[$name] = $pref;
        return true;

        // don't set default values to save space (in cookies, db and
        // sesssion)
        /*
        if ($value == $pref->default_value)
            unset($this->_prefs[$name]);
        else
            $this->_prefs[$name] = $pref;
        */
    }

    // array of objects => array of values
    function store() {
        $prefs = array();
        foreach ($this->_prefs as $name => $object) {
            if ($value = $object->getraw($name))
                $prefs[] = array($name => $value);
        }
        return $this->pack($prefs);
    }

    // packed string or array of values => array of values
    function retrieve($packed) {
        if (is_string($packed) and (substr($packed, 0, 2) == "a:"))
            $packed = unserialize($packed);
        if (!is_array($packed)) return false;
        $prefs = array();
        foreach ($packed as $name => $packed_pref) {
            if (substr($packed_pref, 0, 2) == "O:") {
                //legacy: check if it's an old array of objects
                // Looks like a serialized object. 
                // This might fail if the object definition does not exist anymore.
                // object with ->$name and ->default_value vars.
                $pref = unserialize($packed_pref);
                $prefs[$name] = $pref->get($name);
            } else {
                $prefs[$name] = unserialize($packed_pref);
            }
        }
        return $prefs;
    }
    
    // array of objects
    function getAll() {
        return $this->_prefs;
    }

    function pack($nonpacked) {
        return serialize($nonpacked);
    }

    function unpack($packed) {
        if (!$packed)
            return false;
        if (substr($packed, 0, 2) == "O:") {
            // Looks like a serialized object
            return unserialize($packed);
        }
        if (substr($packed, 0, 2) == "a:") {
            return unserialize($packed);
        }
        //trigger_error("DEBUG: Can't unpack bad UserPreferences",
        //E_USER_WARNING);
        return false;
    }

    function hash () {
        return hash($this->_prefs);
    }
}

class CookieUserPreferences
extends UserPreferences
{
    function CookieUserPreferences ($saved_prefs = false) {
        UserPreferences::UserPreferences($saved_prefs);
    }
}

class PageUserPreferences
extends UserPreferences
{
    function PageUserPreferences ($saved_prefs = false) {
        UserPreferences::UserPreferences($saved_prefs);
    }
}

class PearDbUserPreferences
extends UserPreferences
{
    function PearDbUserPreferences ($saved_prefs = false) {
        UserPreferences::UserPreferences($saved_prefs);
    }
}

class AdoDbUserPreferences
extends UserPreferences
{
    function AdoDbUserPreferences ($saved_prefs = false) {
        UserPreferences::UserPreferences($saved_prefs);
    }
    function getPreferences() {
        // override the generic slow method here for efficiency
        _AnonUser::getPreferences();
        $this->getAuthDbh();
        if ((! $this->_prefs) and isset($this->_prefselect)) {
            $dbh = & $this->_auth_dbi;
            $rs = $dbh->Execute(sprintf($this->_prefselect,$dbh->qstr($this->_userid)));
            if ($rs->EOF) {
                $rs->Close();
            } else {
                $prefs_blob = $rs->fields['pref_blob'];
                $rs->Close();
                if ($restored_from_db = $this->_prefs->retrieve($prefs_blob)) {
                    $this->_prefs = new UserPreferences($restored_from_db);
                    return $this->_prefs;
                }
            }
        }
        if ((! $this->_prefs) && $this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve($this->_HomePagehandle->get('pref'))) {
                $this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }
}


// $Log: not supported by cvs2svn $
// Revision 1.21  2004/02/26 20:43:49  rurban
// new HttpAuthPassUser class (forces http auth if in the auth loop)
// fixed user upgrade: don't return _PassUser in the first hand.
//
// Revision 1.20  2004/02/26 01:29:11  rurban
// important fixes: endless loops in certain cases. minor rewrite
//
// Revision 1.19  2004/02/25 17:15:17  rurban
// improve stability
//
// Revision 1.18  2004/02/24 15:20:05  rurban
// fixed minor warnings: unchecked args, POST => Get urls for sortby e.g.
//
// Revision 1.17  2004/02/17 12:16:42  rurban
// started with changePass support. not yet used.
//
// Revision 1.16  2004/02/15 22:23:45  rurban
// oops, fixed showstopper (endless recursion)
//
// Revision 1.15  2004/02/15 21:34:37  rurban
// PageList enhanced and improved.
// fixed new WikiAdmin... plugins
// editpage, Theme with exp. htmlarea framework
//   (htmlarea yet committed, this is really questionable)
// WikiUser... code with better session handling for prefs
// enhanced UserPreferences (again)
// RecentChanges for show_deleted: how should pages be deleted then?
//
// Revision 1.14  2004/02/15 17:30:13  rurban
// workaround for lost db connnection handle on session restauration (->_auth_dbi)
// fixed getPreferences() (esp. from sessions)
// fixed setPreferences() (update and set),
// fixed AdoDb DB statements,
// update prefs only at UserPreferences POST (for testing)
// unified db prefs methods (but in external pref classes yet)
//
// Revision 1.13  2004/02/09 03:58:12  rurban
// for now default DB_SESSION to false
// PagePerm:
//   * not existing perms will now query the parent, and not
//     return the default perm
//   * added pagePermissions func which returns the object per page
//   * added getAccessDescription
// WikiUserNew:
//   * added global ->prepare (not yet used) with smart user/pref/member table prefixing.
//   * force init of authdbh in the 2 db classes
// main:
//   * fixed session handling (not triple auth request anymore)
//   * don't store cookie prefs with sessions
// stdlib: global obj2hash helper from _AuthInfo, also needed for PagePerm
//
// Revision 1.12  2004/02/07 10:41:25  rurban
// fixed auth from session (still double code but works)
// fixed GroupDB
// fixed DbPassUser upgrade and policy=old
// added GroupLdap
//
// Revision 1.11  2004/02/03 09:45:39  rurban
// LDAP cleanup, start of new Pref classes
//
// Revision 1.10  2004/02/01 09:14:11  rurban
// Started with Group_Ldap (not yet ready)
// added new _AuthInfo plugin to help in auth problems (warning: may display passwords)
// fixed some configurator vars
// renamed LDAP_AUTH_SEARCH to LDAP_BASE_DN
// changed PHPWIKI_VERSION from 1.3.8a to 1.3.8pre
// USE_DB_SESSION defaults to true on SQL
// changed GROUP_METHOD definition to string, not constants
// changed sample user DBAuthParams from UPDATE to REPLACE to be able to
//   create users. (Not to be used with external databases generally, but
//   with the default internal user table)
//
// fixed the IndexAsConfigProblem logic. this was flawed:
//   scripts which are the same virtual path defined their own lib/main call
//   (hmm, have to test this better, phpwiki.sf.net/demo works again)
//
// Revision 1.9  2004/01/30 19:57:58  rurban
// fixed DBAuthParams['pref_select']: wrong _auth_dbi object used.
//
// Revision 1.8  2004/01/30 18:46:15  rurban
// fix "lib/WikiUserNew.php:572: Notice[8]: Undefined variable: DBParams"
//
// Revision 1.7  2004/01/27 23:23:39  rurban
// renamed ->Username => _userid for consistency
// renamed mayCheckPassword => mayCheckPass
// fixed recursion problem in WikiUserNew
// fixed bogo login (but not quite 100% ready yet, password storage)
//
// Revision 1.6  2004/01/26 09:17:49  rurban
// * changed stored pref representation as before.
//   the array of objects is 1) bigger and 2)
//   less portable. If we would import packed pref
//   objects and the object definition was changed, PHP would fail.
//   This doesn't happen with an simple array of non-default values.
// * use $prefs->retrieve and $prefs->store methods, where retrieve
//   understands the interim format of array of objects also.
// * simplified $prefs->get() and fixed $prefs->set()
// * added $user->_userid and class '_WikiUser' portability functions
// * fixed $user object ->_level upgrading, mostly using sessions.
//   this fixes yesterdays problems with loosing authorization level.
// * fixed WikiUserNew::checkPass to return the _level
// * fixed WikiUserNew::isSignedIn
// * added explodePageList to class PageList, support sortby arg
// * fixed UserPreferences for WikiUserNew
// * fixed WikiPlugin for empty defaults array
// * UnfoldSubpages: added pagename arg, renamed pages arg,
//   removed sort arg, support sortby arg
//
// Revision 1.5  2004/01/25 03:05:00  rurban
// First working version, but has some problems with the current main loop.
// Implemented new auth method dispatcher and policies, all the external
// _PassUser classes (also for ADODB and Pear DB).
// The two global funcs UserExists() and CheckPass() are probably not needed,
// since the auth loop is done recursively inside the class code, upgrading
// the user class within itself.
// Note: When a higher user class is returned, this doesn't mean that the user
// is authorized, $user->_level is still low, and only upgraded on successful
// login.
//
// Revision 1.4  2003/12/07 19:29:48  carstenklapp
// Code Housecleaning: fixed syntax errors. (php -l *.php)
//
// Revision 1.3  2003/12/06 19:10:46  carstenklapp
// Finished off logic for determining user class, including
// PassUser. Removed ability of BogoUser to save prefs into a page.
//
// Revision 1.2  2003/12/03 21:45:48  carstenklapp
// Added admin user, password user, and preference classes. Added
// password checking functions for users and the admin. (Now the easy
// parts are nearly done).
//
// Revision 1.1  2003/12/02 05:46:36  carstenklapp
// Complete rewrite of WikiUser.php.
//
// This should make it easier to hook in user permission groups etc. some
// time in the future. Most importantly, to finally get UserPreferences
// fully working properly for all classes of users: AnonUser, BogoUser,
// AdminUser; whether they have a NamesakePage (PersonalHomePage) or not,
// want a cookie or not, and to bring back optional AutoLogin with the
// UserName stored in a cookie--something that was lost after PhpWiki had
// dropped the default http auth login method.
//
// Added WikiUser classes which will (almost) work together with existing
// UserPreferences class. Other parts of PhpWiki need to be updated yet
// before this code can be hooked up.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
