<?php // -*-php-*-
rcs_id('$Id: HelloWorld.php,v 1.11 2002-12-30 23:49:35 carstenklapp Exp $');
/**
 * A simple demonstration WikiPlugin.
 *
 * Usage:
 * <?plugin HelloWorld?>
 * <?plugin HelloWorld salutation="Greetings, " name=Wikimeister ?>
 * <?plugin HelloWorld salutation=Hi ?>
 * <?plugin HelloWorld name=WabiSabi ?>
 */

// Constants are defined before the class.
if (!defined('THE_END'))
    define('THE_END', "!");

class WikiPlugin_HelloWorld
extends WikiPlugin
{
    // Five required functions in a WikiPlugin.

    function getName () {
        return _("HelloWorld");
    }

    function getDescription () {
        return _("Simple Sample Plugin");

    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.11 $");
    }

    // Establish default values for each of this plugin's arguments.
    function getDefaultArguments() {
        return array('salutation' => "Hello,",
                     'name'	  => "World");
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        // Any text that is returned will not be further transformed,
        // so use html where necessary.
        $html = HTML::tt(fmt('%s %s', $salutation, WikiLink($name, 'auto')),
                         THE_END);
        return $html;
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
