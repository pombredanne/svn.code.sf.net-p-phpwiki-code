<?php // -*-php-*-
rcs_id('$Id: walkabout.php,v 1.4 2002-01-11 06:39:06 carstenklapp Exp $');
/**
 * Usage:
 *
 * <?plugin walkabout parent=MyTableOfContents ?>
 *
 * <?plugin walkabout parent=MyTableOfContents label="Visit more pages in MyTableOfContents: %s" sep=, ?>
 *
 * <?plugin walkabout parent=MyTableOfContents section=PartTwo loop=true ?>
 *
 * <?plugin walkabout parent=MyTableOfContents loop=1 ?>
 *
 *
 */




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
                     'rev'     => false,
                     'section' => _("Contents"),
                     'sep'     => '|',
                     'label'   => '',
                     'loop'    => false,
                     'style'   => 'text',
                     );
    }

    // Stolen from Toolbar.php
    function mklinks($text, $action) {
        return do_transform("[$text|$action]", 'LinkTransform');
    }

    // Stolen from IncludePage.php
    function extractSection ($section, $content) {
        $qsection = preg_replace('/\s+/', '\s+', preg_quote($section, '/'));

        if (preg_match("/ ^(!{1,})\\s*$qsection" // section header
                       . "  \\s*$\\n?"           // possible blank lines
                       . "  ( (?: ^.*\\n? )*? )" // some lines
                       . "  (?= ^\\1 | \\Z)/xm", // sec header (same or higher level) (or EOF)
                       implode("\n", $content),
                       $match)) {
            // Strip trailing blanks lines and ---- <hr>s
            $text = preg_replace("/\\s*^-{4,}\\s*$/m", "", $match[2]);
            return explode("\n", $text);
        }
        return array(sprintf(_("<%s: no such section>"), $section));
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

        // This is where the list extraction occurs from the named
        // $section on the $parent page.

        $p = $dbi->getPage($parent);
        if ($rev) {
            $r = $p->getRevision($rev);
            if (!$r) {
                $this->error(sprintf(_("%s(%d): no such revision"), $parent,
                                     $rev));
                return '';
            }
        } else {
            $r = $p->getCurrentRevision();
        }

        $c = $r->getContent();
        $c = $this->extractSection($section, $c);

        //debugging only
        //foreach ( $c as $line ) {
        //    echo $line ."<br />";
        //}

       $pagename = $request->getArg('pagename');

        // The ordered list of page names determines the page
        // ordering. Right now it doesn't work with a WikiList, only
        // normal lines of text containing the page names.

        $thispage = array_search($pagename, $c);

        $go = array ('previous','next');
        $links = array();
        $max = count($c) - 1; //array is 0-based, count is 1-based!

        foreach ( $go as $go_item ) {
            //yuck this smells, needs optimization.
            if ($go_item == 'previous') {
                if ($loop) {
                    if ($thispage == 0) {
                        $action  = $c[$max];
                    } else {
                        $action  = $c[$thispage - 1];
                    }
                    $text    = sprintf(_("%s: %s"), $directions[$go_item], '%s');
                    $links[] = sprintf($text, $this->mklinks($action, $action));
                } else {
                    if ($thispage == 0) {
                        // skip it
                    } else {
                        $text    = sprintf(_("%s: %s"), $directions[$go_item], '%s');
                        $action  = $c[$thispage - 1];
                        $links[] = sprintf($text, $this->mklinks($action, $action));
                    }
                }
            } else if ($go_item == 'next') {
                if ($loop) {
                    if ($thispage == $max) {
                        $action  = $c[1];
                    } else {
                        $action  = $c[$thispage + 1];
                    }
                    $text    = sprintf(_("%s: %s"), $directions[$go_item], '%s');
                    $links[] = sprintf($text, $this->mklinks($action, $action));
                } else {
                    if ($thispage == $max) {
                        // skip it
                    } else {
                        $text    = sprintf(_("%s: %s"), $directions[$go_item], '%s');
                        $action  = $c[$thispage + 1];
                        $links[] = sprintf($text, $this->mklinks($action, $action));
                    }
                }
            }
        }

        // Final assembly
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
