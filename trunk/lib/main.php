<?php
rcs_id('$Id: main.php,v 1.24 2002-01-17 20:35:44 dairiki Exp $');

include "lib/config.php";
include "lib/stdlib.php";
require_once('lib/Request.php');
require_once("lib/WikiUser.php");
require_once('lib/WikiDB.php');

if (defined('THEME')) {
    include("themes/" . THEME . "/themeinfo.php");
}
if (empty($Theme)) {
    include("themes/default/themeinfo.php");
}
assert(!empty($Theme));


function deduce_pagename ($request) {
    if ($request->getArg('pagename'))
        return $request->getArg('pagename');

    if (USE_PATH_INFO) {
        $pathinfo = $request->get('PATH_INFO');
        if (ereg('^' . PATH_INFO_PREFIX . '(..*)$', $pathinfo, $m))
            return $m[1];
    }

    $query_string = $request->get('QUERY_STRING');
    if (preg_match('/^[^&=]+$/', $query_string))
        return urldecode($query_string);
    
    return _("HomePage");
}

function is_safe_action ($action) {
    if (! ZIPDUMP_AUTH and ($action == 'zip' || $action == 'xmldump'))
        return true;
    return in_array ( $action, array('browse', 'info',
                                     'diff',   'search',
                                     'edit',   'save',
                                     'login',  'logout',
                                     'setprefs') );
}

function get_auth_mode ($action) {
    switch ($action) {
    case 'logout':
        return  'LOGOUT';
    case 'login':
        return 'LOGIN';
    default:
        if (is_safe_action($action))
            return 'ANON_OK';
        else
            return 'REQUIRE_AUTH';
    }
}

function main ($request) {
    
    
    if (USE_PATH_INFO && ! $request->get('PATH_INFO')
        && ! preg_match(',/$,', $request->get('REDIRECT_URL'))) {
        $request->redirect(SERVER_URL
                           . preg_replace('/(\?|$)/', '/\1',
                                          $request->get('REQUEST_URI'),
                                          1));
        exit;
    }

    $request->setArg('pagename', deduce_pagename($request));
    global $pagename;               // FIXME: can we make this non-global?
    $pagename = $request->getArg('pagename');
    
    $action = $request->getArg('action');
    if (!$action) {
        $action = 'browse';
        $request->setArg('action', $action);
    }
    
    global $user;               // FIXME: can we make this non-global?
    $user = new WikiUser($request, get_auth_mode($action));
    //FIXME:
    //if ($user->is_authenticated())
    //  $LogEntry->user = $user->id();
    
    // All requests require the database
    global $dbi;                // FIXME: can we keep this non-global?
    $dbi = WikiDB::open($GLOBALS['DBParams']);
    
    if ( $action == 'browse' && $request->getArg('pagename') == _("HomePage") ) {
        // if there is no HomePage, create a basic set of Wiki pages
        if ( ! $dbi->isWikiPage(_("HomePage")) ) {
            include_once("lib/loadsave.php");
            SetupWiki($dbi);
            ExitWiki();
        }
    }

    // FIXME: I think this is redundant.
    if (!is_safe_action($action))
        $user->must_be_admin($action);
    
    if (isset($DisabledActions) && in_array($action, $DisabledActions))
        ExitWiki(sprintf(_("Action %s is disabled in this wiki."), $action));
   
    // Enable the output of most of the warning messages.
    // The warnings will screw up zip files and setpref though.
    global $ErrorManager;
    if ($action != 'zip' && $action != 'setprefs') {
        $ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE);
    }
    
    
    switch ($action) {
    case 'edit':
        $request->compress_output();
        include "lib/editpage.php";
        editPage($dbi, $request);
        break;

    case 'search':
        // This is obsolete: reformulate URL and redirect.
        // FIXME: this whole section should probably be deleted.
        if ($request->getArg('searchtype') == 'full') {
            $search_page = _("FullTextSearch");
        }
        else {
            $search_page = _("TitleSearch");
        }
        $request->redirect(WikiURL($search_page,
                                   array('s' => $request->getArg('searchterm')),
                                   'absolute_url'));
        break;
        
    case 'save':
        $request->compress_output();
        include "lib/savepage.php";
        savePage($dbi, $request);
        break;
    case 'diff':
        $request->compress_output();
        include_once "lib/diff.php";
        showDiff($dbi, $request);
        break;
      
    case 'zip':
        include_once("lib/loadsave.php");
        MakeWikiZip($dbi, $request);
        // I don't think it hurts to add cruft at the end of the zip file.
        echo "\n========================================================\n";
        echo "PhpWiki " . PHPWIKI_VERSION . " source:\n$GLOBALS[RCS_IDS]\n";
        break;

    /* Not yet implemented:    
    case 'xmldump':
        // FIXME:
        $limit = 1;
        if ($request->getArg('include') == 'all')
            $limit = 0;
        require_once("lib/libxml.php");
        $xmlwriter = new WikiXmlWriter;
        $xmlwriter->begin();
        $xmlwriter->writeComment("PhpWiki " . PHPWIKI_VERSION
                                 . " source:\n$RCS_IDS\n");
        $xmlwriter->writeDatabase($dbi, $limit);
        $xmlwriter->end();
        break;
    */
        
    case 'upload':
        include_once("lib/loadsave.php");
        LoadPostFile($dbi, $request);
        break;
   
    case 'dumpserial':
        include_once("lib/loadsave.php");
        DumpToDir($dbi, $request);
        break;

    case 'loadfile':
        include_once("lib/loadsave.php");
        LoadFileOrDir($dbi, $request);
        break;

    case 'remove':
        include 'lib/removepage.php';
        break;
    
    case 'lock':
    case 'unlock':
        $user->must_be_admin(_("lock or unlock pages"));
        $page = $dbi->getPage($request->getArg('pagename'));
        $page->set('locked', $action == 'lock');

        $request->compress_output();
        include_once("lib/display.php");
        displayPage($dbi, $request);
        break;

    case 'setprefs':
        $prefs = $user->getPreferences();
        $edit_area_width = $request->getArg('edit_area_width');
        $edit_area_height = $request->getArg('edit_area_height');
        if ($edit_area_width)
            $prefs['edit_area.width'] = $edit_area_width;
        if ($edit_area_height)
            $prefs['edit_area.height'] = $edit_area_height;
        $user->setPreferences($prefs);
        $ErrorManager->setPostponedErrorMask(E_ALL & ~E_NOTICE);

        $request->compress_output();
        include_once("lib/display.php");
        displayPage($dbi, $request);
        break;
   
    case 'browse':
    case 'login':
    case 'logout':
        $request->compress_output();
        include_once("lib/display.php");
        displayPage($dbi, $request);
        break;

    default:
        echo QElement('p', sprintf(_("Bad action: '%s'"), urlencode($action)));
        break;
    }
    ExitWiki();
}

$request = new Request;
main($request);


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
