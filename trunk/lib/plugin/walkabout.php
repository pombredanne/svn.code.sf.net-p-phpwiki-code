<?php // -*-php-*-
rcs_id('$Id: walkabout.php,v 1.1 2002-01-10 05:04:45 carstenklapp Exp $');
/**
 * Usage:
 *
 * <?plugin walkabout parent=MyTableOfContents ?>
 *
 * <?plugin walkabout parent=MyTableOfContents label="Visit more pages in MyTableOfContents: %s" sep=, ?>
 *
 * <?plugin walkabout parent=MyTableOfContents section=PartTwo ?>
 *
 *
 */




///////////////////////////////////////////////////////////////////////
//                This doesn't work completely yet







// (This is all in a state of flux, so don't count on any of this being
// the same tomorrow...)




require_once('lib/transform.php');

// This is just the working name. Any suggestions?
class WikiPlugin_walkabout
extends WikiPlugin
{
    function getName() {
        return _("walkabout");
    }
    
    function getDescription() {
        return sprintf(_("walkabout for %s"),'[pagename]');
    }
    
    function getDefaultArguments() {
        return array(
                     'parent'  => '',
                     'section' => _("Contents"),
                     'sep'     => '|',
                     'label'   => '',
                     'loop'    => false,
                     'style'   => 'text',
                     );
    }

    function mklinks($text, $action) {
        return do_transform("[$text|$action]", 'LinkTransform');
    }

    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        extract($args);
        $html="";
        if (empty($parent)) {
            // FIXME: WikiPlugin has no way to report when
            // required args are missing?
            $error_text = "WikiPlugin_" .$this->getName() .": ";
            $error_text .= sprintf(_("A required argument '%s' is missing."),
                                   'parent');
            $html = $error_text;
            return $html;
        }

        $directions = array (
                             'next'     => _("Next"),
                             'previous' => _("Previous"),
                             'contents' => _("Contents"),
                             'first'    => _("First"),
                             'last'     => _("Last")
                             );

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
            // I believe French punctuation rules may require a space
            // before a colon.
            $label = sprintf(_("%s: %s"), $parent, '%s');
        }

        // This is where the list extraction will occur from the named
        // $section on the $parent page. The ordered list will
        // determine the page ordering.










        $links = array(
                       $directions['previous'] => $previous,
                       $directions['next']     => $next
                       );

        //final assembly
        $links = join($sep, $links);
        $html  = sprintf(do_transform(_($label)),$links);

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
