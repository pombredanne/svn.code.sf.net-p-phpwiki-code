<?php // -*-php-*-
rcs_id('$Id: Comment.php,v 1.1 2003-01-28 17:57:15 carstenklapp Exp $');
/**
 * A WikiPlugin for putting comments in WikiPages
 *
 * Usage:
 * <?plugin Comment
 *
 * !!! My Secret Text
 *
 * This is some WikiText that won't show up on the page.
 *
 * ?>
 */

class WikiPlugin_Comment
extends WikiPlugin
{
    // Five required functions in a WikiPlugin.

    function getName() {
        return _("Comment");
    }

    function getDescription() {
        return _("Embed hidden comments in WikiPages.");

    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    // No arguments here.
    function getDefaultArguments() {
        return array();
    }

    function run($dbi, $argstr, $request) {
    }

    // function handle_plugin_args_cruft(&$argstr, &$args) {
    // }

};

// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
