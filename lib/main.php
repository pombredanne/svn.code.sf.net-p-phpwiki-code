<?php
rcs_id('$Id: main.php,v 1.28 2002-01-23 05:10:22 dairiki Exp $');


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

class UserPreferences {
    function UserPreferences ($prefs = false) {
        if (isa($prefs, 'UserPreferences'))
            $this->_prefs = $prefs->_prefs;
        else
            $this->_prefs = array();

        $this->sanitize();
    }

    function sanitize() {
        // FIXME: needs cleanup.
        
        $LIMITS = array('edit_area.width' => array(30, 80, 150),
                        'edit_area.height' => array(5, 22, 80));

        $prefs = $this->_prefs;
        $new = array();
        foreach ($LIMITS as $key => $lims) {
            list ($min, $default, $max) = $lims;
            if (isset($prefs[$key]))
                $new[$key] = min($max, max($min, (int)$prefs[$key]));
            else
                $new[$key] = $default;
        }

        $this->_prefs = $new;
    }

    function get($key) {
        return $this->_prefs[$key];
    }

    function setPrefs($new_prefs) {
        if (is_array($new_prefs)) {
            $this->_prefs = array_merge($this->_prefs, $new_prefs);
            $this->sanitize();
        }
    }
}


class WikiRequest extends Request {

    function WikiRequest () {
        $this->Request();

        // Normalize args...
        $this->setArg('pagename', $this->_deducePagename());
        $this->setArg('action', $this->_deduceAction());

        $this->_user = new WikiUser($this->getSessionVar('auth_state'));

        if (!($prefs = $this->getSessionVar('user_prefs')))
            $prefs = $this->getCookieVar('WIKI_PREFS');
        $this->_prefs = new UserPreferences($prefs);
    }

    function getUser () {
        return $this->_user;
    }

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



    function handleAuthRequest () {
        $auth_args = $this->getArg('auth');
        $this->setArg('auth', false);
        
        if (!is_array($auth_args) || !$this->isPost())
            return;             // Ignore if not posted.
        
        $user = WikiUser::AuthCheck($auth_args);

        if (isa($user, 'WikiUser')) {
            // Successful login (or logout.)
            $this->_user = $user;
            $this->setSessionVar('auth_state', $user);
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

    function checkAuthority () {
        $action = $this->getArg('action');
        $require_level = $this->requiredAuthority($action);
        
        $user = $this->getUser();
        if (! $user->hasAuthority($require_level)) {
            // User does not have required authority.  Prompt for login.
            $what = HTML::em($action);

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
        
    function handleSetPrefRequest () {
        $new_prefs = $this->getArg('pref');
        $this->setArg('pref', false);
        if (!is_array($pref_args))
            return;

        // Update and save preferences.
        $this->_prefs->setPrefs($new_prefs);
        $this->setSessionVar('user_prefs', $prefs);
        $this->setCookieVar('WIKI_PREFS', $prefs, 365);
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

    // Handle authentication requests
    if ($request->getArg('auth'))
        $request->handleAuthRequest();

    // FIXME: deprecated
    //global $user;               // FIXME: can we make this non-global?
    //$user = $request->getUser();

    // Handle adjustments to user preferences
    if ($request->getArg('pref'))
        $request->handleSetPrefRequest();

    // Enable the output of most of the warning messages.
    // The warnings will screw up zip files and setpref though.
    global $ErrorManager;
    if ($request->getArg('action') != 'zip') {
        //$ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE);
        $ErrorManager->setPostponedErrorMask(0);
    }

    
    // Ensure user has permissions for action
    $request->checkAuthority();
    
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
