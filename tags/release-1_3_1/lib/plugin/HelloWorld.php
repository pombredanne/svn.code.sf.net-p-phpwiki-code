<?php // -*-php-*-
rcs_id('$Id: HelloWorld.php,v 1.2 2001-09-19 03:24:36 wainstead Exp $');
/**
 * A simple demonstration WikiPlugin.
 */
class WikiPlugin_HelloWorld
extends WikiPlugin
{
    function getDefaultArguments() {
        return array('salutation'	=> 'Hello,',
                     'name'			=> 'World');
    }

    function run($argstr) {
        extract($this->parseArgs($argstr));
        
        return sprintf("<tt>%s %s</tt>", $salutation, $name);
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
