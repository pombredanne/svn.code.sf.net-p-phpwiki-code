<?php // -*-php-*-
rcs_id('$Id: _PreferencesInfo.php,v 1.1 2002-08-23 21:54:30 rurban Exp $');
/**
 * Plugin to display the current preferences without auth check.
 */
class WikiPlugin__PreferencesInfo
extends WikiPlugin
{
    function getName () {
        return _("PreferencesInfo");
    }

    function getDescription () {
        return sprintf(_("Get preferences information for current user %s."), '[userid]');
    }

    function getDefaultArguments() {
        return array('page' => '[pagename]',
                     'userid' => '[userid]');
    }
   
    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        // $user = &$request->getUser();
        return Template('userprefs', $args);
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
