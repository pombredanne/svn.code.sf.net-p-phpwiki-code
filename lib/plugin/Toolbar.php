<?php // -*-php-*-
rcs_id('$Id: Toolbar.php,v 1.3 2002-01-07 09:04:41 carstenklapp Exp $');
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




// (This is all in a state of flux, so don't count on any of this being
// the same tomorrow...)




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
        return array('sep'	=> ',',
                     'label'	=> false,
                     'go'	=> false,
                     'style'	=> 'text',
                     'name'	=> ''
                     // TODO: new 'image' style for use with themes
                     // which have graphic buttons
                     );
    }

    function mkimg($key, $val, &$html, &$ToolbarURLs) {
        $html .= "<td><a href=\"". $ToolbarURLs[$key]."\"><img alt=\"$key\" src=\"$val\" border=\"0\"></a></td>";
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
        global $theme;
//$name="RecentChanges";
        if ($theme == "MacOSX" && $name=="RecentChanges") {
            global $ToolbarImages;
/*
                $ToolbarImages = array(
                'RecentChanges' => array(
                '1 day'		=> "themes/$theme/locale/en/toolbars/RecentChanges/1day.png",
                '3 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/3days.png",
                '7 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/7days.png",
                '30 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/30days.png",
                '90 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/90days.png",
                '...'		=> "themes/$theme/locale/en/toolbars/RecentChanges/alltime.png")
                );
*/                
                $ToolbarURLs = array(
                //'RecentChanges' => array(
                '1 day'		=> "RecentChanges?days=1",
                '3 days'	=> "RecentChanges?days=3",
                '7 days'	=> "RecentChanges?days=7",
                '30 days'	=> "RecentChanges?days=30",
                '90 days'	=> "RecentChanges?days=90",
                '...'		=> "RecentChanges?days=-1"
                //)
                );



            //while(list($key, $val) = each($ToolbarImages)) {
            //    echo "$key => $val<br>";
            //}

//            if (in_array ($name, $ToolbarImages)) {
                $rcimages = $ToolbarImages[$name];
                $html = "<table summary=\"RecentChanges\" border=0 cellspacing=0 cellpadding=0><tr valign=\"middle\"><td>Show changes for: ";
                //array_walk($rcimages, 'makeimg'); //doesn't work???
                while(list($key, $val) = each($rcimages)) {
                    $this->mkimg($key, $val, $html, $ToolbarURLs);
                }
                return "</tr>".$html;
                //reset($rcimages);
//            }

//            if (array_key_exists("first", $search_array)) {
//                echo "The 'first' element is in the array";
//            }

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
