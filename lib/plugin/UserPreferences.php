<?php // -*-php-*-
rcs_id('$Id: UserPreferences.php,v 1.7 2003-02-22 20:49:56 dairiki Exp $');
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

    function getDefaultArguments() {
        global $request;
        $pagename = $request->getArg('pagename');
        $user = $request->getUser();
        // for a UserPage/Prefences plugin default to this userid
        if (isSubPage($pagename)) {
            $pages  = explode(SUBPAGE_SEPARATOR,$pagename);
            $userid = $pages[0];
        } else {
            // take current user
            $userid = $user->_userid;
        }
        return array('userid'		=> $userid, // current or the one from the SubPage
                     'changePass'       => $user->mayChangePassword(),
                     'appearance'     	=> true,
                     'email'       	=> true,
                     'notifyPages'     	=> true,
                     'editAreaSize'     => true,
                     'timeOffset'       => true,
                     'relativeDates'    => true
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
        if ($user->isAuthenticated() and $args['userid'] == $user->_userid) {
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
                $args['errmsg'] = HTML(HTML::h2($errmsg),HTML::hr());
            }
            $available_themes = array(); 
            $dir_root = PHPWIKI_DIR . '/themes/'; 
            $dir = dir($dir_root);
            if ($dir) {
                while($entry = $dir->read()) {
                    if (is_dir($dir_root.$entry) and (substr($entry,0,1) != '.') and 
                        $entry!='CVS') {
                        array_push($available_themes,$entry);
                    }
                }
                $dir->close();
            }
            $args['available_themes'] = $available_themes;

            $available_languages = array('en');
            $dir_root = PHPWIKI_DIR . '/locale/'; 
            $dir = dir($dir_root);
            if ($dir) {
                while($entry = $dir->read()) {
                    if (is_dir($dir_root.$entry) and (substr($entry,0,1) != '.') and 
                        $entry != 'po' and $entry != 'CVS') {
                        array_push($available_languages,$entry);
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

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
