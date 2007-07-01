<?php
rcs_id('$Id: themeinfo.php,v 1.6 2007-07-01 09:36:10 rurban Exp $');
/**
 * tiny actionbar, only Edit (if signed in) and Info => PageInfo,
 *   all other Actionbars buttons in info.tmpl
 * old-style Sign In button
 * navbar also shorter labels and without buttons, just links
 */

require_once('lib/Theme.php');

class Theme_smaller extends Theme {

    function makeActionButton ($action, $label = false, $page_or_rev = false) {
        extract($this->_get_name_and_rev($page_or_rev));

        if (is_array($action)) {
            $attr = $action;
            $action = isset($attr['action']) ? $attr['action'] : 'browse';
        }
        else
            $attr['action'] = $action;

        $class = is_safe_action($action) ? 'named-wiki' : 'wikiadmin';
        if (!$label)
            $label = $this->_labelForAction($action);

        if ($version)
            $attr['version'] = $version;

        if ($action == 'browse')
            unset($attr['action']);

        return $this->makeButton($label, WikiURL($pagename, $attr), $class);
    }
    
}

$WikiTheme = new Theme_smaller('smaller');

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
