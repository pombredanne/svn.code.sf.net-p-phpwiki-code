<?php // -*-php-*-
rcs_id('$Id: Toolbar.php,v 1.10 2002-01-10 20:59:09 carstenklapp Exp $');
/**
 * Usage:
 *
 * <?plugin Toolbar name=RecentEdits days=1,2,3,7,30,-1 label="Show edits for: %s" sep=| ?>
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
                     'label'	=> '',
                     'go'	=> '',
                     'style'	=> 'text',
                     'name'	=> '',
                     'days'	=> ''
                     // TODO: new 'image' style for use with themes
                     // which have graphic buttons
                     );
    }

    function mklinks($text, $action) {
        return do_transform("[$text|$action]", 'LinkTransform');
    }

    function mkimglinks($text, $action, $imgurl) {
        // ImageLinks would be helpful here
        if ($imgurl)
            return "<a href=\"". VIRTUAL_PATH ."/" .$action ."\"><img alt=\"" .$text ."\" src=\"" .($imgurl) ."\" border=\"0\"></a>";
        else
            return "&nbsp;". $this->mklinks($text, 'phpwiki:' .$action) ."&nbsp;";
    }

    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($go) && empty($days)) {
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

        if ($style == 'img')
            $style = 'image';

        global $theme;
        if ($theme == "MacOSX" && ($name == _("RecentChanges") || $name == _("RecentEdits"))) {
            $style = "image";
        }

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

        if (empty($label)) {
            switch ($name) {
            case _("RecentChanges") :
                $label = _("Show changes for: %s");
            case _("RecentEdits") :
                $label = _("Show minor edits for: %s");
            }
        }

        if (($name == _("RecentChanges") || $name == _("RecentEdits")) && $days) {

            $days = explode(",", $days);

            $day1    = _("1 day");
            $ndays   = _("%s days");
            $alldays = "...";

            $links = array();
            if ($style == "image") {
                global $ToolbarImages;
                $rcimages = $ToolbarImages['RecentChanges'];
            }
            foreach ($days as $val) {

                if ($val == 1)
                    $text = $day1;
                elseif ($val == -1)
                    $text = $alldays;
                else
                    $text = sprintf($ndays, $val);

                if ($style == "image") {
                    $action  = $name ."?days=" .$val;
                    $imgurl  = $rcimages[$text];
                    $links[] = $this->mkimglinks($text, $action, $imgurl);
                } else {
                    $action  = 'phpwiki:' .$name ."?days=" .$val;
                    $links[] = $this->mklinks($text, $action);
                }
            }
            // final assembly of label and the links
            if ($style == "image") {
                $links = join("</td><td>", $links);
                $html  = sprintf("<table summary=\"". $name ."\" border=0 cellspacing=0 cellpadding=0><tr valign=\"middle\"><td>" ._($label) ."</td>","<td>" .$links)."</tr></td>";
            } else {
                $links = join($sep, $links);
                $html  = sprintf(_($label),$links);
            }

        } else {
            $links = "[" .str_replace(",", ("]" .$sep ."["), $go) ."]";
            $links = do_transform($links, 'LinkTransform');
            $html  = sprintf(_($label),$links);

        }
        // TODO: (maybe) localise individual item labels (the
        // parts of the $go text before the "|"s)

        return $html;
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
