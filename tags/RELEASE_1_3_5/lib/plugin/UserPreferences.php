<?php // -*-php-*-
rcs_id('$Id: UserPreferences.php,v 1.11 2003-09-19 22:01:19 carstenklapp Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

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
 * This must be used in the page "UserPreferences" or in a subpage of a
 * user called like HomePage/Preferences.
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
                            "\$Revision: 1.11 $");
    }

    function getDefaultArguments() {
        global $request;
        $pagename = $request->getArg('pagename');
        $user = $request->getUser();
        // for a UserPage/Prefences plugin default to this userid
        if (isSubPage($pagename)) {
            $pages  = explode(SUBPAGE_SEPARATOR, $pagename);
            $userid = $pages[0];
        } else {
            // take current user
            $userid = $user->_userid;
        }
        return
            array('userid'  => $userid, // current or the one from the SubPage
                  'changePass'    => $user->mayChangePassword(),
                  'appearance'    => true,
                  'email'         => true,
                  'notifyPages'   => true,
                  'editAreaSize'  => true,
                  'timeOffset'    => true,
                  'theme'         => THEME,
                  'lang'          => DEFAULT_LANGUAGE,
                  'relativeDates' => true
                  );
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        $user = &$request->getUser();
        if (! $request->isActionPage($request->getArg('pagename'))) {
            $no_args = $this->getDefaultArguments();
            foreach ($no_args as $key => $value) {
                $no_args[$value] = false;
            }
            $no_args['errmsg'] = HTML(HTML::h2(_("Error: The page with the UserPreferences plugin must be valid WikiWord or a Preferences subpage of the users HomePage. Sorry, UserPreferences cannot be saved."),HTML::hr()));
            $no_args['isForm'] = false;
            return Template('userprefs', $no_args);
        }
        if (((defined('ALLOW_BOGO_LOGIN') && ALLOW_BOGO_LOGIN && $user->isSignedIn())
             || $user->isAuthenticated())
            && $args['userid'] == $user->_userid) {
            if ($request->isPost()) {
                if ($request->_prefs) {
                    $pref = $request->_prefs;
                } else { // hmm. already handled somewhere else...
                    $pref = new UserPreferences($request->getArg('pref'));
                }
                // Fixme: How to update the Theme? Correct update?
                $num = $request->_user->SetPreferences($pref);
                if (!$num) {
                    $errmsg = _("No changes.");
                } else {
                    $errmsg = fmt("%d UserPreferences fields successfully updated.", $num);
                }
                $args['errmsg'] = HTML(HTML::h2($errmsg), HTML::hr());
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
        } else {
            return $user->PrintLoginForm ($request, $args, false, false);
        }
    }
};

// $Log: not supported by cvs2svn $
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
