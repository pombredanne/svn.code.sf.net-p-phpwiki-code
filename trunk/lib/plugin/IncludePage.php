<?php // -*-php-*-
rcs_id('$Id: IncludePage.php,v 1.10 2002-01-23 05:13:38 carstenklapp Exp $');
/**
 * IncludePage:  include text from another wiki page in this one
 * usage:   <?plugin IncludePage page=OtherPage rev=6 quiet=1 words=50 lines=6?>
 * author:  Joe Edelman <joe@orbis-tertius.net>
 */

require_once('lib/transform.php');

class WikiPlugin_IncludePage
extends WikiPlugin
{
    function getName() {
        return _("IncludePage");
    }

    function getDefaultArguments() {
        return array( 'page'    => false, // the page to include
                      'rev'     => false, // the revision (defaults to most recent)
                      'quiet'   => false, // if set, inclusion appears as normal content
                      'words'   => false, // maximum number of words to include
                      'lines'   => false, // maximum number of lines to include
                      'section' => false  // include a named section
                      );
    }

    function firstNWordsOfContent( $n, $content ) {
        $wordcount = 0;
        $new = array( );
        foreach ($content as $line) {
            $words = explode(' ', $line);
            if ($wordcount + count($words) > $n) {
                $new[] = implode(' ', array_slice($words, 0, $n - $wordcount))
                    . "... (first $n words)";
                return $new;
            } else {
                $wordcount += count($words);
                $new[] = $line;
            }
        }
        return $new;
    }

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

    function error($msg) {
        // FIXME: better error reporting?
        trigger_error($msg, E_USER_NOTICE);
    }

    function run($dbi, $argstr, $request) {

        extract($this->getArgs($argstr, $request));

        if (!$page) {
            $this->error(_("no page specified"));
            return '';
        }

        // A page can include itself once (this is needed, e.g.,  when editing
        // TextFormattingRules).
        static $included_pages = array();
        if (in_array($page, $included_pages)) {
            $this->error(sprintf(_("recursive inclusion of page %s"), $page));
            return '';
        }

        $p = $dbi->getPage($page);

        if ($rev) {
            $r = $p->getRevision($rev);
            if (!$r) {
                $this->error(sprintf(_("%s(%d): no such revision"), $page,
                                     $rev));
                return '';
            }
        } else {
            $r = $p->getCurrentRevision();
        }

        $c = $r->getContent();

        if ($section)
            $c = $this->extractSection($section, $c);
        if ($lines)
            $c = array_slice($c, 0, $lines);
        if ($words)
            $c = $this->firstNWordsOfContent($words, $c);

        array_push($included_pages, $page);
        $content = do_transform($c);
        array_pop($included_pages);

        if ($quiet) return $content;

        $html[] = HTML::p(array('class' => 'transclusion-title'),
                          fmt("Included from %s",
                              $Theme->LinkExistingWikiWord($page)));
        
        $html[] = HTML::div(array('class' => 'transclusion'),
                            $content);
        
        return $html;
    }
};

// This is an excerpt from the css file I use:
//
// .transclusion-title {
//   font-style: oblique;
//   font-size: 0.75em;
//   text-decoration: underline;
//   text-align: right;
// }
//
// DIV.transclusion {
//   background: lightgreen;
//   border: thin;
//   border-style: solid;
//   padding-left: 0.8em;
//   padding-right: 0.8em;
//   padding-top: 0px;
//   padding-bottom: 0px;
//   margin: 0.5ex 0px;
// }

// KNOWN ISSUES:
// - line & word limit doesn't work if the included page itself
//   includes a plugin
// - we need an error reporting scheme

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>