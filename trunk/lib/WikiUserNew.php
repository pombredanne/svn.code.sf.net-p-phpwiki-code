<?php //-*-php-*-
rcs_id('$Id: WikiUserNew.php,v 1.3 2003-12-06 19:10:46 carstenklapp Exp $');

// This is a complete rewrite of the old WikiUser code but it is not
// implemented yet. Much of the existing UserPreferences class should
// work fine with this but a few other parts of PhpWiki need to be
// refitted: main.php, config.php, index.php. --Carsten


// Returns a user object, which contains the user's preferences.
//
// Given no name, returns an _AnonUser (anonymous user) object, who
// may or may not have a cookie. Given a user name, returns a
// _BogoUser object, who may or may not have a cookie and/or
// NamesakePage, a _PassUser object or an _AdminUser object.
//
// Takes care of passwords, all preference loading/storing in the
// user's page and any cookies. main.php will query the user object to
// verify the password as appropriate.


define('WIKIAUTH_ANON', 0);       // Not signed in.
define('WIKIAUTH_BOGO', 1);       // Any valid WikiWord is enough.
define('WIKIAUTH_USER', 2);       // Bogo user with a password.
define('WIKIAUTH_ADMIN', 10);     // UserName == ADMIN_USER.
define('WIKIAUTH_FORBIDDEN', -1); // Completely not allowed.

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
 * There are/will be four constants in index.php to establish login
 * parameters:
 *
 * ALLOW_ANON_USER         default true
 * ALLOW_BOGO_LOGIN        default true
 * ALLOW_USER_PASSWORDS    default true
 * PASSWORD_LENGTH_MINIMUM default 6?
 *
 *
 * To require user passwords:
 * ALLOW_BOGO_LOGIN = false,
 * ALLOW_USER_PASSWORDS = true.
 *
 * To establish a COMPLETELY private wiki, such as an internal
 * corporate one:
 * ALLOW_ANON_USER = false,
 * (and probably require user passwords as described above). In this
 * case the user will be prompted to login immediately upon accessing
 * any page.
 *
 * There are other possible combinations, but the typical wiki (such
 * as PhpWiki.sf.net) would usually just leave all three enabled.
 */

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
    // _AdminUser, _BogoUser and _PassUser.
    if (!$UserName)
        return false;

    if ($UserName == ADMIN_USER)
        return new _AdminUser($UserName);
    else
        return _determineBogoUserOrPassUser($UserName);
}

function _determineBogoUserOrPassUser($UserName) {
    // Sanity check. User name is a condition of the definition of
    // _BogoUser and _PassUser.
    if (!$UserName)
        return false;

    // Check for password and possibly upgrade user object.
    $_BogoUser = new _BogoUser($UserName);
    if (_isUserPasswordsAllowed()) {
        if (/*$has_password =*/ $_BogoUser->_prefs->get('passwd'))
            return new _PassUser($UserName);
    }
    // User has no password.
    if (_isBogoUserAllowed())
        return $_BogoUser;

    // Passwords are not allowed, and Bogo is disallowed too. (Only
    // the admin can sign in).
    return false;
}

/**
 * Primary WikiUser function, called by main.php.
 * 
 * This determines the user's type and returns an appropriate user
 * object. main.php then querys the resultant object for password
 * validity as necessary.
 *
 * If an _AnonUser object is returned, the user may only browse pages
 * (and save prefs in a cookie).
 *
 * When this function returns false instead of any user object, the
 * user has been denied access to the wiki (possibly even reading
 * pages) and must therefore sign in to continue.
 */
function WikiUser ($UserName = '') {
    //TODO: Check sessionvar for username & save username into
    //sessionvar (may be more appropriate to do this in main.php).
    if ($UserName) {
        // Found a user name.
        return _determineAdminUserOrOtherUser($UserName);
    }
    else {
        // Check for autologin pref in cookie and possibly upgrade
        // user object to another type.
        $_AnonUser = new _AnonUser();
        if ($UserName = $_AnonUser->UserName && $_AnonUser->_prefs->get('autologin')) {
            // Found a user name.
            return _determineAdminUserOrOtherUser($UserName);
        }
        else {
            if (_isAnonUserAllowed())
                return $_AnonUser;
            return false; // User must sign in to browse pages.
        }
        return false; // User must sign in with a password.
    }
    trigger_error("DEBUG: Note: End of function reached in WikiUser." . " "
                  . "Unexpectedly, an appropriate user class could not be determined.");
    return false; // Failsafe.
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

// Base _WikiUser class.
class _WikiUser
{
    var $_level = WIKIAUTH_FORBIDDEN;
    var $_prefs = false;
    var _HomePagehandle = false;

    var $UserName = '';

    // constructor
    function _WikiUser($UserName = '') {
        if ($UserName) {
            $this->UserName = $UserName;
            $this->_HomePagehandle = $this->hasHomePage();
        }
        $this->loadPreferences();
    }

    function loadPreferences() {
        trigger_error("DEBUG: Note: undefined _WikiUser class trying to load prefs." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    function savePreferences() {
        trigger_error("DEBUG: Note: undefined _WikiUser class trying to save prefs." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    // returns page_handle to user's home page or false if none
    function hasHomePage() {
        if ($this->UserName) {
            if ($this->_HomePagehandle) {
                return $this->_HomePagehandle;
            }
            else {
                // check db again (maybe someone else created it since
                // we logged in.)
                global $request;
                $this->_HomePagehandle = $request->getPage($this->UserName);
                return $this->_HomePagehandle;
            }
        }
        // nope
        return false;
    }

    function checkPass($submitted_password) {
        // By definition, an undefined user class cannot sign in.
        trigger_error("DEBUG: Warning: undefined _WikiUser class trying to sign in." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

}

class _AnonUser
extends _WikiUser
{
    var $_level = WIKIAUTH_ANON;

    // Anon only gets to load and save prefs in a cookie, that's it.
    function loadPreferences() {
        global $request;
        if ($cookie = $request->getCookieVar(WIKI_NAME)) {
            if (! $unboxedcookie = $this->_prefs->unpack($cookie)) {
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
            if (! $this->UserName || $this->UserName == $unboxedcookie['userid']) {
                $this->_prefs = new UserPreferences($unboxedcookie);
                $this->UserName = $unboxedcookie['userid'];
            }
        }
    }
    function savePreferences() {
        // Allow for multiple wikis in same domain. Encode only the
        // _prefs array of the UserPreference object. Ideally the
        // prefs array should just be imploded into a single string or
        // something so it is completely human readable by the end
        // user. In that case stricter error checking will be needed
        // when loading the cookie.
        setcookie(WIKI_NAME, $this->_prefs->pack($this->_prefs->getAll()),
                  COOKIE_EXPIRATION_DAYS, COOKIE_DOMAIN);
    }

    function checkPass($submitted_password) {
        // By definition, the _AnonUser does not HAVE a password
        // (compared to _BogoUser, who has an EMPTY password).
        trigger_error("DEBUG: Warning: _AnonUser unexpectedly asked to checkPass()." . " "
                      . "Check isa($user, '_PassUser'), or: isa($user, '_AdminUser') etc. first." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

}

/**
 * Do NOT extend _BogoUser to other classes, for checkPass()
 * security. (In case of defects in code logic of the new class!)
 */
class _BogoUser
extends _AnonUser
{
    var $_level = WIKIAUTH_BOGO;

    function checkPass($submitted_password) {
        // By definition, BogoUser has an empty password.
        return true;
    }
}

class _PassUser
extends _AnonUser
/**
 * New classes for externally authenticated users should extend from
 * this class.
 * 
 * For now, the prefs $restored_from_page stuff is in here, but that
 * will soon be moved into a new PersonalPage PassUser class or
 * something, thus leaving this as a more generic passuser class from
 * which other new authentication classes (and preference storage
 * types) can extend.
 */
{
    var $_level = WIKIAUTH_USER;

    //TODO: password changing
    //TODO: email verification

    function loadPreferences() {
        // We don't necessarily have to read the cookie first. Since
        // the user has a password, the prefs stored in the homepage
        // cannot be arbitrarily altered by other Bogo users.
        _AnonUser::loadPreferences();
        // User may have deleted cookie, retrieve from his
        // NamesakePage if there is one.
        if ((! $this->_prefs) && $this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->unpack($this->_HomePagehandle->get('_prefs'))) {
                $this->_prefs = new UserPreferences($restored_from_page);
            }
        }
    }
    function savePreferences() {
        _AnonUser::savePreferences();
        // Encode only the _prefs array of the UserPreference object
        $serialized = $this->_prefs->pack($this->_prefs->getAll());
        $this->_HomePagehandle->set('_prefs', $serialized);
    }

    //TODO: alternatively obtain $stored_password from external auth
    function checkPass($submitted_password) {
        $stored_password = $this->_prefs->get('passwd');
        return $this->_checkPass($submitted_password, $stored_password);
    }

    //TODO: remove crypt() function check from config.php:396
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
}

/**
 * For security, this class should not be extended. Instead, extend
 * from _PassUser (think of this as unix "root").
 */
class _AdminUser
extends _PassUser
{
    var $_level = WIKIAUTH_ADMIN;

    function checkPass($submitted_password) {
        $stored_password = ADMIN_PASSWD;
        return $this->_checkPass($submitted_password, $stored_password);
    }
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

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

// don't save default preferences for efficiency.
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
        //trigger_error("DEBUG: Can't unpack bad UserPreferences",
        //E_USER_WARNING);
        return false;
    }

    function hash () {
        return hash($this->_prefs);
    }
}


// $Log: not supported by cvs2svn $
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
