<?php
rcs_id('$Id: main.php,v 1.70 2002-08-24 13:18:56 rurban Exp $');

define ('DEBUG', 1);
define ('USE_PREFS_IN_PAGE', true);

include "lib/config.php";
include "lib/stdlib.php";
require_once('lib/Request.php');
require_once("lib/WikiUser.php");
require_once('lib/WikiDB.php');

class WikiRequest extends Request {

    function WikiRequest () {
        $this->Request();

        // Normalize args...
        $this->setArg('pagename', $this->_deducePagename());
        $this->setArg('action', $this->_deduceAction());

        // Restore auth state
        $this->_user = new WikiUser($this->_deduceUsername());
        $this->_deduceUsername();
        // WikiDB Auth later
        // $this->_user = new WikiDB_User($this->getSessionVar('wiki_user'), $this->getAuthDbh());
        $this->_prefs = $this->_user->getPreferences();
    }

    // This really maybe should be part of the constructor, but since it
    // may involve HTML/template output, the global $request really needs
    // to be initialized before we do this stuff.
    function updateAuthAndPrefs () {
    	global $Theme;
        // Handle preference updates, an authentication requests, if any.
        if ($new_prefs = $this->getArg('pref')) {
            $this->setArg('pref', false);
            if ($this->isPost() and !empty($new_prefs['passwd']) and 
                ($new_prefs['passwd2'] != $new_prefs['passwd'])) {
                $this->_prefs->set('passwd','');
                // $this->_prefs->set('passwd2',''); // This is not stored anyway
                include_once("themes/" . THEME . "/themeinfo.php");
                return false;
            }
            foreach ($new_prefs as $key => $val) {
            	if ($key == 'passwd') {
            	  $val = crypt('passwd');
            	}
                $this->_prefs->set($key, $val);
            }
        }

        // Handle authentication request, if any.
        if ($auth_args = $this->getArg('auth')) {
            $this->setArg('auth', false);
            include_once("themes/" . THEME . "/themeinfo.php");
            $this->_handleAuthRequest($auth_args); // possible NORETURN
        }
        elseif ( ! $this->_user->isSignedIn() ) {
            // If not auth request, try to sign in as saved user.
            if (($saved_user = $this->getPref('userid')) != false) {
            	include_once("themes/" . THEME . "/themeinfo.php");
                $this->_signIn($saved_user);
            }
        }

        // Save preferences in session and cookie
        $id_only = true;
        $this->_user->setPreferences($this->_prefs, $id_only);
        /*
        if ($theme = $this->getPref('theme') ) {
            // Load user-defined theme
            include_once("themes/$theme/themeinfo.php");
        } else {
            // site theme
            include_once("themes/" . THEME . "/themeinfo.php");
        }
        */
        if (empty($Theme)) {
            include_once("themes/" . THEME . "/themeinfo.php");
        }
        if (empty($Theme)) {
            include_once("themes/default/themeinfo.php");
        }
        assert(!empty($Theme));

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
            // needs PHP 4.1. better use $this->_user->...
            $this->_dbi = WikiDB::open($GLOBALS['DBParams']);
        }
        return $this->_dbi;
    }

    function getAuthDbh () {
        global $DBParams, $DBAuthParams;
        if (!isset($this->_auth_dbi)) {
            if ($DBParams['dbtype'] == 'dba' or empty($DBAuthParams['auth_dsn']))
                $this->_auth_dbi = $this->getDbh(); // use phpwiki database 
            elseif ($DBAuthParams['auth_dsn'] == $DBParams['dsn'])
                $this->_auth_dbi = $this->getDbh(); // same phpwiki database 
            else // use external database 
                // needs PHP 4.1. better use $this->_user->...
                $this->_auth_dbi = WikiDB_User::open($DBAuthParams);
        }
        return $this->_auth_dbi;
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

        // Ignore password unless POST'ed.
        if (!$this->isPost())
            unset($auth_args['passwd']);

        $user = $this->_user->AuthCheck($auth_args);

        if (isa($user, 'WikiUser')) {
            // Successful login (or logout.)
            $this->_setUser($user);
        }
        elseif ($user) {
            // Login attempt failed.
            $fail_message = $user;
            $auth_args['pass_required'] = true;
            // If no password was submitted, it's not really
            // a failure --- just need to prompt for password...
            if (!isset($auth_args['passwd'])) {
                 //$auth_args['pass_required'] = false;
                 $fail_message = false;
            }
            $this->_user->PrintLoginForm($this, $auth_args, $fail_message);
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
        $user = $this->_user->AuthCheck(array('userid' => $userid));
        if (isa($user, 'WikiUser')) {
            $this->_setUser($user); // success!
        }
    }

    function _setUser ($user) {
        $this->_user = $user;
        $this->setCookieVar('WIKI_ID', $user->_userid, 365);
        $this->setSessionVar('wiki_user', $user);
        if ($user->isSignedIn())
            $user->_authhow = 'signin';

        // Save userid to prefs..
        $this->_prefs->set('userid',
                           $user->isSignedIn() ? $user->getId() : '');
    }

    function _notAuthorized ($require_level) {
        // User does not have required authority.  Prompt for login.
        $what = $this->getActionDescription($this->getArg('action'));

        if ($require_level >= WIKIAUTH_FORBIDDEN) {
            $this->finish(fmt("%s is disallowed on this wiki.",
                              $this->getDisallowedActionDescription($this->getArg('action'))));
        }
        elseif ($require_level == WIKIAUTH_BOGO)
            $msg = fmt("You must sign in to %s.", $what);
        elseif ($require_level == WIKIAUTH_USER)
            $msg = fmt("You must log in to %s.", $what);
        else
            $msg = fmt("You must be an administrator to %s.", $what);
        $pass_required = ($require_level >= WIKIAUTH_USER);

        $this->_user->PrintLoginForm($this, compact('require_level','pass_required'), $msg);
        $this->finish();    // NORETURN
    }

    function getActionDescription($action) {
        static $actionDescriptions;
        if (! $actionDescriptions) {
            $actionDescriptions
            = array('browse'     => _("browse pages in this wiki"),
                    'diff'       => _("diff pages in this wiki"),
                    'dumphtml'   => _("dump html pages from this wiki"),
                    'dumpserial' => _("dump serial pages from this wiki"),
                    'edit'       => _("edit pages in this wiki"),
                    'loadfile'   => _("load files into this wiki"),
                    'lock'       => _("lock pages in this wiki"),
                    'remove'     => _("remove pages from this wiki"),
                    'unlock'     => _("unlock pages in this wiki"),
                    'upload'     => _("upload a zip dump to this wiki"),
                    'verify'     => _("verify the current action"),
                    'viewsource' => _("view the source of pages in this wiki"),
                    'zip'        => _("download a zip dump from this wiki"),
                    'ziphtml'    => _("download an html zip dump from this wiki")
                    );
        }
        if (in_array($action, array_keys($actionDescriptions)))
            return $actionDescriptions[$action];
        else
            return $action;
    }
    function getDisallowedActionDescription($action) {
        static $disallowedActionDescriptions;
        if (! $disallowedActionDescriptions) {
            $disallowedActionDescriptions
            = array('browse'     => _("Browsing pages"),
                    'diff'       => _("Diffing pages"),
                    'dumphtml'   => _("Dumping html pages"),
                    'dumpserial' => _("Dumping serial pages"),
                    'edit'       => _("Editing pages"),
                    'loadfile'   => _("Loading files"),
                    'lock'       => _("Locking pages"),
                    'remove'     => _("Removing pages"),
                    'unlock'     => _("Unlocking pages"),
                    'upload'     => _("Uploading zip dumps"),
                    'verify'     => _("Verify the current action"),
                    'viewsource' => _("Viewing the source of pages"),
                    'zip'        => _("Downloading zip dumps"),
                    'ziphtml'    => _("Downloading html zip dumps")
                    );
        }
        if (in_array($action, array_keys($disallowedActionDescriptions)))
            return $disallowedActionDescriptions[$action];
        else
            return $action;
    }

    function requiredAuthority ($action) {
        // FIXME: clean up. 
        // Todo: Check individual page permissions instead.
        switch ($action) {
            case 'browse':
            case 'viewsource':
            case 'diff':
                return WIKIAUTH_ANON;

            case 'zip':
                if (defined('ZIPDUMP_AUTH') && ZIPDUMP_AUTH)
                    return WIKIAUTH_ADMIN;
                return WIKIAUTH_ANON;

            case 'ziphtml':
                if (defined('ZIPDUMP_AUTH') && ZIPDUMP_AUTH)
                    return WIKIAUTH_ADMIN;
                return WIKIAUTH_ANON;

            case 'edit':
                if (defined('REQUIRE_SIGNIN_BEFORE_EDIT') && REQUIRE_SIGNIN_BEFORE_EDIT)
                    return WIKIAUTH_BOGO;
                return WIKIAUTH_ANON;
                // return WIKIAUTH_BOGO;

            case 'upload':
            case 'dumpserial':
            case 'dumphtml':
            case 'loadfile':
            case 'remove':
            case 'lock':
            case 'unlock':
                return WIKIAUTH_ADMIN;
            default:
                // Temp workaround for french single-word action pages 'Historique'
                // Some of this make sense as SubPage actions or buttons.
                $singleWordActionPages = 
                    array("Historique", "Info",
                          _("Preferences"), _("Administration"), 
                          _("Today"), _("Help"));
                if (in_array($action, $singleWordActionPages))
                    return WIKIAUTH_ANON; // ActionPage.
                global $WikiNameRegexp;
                if (preg_match("/$WikiNameRegexp\Z/A", $action))
                    return WIKIAUTH_ANON; // ActionPage.
                else
                    return WIKIAUTH_ADMIN;
        }
    }

    function possiblyDeflowerVirginWiki () {
        if ($this->getArg('action') != 'browse')
            return;
        if ($this->getArg('pagename') != HOME_PAGE)
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
        if (method_exists($this, $method)) {
            $this->{$method}();
        }
        elseif ($this->isActionPage($action)) {
            $this->actionpage($action);
        }
        else {
            $this->finish(fmt("%s: Bad action", $action));
        }
    }


    function finish ($errormsg = false) {
        static $in_exit = 0;

        if ($in_exit)
            exit();        // just in case CloseDataBase calls us
        $in_exit = true;

        if (!empty($this->_dbi))
            $this->_dbi->close();
        unset($this->_dbi);


        global $ErrorManager;
        $ErrorManager->flushPostponedErrors();

        if (!empty($errormsg)) {
            PrintXML(HTML::br(),
                     HTML::hr(),
                     HTML::h2(_("Fatal PhpWiki Error")),
                     $errormsg);
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

        return HOME_PAGE;
    }

    function _deduceAction () {
        if (!($action = $this->getArg('action')))
            return 'browse';

        if (method_exists($this, "action_$action"))
            return $action;

        // Allow for, e.g. action=LikePages
        if ($this->isActionPage($action))
            return $action;

        // Check for _('PhpWikiAdministration').'/'._('Remove') actions
        $pagename = $this->getArg('pagename');
        if (strstr($pagename,_('PhpWikiAdministration')))
            return $action;

        trigger_error("$action: Unknown action", E_USER_NOTICE);
        return 'browse';
    }

    function _deduceUsername () {
        if ($userid = $this->getSessionVar('wiki_user')) {
            if (!empty($this->_user))
                $this->_user->authhow = 'session';
            return $userid;
        }
        if ($userid = $this->getCookieVar('WIKI_ID')) {
            if (!empty($this->_user))
                $this->_user->authhow = 'cookie';
            return $userid;
        }
        return false;
    }
    
    function isActionPage ($pagename) {
        if (isSubPage($pagename)) 
            $subpagename = subPageSlice($pagename,-1); // last element
        else 
            $subpagename = $pagename;
        // Temp workaround for french single-word action page 'Historique'
        $singleWordActionPages = array("Historique", "Info", _('Preferences'));
        if (! in_array($subpagename, $singleWordActionPages)) {
            // Allow for, e.g. action=LikePages
            global $WikiNameRegexp;
            if (!preg_match("/$WikiNameRegexp\\Z/A", $subpagename))
                return false;
        }
        $dbi = $this->getDbh();
        $page = $dbi->getPage($pagename);
        $rev = $page->getCurrentRevision();
        // FIXME: more restrictive check for sane plugin?
        if (strstr($rev->getPackedContent(), '<?plugin'))
            return true;
        trigger_error("$pagename: Does not appear to be an 'action page'", E_USER_NOTICE);
        return false;
    }

    function action_browse () {
        $this->compress_output();
        include_once("lib/display.php");
        displayPage($this);
    }

    function action_verify () {
        $this->action_browse();
    }

    function actionpage ($action) {
        $this->compress_output();
        include_once("lib/display.php");
        actionPage($this, $action);
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
        $e = new PageEditor ($this);
        $e->editPage();
    }

    function action_viewsource () {
        $this->compress_output();
        include "lib/editpage.php";
        $e = new PageEditor ($this);
        $e->viewSource();
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
        $pagename = $this->getArg('pagename');
        if (strstr($pagename,_('PhpWikiAdministration'))) {
            $this->action_browse();
        } else {
            include('lib/removepage.php');
            RemovePage($this);
        }
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

    function action_ziphtml () {
        include_once("lib/loadsave.php");
        MakeWikiZipHtml($this);
        // I don't think it hurts to add cruft at the end of the zip file.
        echo "\n========================================================\n";
        echo "PhpWiki " . PHPWIKI_VERSION . " source:\n$GLOBALS[RCS_IDS]\n";
    }

    function action_dumpserial () {
        include_once("lib/loadsave.php");
        DumpToDir($this);
    }

    function action_dumphtml () {
        include_once("lib/loadsave.php");
        DumpHtmlToDir($this);
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
    $request->updateAuthAndPrefs();

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
    // The warnings will screw up zip files though.
    global $ErrorManager;
    if (substr($request->getArg('action'), 0, 3) != 'zip') {
        $ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE);
        //$ErrorManager->setPostponedErrorMask(0);
    }

    //FIXME:
    //if ($user->is_authenticated())
    //  $LogEntry->user = $user->getId();

    $request->possiblyDeflowerVirginWiki();

    $request->handleAction();
if (defined('DEBUG') and DEBUG>1) phpinfo(INFO_VARIABLES);
    $request->finish();
}

// Used for debugging purposes
function getmicrotime(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
if (defined('DEBUG')) $GLOBALS['debugclock'] = getmicrotime();

main();


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
