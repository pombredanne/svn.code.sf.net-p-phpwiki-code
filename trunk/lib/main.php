<?php
rcs_id('$Id: main.php,v 1.29 2002-01-23 19:21:43 dairiki Exp $');


include "lib/config.php";
include "lib/stdlib.php";
require_once('lib/Request.php');
require_once("lib/WikiUser.php");
require_once('lib/WikiDB.php');

// FIXME: move to config?
if (defined('THEME')) {
    include("themes/" . THEME . "/themeinfo.php");
}
if (empty($Theme)) {
    include("themes/default/themeinfo.php");
}
assert(!empty($Theme));

class _UserPreference
{
    function _UserPreference ($default_value) {
        $this->default_value = $default_value;
    }

    function sanify ($value) {
        return (string) $value;
    }
}

class _UserPreference_int extends _UserPreference 
{
    function _UserPreference_int ($default, $minval = false, $maxval = false) {
        $this->_UserPreference((int) $default);
        $this->_minval = (int) $minval;
        $this->_maxval = (int) $maxval;
    }
    
    function sanify ($value) {
        $value = (int) $value;
        if ($this->_minval !== false && $value < $this->_minval)
            $value = $this->_minval;
        if ($this->_maxval !== false && $value > $this->_maxval)
            $value = $this->_maxval;
        return $value;
    }
}

$UserPreferences = array('editWidth' => new _UserPreference_int(80, 30, 150),
                         'editHeight' => new _UserPreference_int(22, 5, 80),
                         'userid' => new _UserPreference(''));

class UserPreferences {
    function UserPreferences ($saved_prefs = false) {
        $this->_prefs = array();

        if (isa($saved_prefs, 'UserPreferences')) {
            foreach ($saved_prefs->_prefs as $name => $value)
                $this->set($name, $value);
        }
    }

    function _getPref ($name) {
        global $UserPreferences;
        if (!isset($UserPreferences[$name])) {
            trigger_error("$key: unknown preference", E_USER_NOTICE);
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
        $this->_prefs[$name] = $pref->sanify($value);
    }
}


class WikiRequest extends Request {

    function WikiRequest () {
        $this->Request();
    }

    // FIXME: make $request an argument of WikiTemplate,
    // then subsume _init into WikiRequest().
    function _init () {
        // Normalize args...
        $this->setArg('pagename', $this->_deducePagename());
        $this->setArg('action', $this->_deduceAction());

        // Restore auth state
        $this->_user = new WikiUser($this->getSessionVar('auth_state'));

        // Restore saved preferences
        if (!($prefs = $this->getCookieVar('WIKI_PREFS')))
            $prefs = $this->getSessionVar('user_prefs');
        $this->_prefs = new UserPreferences($prefs);

        // Handle preference updates, an authentication requests, if any.
        if ($new_prefs = $this->getArg('pref')) {
            $this->setArg('pref', false);
            $this->_setPreferences($new_prefs);
        }

        // Handle authentication request, if any.
        if ($auth_args = $this->getArg('auth')) {
            $this->setArg('auth', false);
            $this->_handleAuthRequest($auth_args); // possible NORETURN
        }
        
        if (!$auth_args && !$this->_user->isSignedIn()) {
            // Try to sign in as saved user.
            if (($saved_user = $this->getPref('userid')) != false)
                $this->_signIn($saved_user);
        }

        // Ensure user has permissions for action
        $require_level = $this->requiredAuthority($this->getArg('action'));
        if (! $this->_user->hasAuthority($require_level))
            $this->_notAuthorized($require_level); // NORETURN
    }

    function getUser () {
        return $this->_user;
    }

    function getPrefs () {
        return $this->_prefs;
    }

    // Convenience function:
    function getPref ($key) {
        return $this->_prefs->get($key);
    }

    function getDbh () {
        if (!isset($this->_dbi)) {
            $this->_dbi = WikiDB::open($GLOBALS['DBParams']);
        }
        return $this->_dbi;
    }

    /**
     * Get requested page from the page database.
     *
     * This is a convenience function.
     */
    function getPage () {
        if (!isset($this->_dbi))
            $this->getDbh();
        return $this->_dbi->getPage($this->getArg('pagename'));
    }

    function _handleAuthRequest ($auth_args) {
        if (!is_array($auth_args))
            return;

        // Ignore password unless POSTed.
        if (!$this->isPost())
            unset($auth_args['password']);

        $user = WikiUser::AuthCheck($auth_args);

        if (isa($user, 'WikiUser')) {
            // Successful login (or logout.)
            $this->_setUser($user);
        }
        elseif ($user) {
            // Login attempt failed.
            $fail_message = $user;
            WikiUser::PrintLoginForm($auth_args, $fail_message);
            $this->finish();    //NORETURN
        }
        else {
            // Login request cancelled.
        }
    }

    /**
     * Attempt to sign in (bogo-login).
     *
     * Fails silently.
     *
     * @param $userid string Userid to attempt to sign in as.
     * @access private
     */
    function _signIn ($userid) {
        $user = WikiUser::AuthCheck(array('userid' => $userid));
        if (isa($user, 'WikiUser'))
            $this->_setUser($user); // success!
    }

    function _setUser ($user) {
        $this->_user = $user;
        $this->setSessionVar('auth_state', $user);
        // Save userid to prefs..
        if ($user->isSignedIn())
            $this->_setPreferences(array('userid' => $user->getId()));
        else
            $this->_setPreferences(array('userid' => false));
    }

    function _notAuthorized ($require_level) {
        // User does not have required authority.  Prompt for login.
        $what = HTML::em($this->getArg('action'));

        if ($require_level >= WIKIAUTH_FORBIDDEN) {
            $this->finish(fmt("Action %s is disallowed on this wiki", $what));
        }
        elseif ($require_level == WIKIAUTH_BOGO)
            $msg = fmt("You must sign in to %s this wiki", $what);
        elseif ($require_level == WIKIAUTH_USER)
            $msg = fmt("You must log in to %s this wiki", $what);
        else
            $msg = fmt("You must be an administrator to %s this wiki", $what);
        
        WikiUser::PrintLoginForm(compact('require_level'), $msg);
        $this->finish();    // NORETURN
    }
    
    function requiredAuthority ($action) {
        // FIXME: clean up.
        switch ($action) {
        case 'browse':
        case 'diff':
        // case ActionPage:   
        // case 'search':
            return WIKIAUTH_ANON;

        case 'zip':
            return WIKIAUTH_ANON;
            
        case 'edit':
        case 'save':            // FIXME delete
            return WIKIAUTH_ANON;
            // return WIKIAUTH_BOGO;

        case 'upload':
        case 'dumpserial':
        case 'loadfile':
        case 'remove':
        case 'lock':
        case 'unlock':
        default:
            return WIKIAUTH_ADMIN;
        }
    }
        
    function _setPreferences ($new_prefs) {
        if (!is_array($new_prefs))
            return;

        // Update and save preferences.
        foreach ($new_prefs as $name => $value)
            $this->_prefs->set($name, $value);
        
        $this->setSessionVar('user_prefs', $this->_prefs);
        $this->setCookieVar('WIKI_PREFS', $this->_prefs, 365);
    }

    function deflowerDatabase () {
        if ($this->getArg('action') != 'browse')
            return;
        if ($this->getArg('pagename') != _("HomePage"))
            return;

        $page = $this->getPage();
        $current = $page->getCurrentRevision();
        if ($current->getVersion() > 0)
            return;             // Homepage exists.

        include('lib/loadsave.php');
        SetupWiki($this);
        $this->finish();        // NORETURN
    }

    function handleAction () {
        $action = $this->getArg('action');
        $method = "action_$action";
        if (! method_exists($this, $method)) {
            $this->finish(fmt("%s: Bad action", $action));
        }
        $this->{$method}();
    }
        
        
    function finish ($errormsg = false) {
        static $in_exit = 0;

        if ($in_exit)
            exit();		// just in case CloseDataBase calls us
        $in_exit = true;

        if (!empty($this->_dbi))
            $this->_dbi->close();
        unset($this->_dbi);
        

        global $ErrorManager;
        $ErrorManager->flushPostponedErrors();
   
        if (!empty($errormsg)) {
            PrintXML(array(HTML::br(),
                           HTML::hr(),
                           HTML::h2(_("Fatal PhpWiki Error")),
                           $errormsg));
            // HACK:
            echo "\n</body></html>";
        }

        Request::finish();
        exit;
    }
        
    function _deducePagename () {
        if ($this->getArg('pagename'))
            return $this->getArg('pagename');

        if (USE_PATH_INFO) {
            $pathinfo = $this->get('PATH_INFO');
            $tail = substr($pathinfo, strlen(PATH_INFO_PREFIX));
            
            if ($tail && $pathinfo == PATH_INFO_PREFIX . $tail) {
                return $tail;
            }
        }

        $query_string = $this->get('QUERY_STRING');
        if (preg_match('/^[^&=]+$/', $query_string)) {
            return urldecode($query_string);
        }
        
    
        return _("HomePage");
    }

    
    function _deduceAction () {
        if (!($action = $this->getArg('action')))
            return 'browse';

        if (! method_exists($this, "action_$action")) {
            // unknown action.
            trigger_error("$action: Unknown action", E_USER_NOTICE);
            return 'browse';
        }
        return $action;
    }

    function action_browse () {
        $this->compress_output();
        include_once("lib/display.php");
        displayPage($this);
    }
    
    function action_diff () {
        $this->compress_output();
        include_once "lib/diff.php";
        showDiff($this);
    }

    function action_search () {
        // This is obsolete: reformulate URL and redirect.
        // FIXME: this whole section should probably be deleted.
        if ($this->getArg('searchtype') == 'full') {
            $search_page = _("FullTextSearch");
        }
        else {
            $search_page = _("TitleSearch");
        }
        $this->redirect(WikiURL($search_page,
                                array('s' => $this->getArg('searchterm')),
                                'absolute_url'));
    }

    function action_edit () {
        $this->compress_output();
        include "lib/editpage.php";
        editPage($this);
    }

    // FIXME: combine this with edit
    function action_save () {
        $this->compress_output();
        include "lib/savepage.php";
        savePage($this);
    }

    function action_lock () {
        $page = $this->getPage();
        $page->set('locked', true);
        $this->action_browse();
    }

    function action_unlock () {
        // FIXME: This check is redundant.
        //$user->requireAuth(WIKIAUTH_ADMIN);
        $page = $this->getPage();
        $page->set('locked', false);
        $this->action_browse();
    }

    function action_remove () {
        // FIXME: This check is redundant.
        //$user->requireAuth(WIKIAUTH_ADMIN);
        include('lib/removepage.php');
    }

    
    function action_upload () {
        include_once("lib/loadsave.php");
        LoadPostFile($this);
    }
    
    function action_zip () {
        include_once("lib/loadsave.php");
        MakeWikiZip($this);
        // I don't think it hurts to add cruft at the end of the zip file.
        echo "\n========================================================\n";
        echo "PhpWiki " . PHPWIKI_VERSION . " source:\n$GLOBALS[RCS_IDS]\n";
    }
        
    function action_dumpserial () {
        include_once("lib/loadsave.php");
        DumpToDir($this);
    }

    function action_loadfile () {
        include_once("lib/loadsave.php");
        LoadFileOrDir($this);
    }
}

//FIXME: deprecated
function is_safe_action ($action) {
    return WikiRequest::requiredAuthority($action) < WIKIAUTH_ADMIN;
}


function main () {
    global $request;

    $request = new WikiRequest();
    $request->_init();
    
    /* FIXME: is this needed anymore?
    if (USE_PATH_INFO && ! $request->get('PATH_INFO')
        && ! preg_match(',/$,', $request->get('REDIRECT_URL'))) {
        $request->redirect(SERVER_URL
                           . preg_replace('/(\?|$)/', '/\1',
                                          $request->get('REQUEST_URI'),
                                          1));
        exit;
    }
    */

    // Enable the output of most of the warning messages.
    // The warnings will screw up zip files and setpref though.
    global $ErrorManager;
    if ($request->getArg('action') != 'zip') {
        //$ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE);
        $ErrorManager->setPostponedErrorMask(0);
    }

    
    //FIXME:
    //if ($user->is_authenticated())
    //  $LogEntry->user = $user->getId();

    $request->deflowerDatabase();

    $request->handleAction();
    $request->finish();
}

main();


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
