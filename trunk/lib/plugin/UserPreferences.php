<?php // -*-php-*-
rcs_id('$Id: UserPreferences.php,v 1.1 2002-01-23 19:20:05 dairiki Exp $');
/**
 * Plugin to allow user to adjust his preferences.
 */
class WikiPlugin_UserPreferences
extends WikiPlugin
{
    function getName () {
        return _("UserPreferences");
    }

    /*
    function getDefaultArguments() {
        return array();
    }
    */
    
    function run($dbi, $argstr, $request) {
        return new WikiTemplate('userprefs');
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
