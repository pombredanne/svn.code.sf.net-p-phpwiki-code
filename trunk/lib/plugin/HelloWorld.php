<?php // -*-php-*-
rcs_id('$Id: HelloWorld.php,v 1.1 2001-09-18 19:19:05 dairiki Exp $');
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
        
?>
