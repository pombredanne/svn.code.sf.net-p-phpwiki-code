<?php //-*-php-*-
rcs_id('$Id: WikiUserNew.php,v 1.1 2003-12-02 05:46:36 carstenklapp Exp $');

// This is a complete rewrite of the old WikiUser code but it is not
// implemented yet.  Much of the existing UserPreferences class should
// work fine with this but a few other parts of PhpWiki need to be
// refitted. --Carsten


// Returns a user object, which contains the user's preferences.
//
// Given no name, returns an anonymous user object (who may or may not
// have a cookie).  Given a user name, returns a bogo user object (who
// may or may not have a cookie and/or NamesakePage).
//
// Takes care of all preference loading/storing in the user's page and
// any cookies.
function WikiUser ($UserName = '') {
    if ($UserName) {
        return new _BogoUser($UserName);
    }
    else {
        // check for autologin pref in cookie and upgrade user object
        $_AnonUser = new _AnonUser();
        if ($_AnonUser->UserName && $_AnonUser->_prefs->get('autologin')) {
            return new _BogoUser($_AnonUser->UserName);
        }
        return $_AnonUser;
    }
    // For the future... think about...
    // if (isa($user, 'AdminUser'))
    // if (isa($user, 'DeactivatedUser'))
    // etc.
}


// Base _WikiUser class.
class _WikiUser ($UserName = '') {
    var $UserName = '';

    var $_prefs  = false;
    var _HomePagehandle = false;

    // constructor
    function WikiUser($UserName) {
        if ($UserName) {
            $this->UserName = $UserName;
            global $dbi;
            $this->_HomePagehandle = $this->hasHomePage();
        }
        $this->loadPreferences();
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
                $this->_HomePagehandle = $dbi->getPage($this->UserName);
                return $this->_HomePagehandle;
            }
        }
        // nope
        return false;
    }


    // TODO: move these two methods into UserPreferences class
    function pack($nonpacked) {
        return serialize($nonpacked);
    }
    function unpack($packed) {
        if (!$packed)
            return false;
        if (substr($packed,0,2) == "O:") {
            // Looks like a serialized object
            return unserialize($packed);
        }
        //trigger_error("DEBUG: Can't unpack bad UserPreferences",
        //E_USER_WARNING);
        return false;
    }
}

class _AnonUser extends _WikiUser {
    // Anon only gets to load and save prefs in a cookie, that's it.
    function loadPreferences() {
        global $request;
        if ($cookie = $request->getCookieVar(WIKI_NAME)) {
            if (! $unboxedcookie = $this->unpack($cookie)) {
                trigger_error(_("Format of UserPreferences cookie not recognised. Default preferences will be used instead."),
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
             * username). (Remember, _BogoUser inherits this function
             * too!).
             */
            if (! $this->UserName || $this->UserName == $unboxedcookie['userid']) {
                $this->_prefs = new UserPreferences($unboxedcookie);
                $this->UserName = $unboxedcookie['userid'];
            }
        }
    }
    function savePreferences() {
        // Allow for multiple wikis in same domain.  Encode only the
        // _prefs array of the UserPreference object. Ideally the
        // prefs array should just be imploded into a single string or
        // something so it is completely human readable by the end
        // user. In that case stricter error checking will be needed
        // when loading the cookie.
        setcookie(WIKI_NAME, $this->pack($this->_prefs->getAll()), 365, '/');
    }
}

class _BogoUser extends _AnonUser {
    var $_level  = WIKIAUTH_BOGO;

    function _BogoUser($UserName) {
        $this->_username = $UserName;
        $this->_prefs = $this->loadPreferences();
    }

    function loadPreferences() {
        // Read cookie first, Bogo's homepage prefs could have been
        // altered.
        _AnonUser::loadPreferences();
        // User may have deleted cookie, retrieve from his
        // NamesakePage if there is one.
        if ((! $this->_prefs) && $this->_HomePagehandle) {
            if ($restored_from_page = $this->unpack($this->_HomePagehandle->get('_prefs'))) {
                $this->_prefs = new UserPreferences($restored_from_page);
            }
        }
    }
    function savePreferences() {
        _AnonUser::savePreferences();
        // Encode only the _prefs array of the UserPreference object
        $serialized = $this->pack($this->_prefs->getAll());
        $this->_HomePagehandle->set('_prefs', $serialized);
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
