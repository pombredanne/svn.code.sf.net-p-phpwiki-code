<?php
// display.php: fetch page or get default content
// calls transform.php for actual transformation of wiki markup to HTML
rcs_id('$Id: display.php,v 1.16 2002-01-23 05:10:22 dairiki Exp $');

require_once('lib/Template.php');
require_once('lib/transform.php');

/**
 * Guess a short description of the page.
 *
 * Algorithm:
 *
 * This algorithm was suggested on MeatballWiki by
 * Alex Schroeder <kensanata@yahoo.com>.
 *
 * Use the first paragraph in the page which contains at least two
 * sentences.
 *
 * @see http://www.usemod.com/cgi-bin/mb.pl?MeatballWikiSuggestions
 */
function GleanDescription ($rev) {
    $two_sentences
        = pcre_fix_posix_classes("/[.?!]\s+[[:upper:])]"
                                 . ".*"
                                 . "[.?!]\s*([[:upper:])]|$)/sx");
        
    $content = $rev->getPackedContent();

    // Iterate through paragraphs.
    while (preg_match('/(?: ^ \w .* $ \n? )+/mx', $content, $m)) {
        $paragraph = $m[0];
        
        // Return paragraph if it contains at least two sentences.
        if (preg_match($two_sentences, $paragraph)) {
            return preg_replace("/\s*\n\s*/", " ", trim($paragraph));
        }

        $content = substr(strstr($content, $paragraph), strlen($paragraph));
    }
    return '';
}

function displayPage(&$request, $tmpl = 'browse') {
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');
    $page = $request->getPage();
    
    
    if ($version) {
        $revision = $page->getRevision($version);
        if (!$revision)
            NoSuchRevision($request, $pagename, $version);
    }
    else {
        $revision = $page->getCurrentRevision();
    }

    $splitname = split_pagename($pagename);
    $pagetitle = HTML::a(array('href' => WikiURL(_("BackLinks"),
                                                 array('page' => $pagename)),
                               'class' => 'backlinks'),
                         $splitname);
    $pagetitle->addTooltip(sprintf(_("BackLinks for %s"), $pagename));


    $wrapper = new WikiTemplate('top');
    $wrapper->setPageRevisionTokens($revision);
    $wrapper->qreplace('TITLE', $splitname);
    $wrapper->replace('HEADER', $pagetitle);
    $wrapper->qreplace('ROBOTS_META', 'index,follow');


    $template = new WikiTemplate($tmpl);
    $template->replace('CONTENT', do_transform($revision->getContent()));
    $template->qreplace('PAGE_DESCRIPTION', GleanDescription($revision));

    $wrapper->printExpansion($template);
    flush();
    $page->increaseHitCount();
}

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>
