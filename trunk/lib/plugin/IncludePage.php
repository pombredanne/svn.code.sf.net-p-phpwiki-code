<?php // -*-php-*-
rcs_id('$Id: IncludePage.php,v 1.1 2001-12-02 02:12:21 joe_edelman Exp $');
/**
 * IncludePage:  include text from another wiki page in this one
 * usage:   <?plugin IncludePage page=OtherPage rev=6 quiet=1 words=50 lines=6?>
 * author:  Joe Edelman <joe@orbis-tertius.net>
 */

require_once('lib/transform.php');

class WikiPlugin_IncludePage
extends WikiPlugin
{
    function getDefaultArguments() {
        return array( 'page'  => false,    // the page to include
                      'rev'   => false,    // the revision (defaults to most recent)
                      'quiet' => false,    // if set, inclusion appears as normal content
                      'words' => false,    // maximum number of words to include
                      'lines' => false     // maximum number of lines to include
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
    
    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        if (!$page) return '';         // FIXME:  error reporting?
        $p = $dbi->getPage($page); 
        
        if ($rev) {
            $r = $p->getRevision($rev);
            if (!$r) return '';        // FIXME:  error reporting?
        } else {
            $r = $p->getCurrentRevision();
        }

        $c = $r->getContent();
        
        if ($lines)
            $c = array_slice($c, 0, $lines);
        if ($words) 
            $c = $this->firstNWordsOfContent($words, $c);
                    
        $content = do_transform($c);
        if ($quiet) return $content;
        return 
                 '<p class="transclusion-title">Included from '
               . LinkExistingWikiWord($page)
               . '</p>'
               . '<div class="transclusion">'  . $content 
               . '</div>';
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
//   - line & word limit doesn't work if the included page itself includes a plugin
//   - we need an error reporting scheme

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
        
?>
