<?php
// display.php: fetch page or get default content
// calls transform.php for actual transformation of wiki markup to HTML
rcs_id('$Id: display.php,v 1.18 2002-01-25 01:04:13 dairiki Exp $');

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
            NoSuchRevision($request, $page, $version);
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


    if (0) {
        include_once('lib/BlockParser.php');
        global $BlockParser;
        $content = $BlockParser->parse($revision->getPackedContent());
        //print_r($content);
        $template = Template($tmpl, array('CONTENT' => $content));
    }
    else
        $template = Template($tmpl, do_transform($revision->getContent()));
    

    GeneratePage($template, $pagetitle, $revision,
                 array('ROBOTS_META'	=> 'index,follow',
                       'PAGE_DESCRIPTION' => GleanDescription($revision)));
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
