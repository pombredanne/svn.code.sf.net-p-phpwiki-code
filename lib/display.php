<?php
// display.php: fetch page or get default content
rcs_id('$Id: display.php,v 1.42 2003-02-21 04:12:05 dairiki Exp $');

require_once('lib/Template.php');

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
    // Escape strings
    $content = preg_replace("/(['\"])/", "\$1", $rev->getPackedContent());

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


/** Make a link back to redirecting page.
 *
 * @param $pagename string  Name of redirecting page.
 * @return XmlContent Link to the redirecting page.
 */
function RedirectorLink($pagename) {
    $url = WikiURL($pagename, array('redirectfrom' => ''));
    return HTML::a(array('class' => 'redirectfrom wiki',
                         'href' => $url),
                   $pagename);
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

    // $splitname = split_pagename($pagename);

    $pagetitle = HTML(fmt("%s: %s", $actionpage->getName(),
                          $Theme->linkExistingWikiWord($pagename, false, $version)));

    $validators = new HTTP_ValidatorSet(array('pageversion' => $revision->getVersion(),
                                              '%mtime' => $revision->get('mtime')));
                                        
    $request->appendValidators(array('pagerev' => $revision->getVersion(),
                                     '%mtime' => $revision->get('mtime')));
    $request->appendValidators(array('actionpagerev' => $actionrev->getVersion(),
                                     '%mtime' => $actionrev->get('mtime')));

    $transformedContent = $actionrev->getTransformedContent();
    $template = Template('browse', array('CONTENT' => $transformedContent));

    header("Content-Type: text/html; charset=" . CHARSET);
    GeneratePage($template, $pagetitle, $revision);
    $request->checkValidators();
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
    if (isSubPage($pagename)) {
        $pages = explode(SUBPAGE_SEPARATOR,$pagename);
        $last_page = array_pop($pages); // deletes last element from array as side-effect
        $pagetitle = HTML::span(HTML::a(array('href' => WikiURL($pages[0]),
                                              'class' => 'pagetitle'
                                              ),
                                        split_pagename($pages[0] . SUBPAGE_SEPARATOR)));
        $first_pages = $pages[0] . SUBPAGE_SEPARATOR;
        array_shift($pages);
        foreach ($pages as $p)  {
            $pagetitle->pushContent(HTML::a(array('href' => WikiURL($first_pages . $p),
                                                  'class' => 'backlinks'),
                                       split_pagename($p . SUBPAGE_SEPARATOR)));
            $first_pages .= $p . SUBPAGE_SEPARATOR;
        }
        $backlink = HTML::a(array('href' => WikiURL($pagename,
                                                    array('action' => _("BackLinks"))),
                                  'class' => 'backlinks'),
                            split_pagename($last_page));
        $backlink->addTooltip(sprintf(_("BackLinks for %s"), $pagename));
        $pagetitle->pushContent($backlink);
    } else {
        $pagetitle = HTML::a(array('href' => WikiURL($pagename,
                                                     array('action' => _("BackLinks"))),
                                   'class' => 'backlinks'),
                             $splitname);
        $pagetitle->addTooltip(sprintf(_("BackLinks for %s"), $pagename));
    }

    $redirect_from = $request->getArg('redirectfrom');
    if ($redirect_from) {
        $redirect_from = fmt("Redirected from %s", RedirectorLink($redirect_from));
    }

    //include_once('lib/BlockParser.php');

    $request->appendValidators(array('pagerev' => $revision->getVersion(),
                                     '%mtime' => $revision->get('mtime')));

    if ($frame = $request->getArg('frame')) {
        if (in_array($frame, array('body','browse','editpage')))
            $template = Template($frame, array('CONTENT' => $revision->getTransformedContent()));
        elseif ($frame == 'top')
            $template = Template($frame, array('framesrc' => $request->getArg('framesrc')));
        else
            $template = Template($frame);
    } else {
        $transformedContent = $revision->getTransformedContent();
        $template = Template('browse', array('CONTENT' => $transformedContent));
    }

    header("Content-Type: text/html; charset=" . CHARSET); // FIXME: this gets done twice?
    
    GeneratePage($template, $pagetitle, $revision,
                 array('ROBOTS_META'	=> 'index,follow',
                       'PAGE_DESCRIPTION' => GleanDescription($revision),
                       'REDIRECT_FROM' => $redirect_from));
    $request->checkValidators();
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
