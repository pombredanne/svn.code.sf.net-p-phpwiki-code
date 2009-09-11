<?php // -*-php-*-
rcs_id('$Id$');

require_once("lib/plugin/RecentChanges.php");

class WikiPlugin_RecentEdits
extends WikiPlugin_RecentChanges
{
    function getName () {
        return _("RecentEdits");
    }

    function getDescription () {
        return _("List all recent edits in this wiki.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision$");
    }

    function getDefaultArguments() {
        $args = parent::getDefaultArguments();
        $args['show_minor'] = true;
        $args['show_all'] = true;
        return $args;
    }

    // box is used to display a fixed-width, narrow version with common header.
    // just a numbered list of limit pagenames, without date.
    function box($args = false, $request = false, $basepage = false) {
        if (!$request) $request =& $GLOBALS['request'];
        if (!isset($args['limit'])) $args['limit'] = 15;
        $args['format'] = 'box';
        $args['show_minor'] = true;
        $args['show_major'] = true;
        $args['show_deleted'] = false;
        $args['show_all'] = true;
        $args['days'] = 90;
        return $this->makeBox(WikiLink(_("RecentEdits"),'',_("Recent Edits")),
                              $this->format($this->getChanges($request->_dbi, $args), $args));
    }
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
