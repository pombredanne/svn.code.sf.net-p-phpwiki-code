<?php
// display.php: fetch page or get default content
rcs_id('$Id: display.php,v 1.30 2002-03-06 03:52:22 carstenklapp Exp $');

require_once('lib/Template.php');
require_once('lib/BlockParser.php');

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


function actionPage(&$request, $action) {
    global $Theme;

    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');

    $page = $request->getPage();
    $revision = $page->getCurrentRevision();

    $dbi = $request->getDbh();
    $actionpage = $dbi->getPage($action);
    $actionrev = $actionpage->getCurrentRevision();

    $pagetitle = HTML(fmt("%s: %s", $actionpage->getName(),
                          $Theme->linkExistingWikiWord($pagename, false, $version)));

    require_once('lib/PageType.php');
    $transformedContent = PageType($actionrev);
    $template = Template('browse', array('CONTENT' => $transformedContent));

    header("Content-Type: text/html; charset=" . CHARSET);
    header("Last-Modified: ".Rfc2822DateTime($revision->get('mtime')));

    GeneratePage($template, $pagetitle, $revision);
    flush();
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
    $pagetitle = HTML::a(array('href' => WikiURL($pagename,
                                                 array('action' => _("BackLinks"))),
                               'class' => 'backlinks'),
                         $splitname);
    $pagetitle->addTooltip(sprintf(_("BackLinks for %s"), $pagename));

    require_once('lib/PageType.php');
    $transformedContent = PageType($revision);
    $template = Template('browse', array('CONTENT' => $transformedContent));

    header("Content-Type: text/html; charset=" . CHARSET);
    // don't clobber date header given by RC
    if ( ! ($pagename == _("RecentChanges") || $pagename == _("RecentEdits")) )
        header("Last-Modified: ".Rfc2822DateTime($revision->get('mtime')));

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
