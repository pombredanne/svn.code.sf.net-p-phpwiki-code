<?php // -*-php-*-
rcs_id('$Id: Toolbar.php,v 1.1 2002-01-07 04:35:31 carstenklapp Exp $');
/**
 * Usage:
 *
 * <?plugin Toolbar label="My favorites pages are %s." sep=, go="SandBox|SandBox,stuff|TestPage" ?>
 *
 * <?plugin Toolbar label="Show changes for: %s" sep=| go="1 day|phpwiki:RecentChanges?days=1,3 days|phpwiki:RecentChanges?days=3" ?>
 *
 * <?plugin Toolbar label="Try %s." sep=" or " go="this|SandBox,that|TestPage"?>
 *
 */

require_once('lib/transform.php');

class WikiPlugin_Toolbar
extends WikiPlugin
{
    function getName() {
        return _("Toolbar");
    }
    
    function getDescription() {
        return sprintf(_("Toolbar for %s"),'[pagename]');
    }
    
    function getDefaultArguments() {
        return array('sep'		=> ',',
                     'label'		=> false,
                     'go'		=> false,
                     'style'		=> 'text'
                     // TODO: new 'image' style for use with themes
                     // which have graphic buttons
                     );
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($go)) {
            $html="";
            if (!empty($label)) {
                // cleanup and display label
                $content = str_replace('%s', '', _($label));
                $html = do_transform($content, 'LinkTransform');
            } else {
                // FIXME: WikiPlugin has no way to report when
                // required args are missing?
                $error_text = "WikiPlugin_" .$this->getName() .": ";
                $error_text .= sprintf(_("A required argument '%s' is missing."),
                                       'go');
                $html = $error_text;
            }
            return $html;
        }

        switch ($style) {
        case "text":
            // add spaces
            switch ($sep) {
            case '|':
                $sep = " | ";
                break;
            case ',':
                $sep = ", ";
                break;
            //default:
                //$sep = $sep ." ";
            }

            $links = "[" .str_replace(",", "]" .$sep ."[", $go) ."]";

            $content = sprintf(_($label),$links);
            // TODO: (maybe) localise individual item labels (the
            // parts of the $go text before the "|"s)

            $html = do_transform($content, 'LinkTransform');
            return $html;
            break;
        case "img":
            $style = 'image';
        case "image":
            $error_text = "WikiPlugin_" .$this->getName() .": ";
            $error_text .= 'style=image: ' ._("Not Implemented");
            $html = $error_text;
            return $html;
            break;
        }
    }
};
        
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
