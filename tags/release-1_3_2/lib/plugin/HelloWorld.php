<?php // -*-php-*-
rcs_id('$Id: HelloWorld.php,v 1.4 2001-12-16 18:33:25 dairiki Exp $');
/**
 * A simple demonstration WikiPlugin.
 */
class WikiPlugin_HelloWorld
extends WikiPlugin
{
    function getName () {
        return _("HelloWorld");
    }

    function getDescription () {
        return _("Simple Sample Plugin");
    }
    
    function getDefaultArguments() {
        return array('salutation'	=> "Hello,",
                     'name'		=> "World");
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        
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
