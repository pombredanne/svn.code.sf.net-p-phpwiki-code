<?php // -*-php-*-
rcs_id('$Id: UserPreferences.php,v 1.18 2004-02-24 15:20:06 rurban Exp $');
/**
 Copyright 2001, 2002, 2003, 2004 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * Plugin to allow any user to adjust his own preferences.
 * This must be used in the page "UserPreferences".
 * Prefs are stored in metadata within the user's home page or in a cookie.
 */
class WikiPlugin_UserPreferences
extends WikiPlugin
{
    var $bool_args;

    function getName () {
        return _("UserPreferences");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.18 $");
    }

    function getDefaultArguments() {
        global $request;
        $pagename = $request->getArg('pagename');
        $user = $request->getUser();
        //we need a hash of pref => default_value
        $pref = $user->getPreferences();
        $prefs = array();
        foreach ($pref->_prefs as $name => $obj) {
            $prefs[$name] = $obj->default_value;
        }
        return $prefs;
    }

    function run($dbi, $argstr, &$request, $basepage) {
        $args = $this->getArgs($argstr, $request);
        $user = &$request->getUser();
        if (! $request->isActionPage($request->getArg('pagename'))) {
            $no_args = $this->getDefaultArguments();
// ?
//            foreach ($no_args as $key => $value) {
//                $no_args[$value] = false;
//            }
            $no_args['errmsg'] = HTML(HTML::h2(_("Error: The user HomePage must be a valid WikiWord. Sorry, UserPreferences cannot be saved."),HTML::hr()));
            $no_args['isForm'] = false;
            return Template('userprefs', $no_args);
        }
        $userid = $user->UserName();
        if (((defined('ALLOW_BOGO_LOGIN') && ALLOW_BOGO_LOGIN && $user->isSignedIn())
             || $user->isAuthenticated())
            && !empty($userid)) {
            $pref = $user->getPreferences();
            //trigger_error("DEBUG: reading prefs from getPreferences".print_r($pref));
 
            if ($request->isPost()) {
                if ($rp = $request->getArg('pref')) {
                    // replace only changed prefs in $pref with those from request
                    if (!empty($rp['passwd']) and ($rp['passwd2'] != $rp['passwd'])) {
                        $errmsg = _("Wrong password. Try again.");
                    } else {
                        //trigger_error("DEBUG: reading prefs from request".print_r($rp));
                        //trigger_error("DEBUG: writing prefs with setPreferences".print_r($pref));
                        $num = $user->setPreferences($rp);
                        if (!$num) {
                            $errmsg = _("No changes.");
                        }
                        else {
                            $pref = $user->_prefs;	
                            $errmsg = fmt("%d UserPreferences fields successfully updated.", $num);
                        }
                    }
                    $args['errmsg'] = HTML(HTML::h2($errmsg), HTML::hr());
                }
            }
            $available_themes = array(); 
            $dir_root = 'themes/';
            if (defined('PHPWIKI_DIR'))
                $dir_root = PHPWIKI_DIR . "/$dir_root";
            $dir = dir($dir_root);
            if ($dir) {
                while($entry = $dir->read()) {
                    if (is_dir($dir_root.$entry)
                        && (substr($entry,0,1) != '.')
                        && $entry != 'CVS') {
                        array_push($available_themes, $entry);
                    }
                }
                $dir->close();
            }
            $args['available_themes'] = $available_themes;

            $available_languages = array('en');
            $dir_root = 'locale/';
            if (defined('PHPWIKI_DIR'))
                $dir_root = PHPWIKI_DIR . "/$dir_root";
            $dir = dir($dir_root);
            if ($dir) {
                while($entry = $dir->read()) {
                    if (is_dir($dir_root.$entry)
                        && (substr($entry,0,1) != '.')
                        && $entry != 'po'
                        && $entry != 'CVS') {
                        array_push($available_languages, $entry);
                    }
                }
                $dir->close();
            }
            $args['available_languages'] = $available_languages;

            return Template('userprefs', $args);
        }
        else {
            // wrong or unauthenticated user
            return $user->PrintLoginForm ($request, $args, false, false);
        }
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.17  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.16  2004/02/15 21:34:37  rurban
// PageList enhanced and improved.
// fixed new WikiAdmin... plugins
// editpage, Theme with exp. htmlarea framework
//   (htmlarea yet committed, this is really questionable)
// WikiUser... code with better session handling for prefs
// enhanced UserPreferences (again)
// RecentChanges for show_deleted: how should pages be deleted then?
//
// Revision 1.15  2004/01/27 22:37:50  rurban
// fixed default args: no objects
//
// Revision 1.14  2004/01/26 09:18:00  rurban
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
// Revision 1.13  2003/12/04 20:27:00  carstenklapp
// Use the API.
//
// Revision 1.12  2003/12/01 22:21:33  carstenklapp
// Bugfix: UserPreferences are no longer clobbered when signing in after
// the previous session has ended (i.e. user closed browser then signed
// in again). This is still a bit of a mess, and the preferences do not
// take effect until the next page browse/link has been clicked.
//
// Revision 1.11  2003/09/19 22:01:19  carstenklapp
// BOGO users allowed preferences too when ALLOW_BOGO_LOGIN == true.
//
// Revision 1.10  2003/09/13 21:57:26  carstenklapp
// Reformatting only.
//
// Revision 1.9  2003/09/13 21:53:41  carstenklapp
// Added lang and theme arguments, getVersion(), copyright and cvs log.
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
