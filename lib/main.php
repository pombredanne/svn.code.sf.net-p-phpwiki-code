<?php //-*-php-*-
rcs_id('$Id: main.php,v 1.108 2004-01-28 14:34:14 rurban Exp $');

define ('USE_PREFS_IN_PAGE', true);

include "lib/config.php";
require_once("lib/stdlib.php");
require_once('lib/Request.php');
if (ENABLE_USER_NEW)
  require_once("lib/WikiUserNew.php");
else
  require_once("lib/WikiUser.php");
require_once("lib/WikiGroup.php");
require_once('lib/WikiDB.php');

class WikiRequest extends Request {
    // var $_dbi;

    function WikiRequest () {
        $this->_dbi = WikiDB::open($GLOBALS['DBParams']);

        if (USE_DB_SESSION) {
            include_once('lib/DB_Session.php');
            $prefix = isset($GLOBALS['DBParams']['prefix']) ? $GLOBALS['DBParams']['prefix'] : '';
            $this->_dbsession = & new DB_Session($this->getDbh(),
                                                 $prefix . $GLOBALS['DBParams']['db_session_table']);
        }
        
        $this->Request();

        // Normalize args...
        $this->setArg('pagename', $this->_deducePagename());
        $this->setArg('action', $this->_deduceAction());

        // Restore auth state. This doesn't check for proper authorization!
        if (ENABLE_USER_NEW) {
            $userid = $this->_deduceUsername();	
            $user = WikiUser($userid);
            if (isset($this->_user)) 
                $user = UpgradeUser($this->_user,$user);
            $this->_user = $user;
            $this->_prefs = $this->_user->_prefs;
        } else {
            $this->_user = new WikiUser($this, $this->_deduceUsername());
            $this->_prefs = $this->_user->getPreferences();
        }
    }

    function initializeLang () {
        if ($user_lang = $this->getPref('lang')) {
            //trigger_error("DEBUG: initializeLang() ". $user_lang ." calling update_locale()...");
            update_locale($user_lang);
        }
    }

    function initializeTheme () {
        global $Theme;

        // Load theme
        if ($user_theme = $this->getPref('theme'))
            include_once("themes/$user_theme/themeinfo.php");
        if (empty($Theme) and defined ('THEME'))
            include_once("themes/" . THEME . "/themeinfo.php");
        if (empty($Theme))
            include_once("themes/default/themeinfo.php");
        assert(!empty($Theme));
    }


    // This really maybe should be part of the constructor, but since it
    // may involve HTML/template output, the global $request really needs
    // to be initialized before we do this stuff.
    function updateAuthAndPrefs () {
        
        // Handle preference updates, and authentication requests, if any.
        if ($new_prefs = $this->getArg('pref')) {
            $this->setArg('pref', false);
            if ($this->isPost() and !empty($new_prefs['passwd']) and 
                ($new_prefs['passwd2'] != $new_prefs['passwd'])) {
                // FIXME: enh?
                $this->_prefs->set('passwd','');
                // $this->_prefs->set('passwd2',''); // This is not stored anyway
                return false;
            }
            foreach ($new_prefs as $key => $val) {
                if ($key == 'passwd') {
                    // FIXME: enh?
                    $val = crypt('passwd');
                }
                $this->_prefs->set($key, $val);
            }
        }

        // FIXME: need to move authentication request processing
        // up to be before pref request processing, I think,
        // since logging in may change which preferences
        // we're talking about...

        // even we have disallow anon users?
        // if (! $this->_user ) $this->_user = new _AnonUser();	

        // Handle authentication request, if any.
        if ($auth_args = $this->getArg('auth')) {
            $this->setArg('auth', false);
            $this->_handleAuthRequest($auth_args); // possible NORETURN
        }
        elseif ( ! $this->_user or ! $this->_user->isSignedIn() ) {
            // If not auth request, try to sign in as saved user.
            if (($saved_user = $this->getPref('userid')) != false) {
                $this->_signIn($saved_user);
            }
        }

        // Save preferences in session and cookie
        // FIXME: hey! what about anonymous users?   Can't they have
        // preferences too?

        $id_only = true; 
        $this->_user->setPreferences($this->_prefs, $id_only);

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
        if (isset($this->_prefs))
            return $this->_prefs->get($key);
    }

    function getDbh () {
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
     * By default it will grab the page requested via the URL
     *
     * This is a convenience function.
     * @param string $pagename Name of page to get.
     * @return WikiDB_Page Object with methods to pull data from
     * database for the page requested.
     */
    function getPage ($pagename = false) {
        if (!isset($this->_dbi))
            $this->getDbh();
        if (!$pagename) 
            $pagename = $this->getArg('pagename');
        return $this->_dbi->getPage($pagename);
    }

    /** Get URL for POST actions.
     *
     * Officially, we should just use SCRIPT_NAME (or some such),
     * but that causes problems when we try to issue a redirect, e.g.
     * after saving a page.
     *
     * Some browsers (at least NS4 and Mozilla 0.97 won't accept
     * a redirect from a page to itself.)
     *
     * So, as a HACK, we include pagename and action as query args in
     * the URL.  (These should be ignored when we receive the POST
     * request.)
     */
    function getPostURL ($pagename=false) {
        if ($pagename === false)
            $pagename = $this->getArg('pagename');
        $action = $this->getArg('action');
        if ($this->getArg('start_debug')) // zend ide support
            return WikiURL($pagename, array('action' => $action, 'start_debug' => 1));
        else
            return WikiURL($pagename, array('action' => $action));
    }
    
    function _handleAuthRequest ($auth_args) {
        if (!is_array($auth_args))
            return;

        // Ignore password unless POST'ed.
        if (!$this->isPost())
            unset($auth_args['passwd']);

        $olduser = $this->_user;
        $user = $this->_user->AuthCheck($auth_args);
        if (isa($user,WikiUserClassname())) {
            // Successful login (or logout.)
            $this->_setUser($user);
        }
        elseif (is_string($user)) {
            // Login attempt failed.
            $fail_message = $user;
            $auth_args['pass_required'] = true;
            // If no password was submitted, it's not really
            // a failure --- just need to prompt for password...
            if (!isset($auth_args['passwd'])) {
                 //$auth_args['pass_required'] = false;
                 $fail_message = false;
            }
            $olduser->PrintLoginForm($this, $auth_args, $fail_message);
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
        if (ENABLE_USER_NEW) {
            if (! $this->_user )
                $this->_user = new _BogoUser($userid);
            if (! $this->_user )
                $this->_user = new _PassUser($userid);
        }
        $user = $this->_user->AuthCheck(array('userid' => $userid));
        if (isa($user,WikiUserClassname())) {
            $this->_setUser($user); // success!
        }
    }

    // login or logout or restore state
    function _setUser ($user) {
        $this->_user = $user;
        $this->setCookieVar('WIKI_ID', $user->getAuthenticatedId(), 365);
        $this->setSessionVar('wiki_user', $user);
        if ($user->isSignedIn())
            $user->_authhow = 'signin';

        // Save userid to prefs..
        if (!($this->_prefs = $this->_user->getPreferences()))
            $this->_prefs = $this->_user->_prefs;
        $this->_prefs->set('userid',
                           $user->isSignedIn() ? $user->getId() : '');
    }

    function _notAuthorized ($require_level) {
        // Display the authority message in the Wiki's default
        // language, in case it is not english.
        //
        // Note that normally a user will not see such an error once
        // logged in, unless the admin has altered the default
        // disallowed wikiactions. In that case we should probably
        // check the user's language prefs too at this point; this
        // would be a situation which is not really handled with the
        // current code.
        update_locale(DEFAULT_LANGUAGE);

        // User does not have required authority.  Prompt for login.
        $what = $this->getActionDescription($this->getArg('action'));

        if ($require_level == WIKIAUTH_FORBIDDEN) {
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
                    'create'     => _("create pages in this wiki"),
                    'loadfile'   => _("load files into this wiki"),
                    'lock'       => _("lock pages in this wiki"),
                    'remove'     => _("remove pages from this wiki"),
                    'unlock'     => _("unlock pages in this wiki"),
                    'upload'     => _("upload a zip dump to this wiki"),
                    'verify'     => _("verify the current action"),
                    'viewsource' => _("view the source of pages in this wiki"),
                    'xmlrpc'     => _("access this wiki via XML-RPC"),
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
                    'create'     => _("Creating pages"),
                    'loadfile'   => _("Loading files"),
                    'lock'       => _("Locking pages"),
                    'remove'     => _("Removing pages"),
                    'unlock'     => _("Unlocking pages"),
                    'upload'     => _("Uploading zip dumps"),
                    'verify'     => _("Verify the current action"),
                    'viewsource' => _("Viewing the source of pages"),
                    'xmlrpc'     => _("XML-RPC access"),
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
        $auth = $this->requiredAuthorityForAction($action);
        
        /*
         * This is a hook for plugins to require authority
         * for posting to them.
         *
         * IMPORTANT: this is not a secure check, so the plugin
         * may not assume that any POSTs to it are authorized.
         * All this does is cause PhpWiki to prompt for login
         * if the user doesn't have the required authority.
         */
        if ($this->isPost()) {
            $post_auth = $this->getArg('require_authority_for_post');
            if ($post_auth !== false)
                $auth = max($auth, $post_auth);
        }
        return $auth;
    }
        
    function requiredAuthorityForAction ($action) {
        // FIXME: clean up. 
        // Todo: Check individual page permissions instead.
        switch ($action) {
            case 'browse':
            case 'viewsource':
            case 'diff':
            case 'select':
            case 'xmlrpc':
            case 'search':
                return WIKIAUTH_ANON;

            case 'zip':
            case 'ziphtml':
                if (defined('ZIPDUMP_AUTH') && ZIPDUMP_AUTH)
                    return WIKIAUTH_ADMIN;
                return WIKIAUTH_ANON;

            case 'edit':
                if (defined('REQUIRE_SIGNIN_BEFORE_EDIT') && REQUIRE_SIGNIN_BEFORE_EDIT)
                    return WIKIAUTH_BOGO;
                return WIKIAUTH_ANON;
                // return WIKIAUTH_BOGO;

            case 'create':
                $page = $this->getPage();
                $current = $page->getCurrentRevision();
                if ($current->hasDefaultContents())
                    return $this->requiredAuthorityForAction('edit');
                return $this->requiredAuthorityForAction('browse');

            case 'upload':
            case 'dumpserial':
            case 'dumphtml':
            case 'loadfile':
            case 'remove':
            case 'lock':
            case 'unlock':
                return WIKIAUTH_ADMIN;
            default:
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
        elseif ($page = $this->findActionPage($action)) {
            $this->actionpage($page);
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
        elseif ($this->isPost()) {
            /*
             * In general, for security reasons, HTTP_GET_VARS should be ignored
             * on POST requests, but we make an exception here (only for pagename).
             *
             * The justifcation for this hack is the following
             * asymmetry: When POSTing with USE_PATH_INFO set, the
             * pagename can (and should) be communicated through the
             * request URL via PATH_INFO.  When POSTing with
             * USE_PATH_INFO off, this cannot be done --- the only way
             * to communicate the pagename through the URL is via
             * QUERY_ARGS (HTTP_GET_VARS).
             */
            global $HTTP_GET_VARS;
            if (isset($HTTP_GET_VARS['pagename'])) { 
                return $HTTP_GET_VARS['pagename'];
            }
        }

        /*
         * Support for PhpWiki 1.2 style requests.
         */
        $query_string = $this->get('QUERY_STRING');
        if (preg_match('/^[^&=]+$/', $query_string)) {
            return urldecode($query_string);
        }

        return HOME_PAGE;
    }

    function _deduceAction () {
        if (!($action = $this->getArg('action'))) {
            // Detect XML-RPC requests
            if ($this->isPost()
                && $this->get('CONTENT_TYPE') == 'text/xml') {
                global $HTTP_RAW_POST_DATA;
                if (strstr($HTTP_RAW_POST_DATA, '<methodCall>')) {
                    return 'xmlrpc';
                }
            }

            return 'browse';    // Default if no action specified.
        }

        if (method_exists($this, "action_$action"))
            return $action;

        // Allow for, e.g. action=LikePages
        if ($this->isActionPage($action))
            return $action;

        trigger_error("$action: Unknown action", E_USER_NOTICE);
        return 'browse';
    }

    function _deduceUsername() {
        if (!empty($this->args['auth']) and !empty($this->args['auth']['userid']))
            return $this->args['auth']['userid'];
            
        if ($user = $this->getSessionVar('wiki_user')) {
            // users might switch in a session between the two objects
            // restore old auth level?
            if (isa($user,WikiUserClassname()) and !empty($user->_level)) {
                if (empty($this->_user)) {
                    $c = get_class($user);
                    $userid = $user->UserName();
                    if (ENABLE_USER_NEW)
                        $this->_user = new $c($userid);
                    else
                        $this->_user = new $c($this,$userid,$user->_level);
                }
                if ($user = UpgradeUser($this->_user,$user))
                    $this->_user = $user;
                $this->_user->_authhow = 'session';
                
            }
            if (isa($user,WikiUserClassname()))
                return $user->UserName();
        }
        if ($userid = $this->getCookieVar('WIKI_ID')) {
            if (!empty($this->_user))
                $this->_user->authhow = 'cookie';
            return $userid;
        }
        return false;
    }
    
    function _isActionPage ($pagename) {
        $dbi = $this->getDbh();
        $page = $dbi->getPage($pagename);
        $rev = $page->getCurrentRevision();
        // FIXME: more restrictive check for sane plugin?
        if (strstr($rev->getPackedContent(), '<?plugin'))
            return true;
        if (!$rev->hasDefaultContents())
            trigger_error("$pagename: Does not appear to be an 'action page'", E_USER_NOTICE);
        return false;
    }

    function findActionPage ($action) {
        static $cache;

        // check for translated version, as per users preferred language
        // (or system default in case it is not en)
        $translation = gettext($action);

        if (isset($cache) and isset($cache[$translation]))
            return $cache[$translation];

        // check for cached translated version
        if ($this->_isActionPage($translation))
            return $cache[$action] = $translation;

        // Allow for, e.g. action=LikePages
        global $WikiNameRegexp;
        if (!preg_match("/$WikiNameRegexp\\Z/A", $action))
            return $cache[$action] = false;

        // check for translated version (default language)
        global $LANG;
        if ($LANG != DEFAULT_LANGUAGE and $LANG != "en") {
            $save_lang = $LANG;
            //trigger_error("DEBUG: findActionPage() ". DEFAULT_LANGUAGE." calling update_locale()...");
            update_locale(DEFAULT_LANGUAGE);
            $default = gettext($action);
            //trigger_error("DEBUG: findActionPage() ". $save_lang." restoring save_lang, calling update_locale()...");
            update_locale($save_lang);
            if ($this->_isActionPage($default))
                return $cache[$action] = $default;
        }
        else {
            $default = $translation;
        }
        
        // check for english version
        if ($action != $translation and $action != $default) {
            if ($this->_isActionPage($action))
                return $cache[$action] = $action;
        }

        trigger_error("$action: Cannot find action page", E_USER_NOTICE);
        return $cache[$action] = false;
    }
    
    function isActionPage ($pagename) {
        return $this->findActionPage($pagename);
    }

    function action_browse () {
        $this->buffer_output();
        include_once("lib/display.php");
        displayPage($this);
    }

    function action_verify () {
        $this->action_browse();
    }

    function actionpage ($action) {
        $this->buffer_output();
        include_once("lib/display.php");
        actionPage($this, $action);
    }

    function action_diff () {
        $this->buffer_output();
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
        $this->buffer_output();
        include "lib/editpage.php";
        $e = new PageEditor ($this);
        $e->editPage();
    }

    function action_create () {
        $this->action_edit();
    }
    
    function action_viewsource () {
        $this->buffer_output();
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

    function action_xmlrpc () {
        include_once("lib/XmlRpcServer.php");
        $xmlrpc = new XmlRpcServer($this);
        $xmlrpc->service();
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
    return WikiRequest::requiredAuthorityForAction($action) < WIKIAUTH_ADMIN;
}

function validateSessionPath() {
    // Try to defer any session.save_path PHP errors before any html
    // is output, which causes some versions of IE to display a blank
    // page (due to its strict mode while parsing a page?).
    if (! is_writeable(ini_get('session.save_path'))) {
        $tmpdir = '/tmp';
        trigger_error
            (sprintf(_("%s is not writable."),
                     _("The session.save_path directory"))
             . "\n"
             . sprintf(_("Please ensure that %s is writable, or redefine %s in index.php."),
                       sprintf(_("the directory '%s'"),
                               ini_get('session.save_path')),
                       'session.save_path')
             . "\n"
             . sprintf(_("Attempting to use the directory '%s' instead."),
                       $tmpdir)
             , E_USER_NOTICE);
        if (! is_writeable($tmpdir)) {
            trigger_error
                (sprintf(_("%s is not writable."), $tmpdir)
                 . "\n"
                 . _("Users will not be able to sign in.")
                 , E_USER_NOTICE);
        }
        else
            ini_set('session.save_path', $tmpdir);
    }
}

function main () {
    if (!USE_DB_SESSION)
      validateSessionPath();

    global $request;

    $request = new WikiRequest();

    /*
     * Allow for disabling of markup cache.
     * (Mostly for debugging ... hopefully.)
     *
     * See also <?plugin WikiAdminUtils action=purge-cache ?>
     */
    if (!defined('WIKIDB_NOCACHE_MARKUP') and $request->getArg('nocache'))
        define('WIKIDB_NOCACHE_MARKUP', $request->getArg('nocache'));
    
    // Initialize with system defaults in case user not logged in.
    // Should this go into constructor?
    $request->initializeTheme();

    $request->updateAuthAndPrefs();
    // initialize again with user's prefs
    $request->initializeTheme();
    $request->initializeLang();
    
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
    
if(defined('WIKI_XMLRPC')) return;

    $validators = array('wikiname' => WIKI_NAME,
                        'args'     => hash($request->getArgs()),
                        'prefs'    => hash($request->getPrefs()));
    if (CACHE_CONTROL == 'STRICT') {
        $dbi = $request->getDbh();
        $timestamp = $dbi->getTimestamp();
        $validators['mtime'] = $timestamp;
        $validators['%mtime'] = (int)$timestamp;
    }
    // FIXME: we should try to generate strong validators when possible,
    // but for now, our validator is weak, since equal validators do not
    // indicate byte-level equality of content.  (Due to DEBUG timing output, etc...)
    //
    // (If DEBUG if off, this may be a strong validator, but I'm going
    // to go the paranoid route here pending further study and testing.)
    //
    $validators['%weak'] = true;
    
    $request->setValidators($validators);
   
    $request->handleAction();

if (defined('DEBUG') and DEBUG>1) phpinfo(INFO_VARIABLES);
    $request->finish();
}


main();


// $Log: not supported by cvs2svn $
// Revision 1.107  2004/01/27 23:23:39  rurban
// renamed ->Username => _userid for consistency
// renamed mayCheckPassword => mayCheckPass
// fixed recursion problem in WikiUserNew
// fixed bogo login (but not quite 100% ready yet, password storage)
//
// Revision 1.106  2004/01/26 09:17:49  rurban
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
// Revision 1.105  2004/01/25 03:57:15  rurban
// WikiUserNew support (temp. ENABLE_USER_NEW constant)
//
// Revision 1.104  2003/12/26 06:41:16  carstenklapp
// Bugfix: Try to defer OS errors about session.save_path and ACCESS_LOG,
// so they don't prevent IE from partially (or not at all) rendering the
// page. This should help a little for the IE user who encounters trouble
// when setting up a new PhpWiki for the first time.
//
// Revision 1.103  2003/12/02 00:10:00  carstenklapp
// Bugfix: Ongoing work to untangle UserPreferences/WikiUser/request code
// mess: UserPreferences should take effect immediately now upon signing
// in.
//
// Revision 1.102  2003/11/25 22:55:32  carstenklapp
// Localization bugfix: For wikis where English is not the default system
// language, make sure that the authority error message (i.e. "You must
// sign in to edit pages in this wiki" etc.) is displayed in the wiki's
// default language. Previously it would always display in English.
// (Added call to update_locale() before displaying any messages prior to
// the login prompt.)
//
// Revision 1.101  2003/11/25 21:49:44  carstenklapp
// Bugfix: For a non-english wiki or when the user's preference is not
// english, the wiki would always use the english ActionPage first if it
// was present rather than the appropriate localised variant. (PhpWikis
// running only in english or Wikis running ONLY without any english
// ActionPages would not notice this bug, only when both english and
// localised ActionPages were in the DB.) Now we check for the localised
// variant first.
//
// Revision 1.100  2003/11/18 16:54:18  carstenklapp
// Reformatting only: Tabs to spaces, added rcs log.
//


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
