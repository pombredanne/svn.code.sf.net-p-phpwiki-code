<?php
// display.php: fetch page or get default content
rcs_id('$Id: display.php,v 1.43 2003-02-26 22:27:19 dairiki Exp $');

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

function displayPage(&$request, $template=false) {
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
        if ($request->getArg('frame'))
            $pagetitle->setAttr('target', '_top');
    }

    $pageheader = $pagetitle;
    if (($redirect_from = $request->getArg('redirectfrom'))) {
        $redirect_message = HTML::span(array('class' => 'redirectfrom'),
                                       fmt("(Redirected from %s)",
                                           RedirectorLink($redirect_from)));
        $pageheader = HTML($pagetitle, $redirect_message);
    }

    $request->appendValidators(array('pagerev' => $revision->getVersion(),
                                     '%mtime' => $revision->get('mtime')));

    // FIXME: should probably be in a template...
    header("Content-Type: text/html; charset=" . CHARSET); // FIXME: this gets done twice?
    
    $toks['CONTENT'] = new Template('browse', $request,
                                    $revision->getTransformedContent());
    
    $request->checkValidators();
    
    $toks['TITLE'] = $pagetitle;
    $toks['HEADER'] = $pageheader;
    $toks['revision'] = $revision;
    $toks['ROBOTS_META'] = 'index,follow';
    $toks['PAGE_DESCRIPTION'] = GleanDescription($revision);
    
    if (!$template)
        $template = new Template('html', $request);

    $template->printExpansion($toks);
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
