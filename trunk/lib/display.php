<?php
// display.php: fetch page or get default content
rcs_id('$Id: display.php,v 1.55 2004-09-26 14:58:35 rurban Exp $');

require_once('lib/Template.php');

/**
 * Extract keywords from Category* links on page. 
 */
function GleanKeywords ($page) {
    global $KeywordLinkRegexp;

    $links = $page->getLinks(false);

    $keywords[] = SplitPagename($page->getName());
    
    while ($link = $links->next())
        if (preg_match("/${KeywordLinkRegexp}/x", $link->getName(), $m))
            $keywords[] = SplitPagename($m[0]);

    $keywords[] = WIKI_NAME;
    
    return join(', ', $keywords);
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
    global $WikiTheme;

    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');

    $page = $request->getPage();
    $revision = $page->getCurrentRevision();

    $dbi = $request->getDbh();
    $actionpage = $dbi->getPage($action);
    $actionrev = $actionpage->getCurrentRevision();

    $pagetitle = HTML(fmt("%s: %s", 
                          $actionpage->getName(),
                          $WikiTheme->linkExistingWikiWord($pagename, false, $version)));

    $validators = new HTTP_ValidatorSet(array('pageversion' => $revision->getVersion(),
                                              '%mtime' => $revision->get('mtime')));
                                        
    $request->appendValidators(array('pagerev' => $revision->getVersion(),
                                     '%mtime' => $revision->get('mtime')));
    $request->appendValidators(array('actionpagerev' => $actionrev->getVersion(),
                                     '%mtime' => $actionrev->get('mtime')));

    $transformedContent = $actionrev->getTransformedContent();
    $template = Template('browse', array('CONTENT' => $transformedContent));
/*
    if (!headers_sent()) {
        //FIXME: does not work yet. document.write not supported (signout button)
        // http://www.w3.org/People/mimasa/test/xhtml/media-types/results
        if (ENABLE_XHTML_XML 
            and (!isBrowserIE() and
                 strstr($request->get('HTTP_ACCEPT'),'application/xhtml+xml')))
            header("Content-Type: application/xhtml+xml; charset=" . $GLOBALS['charset']);
        else
            header("Content-Type: text/html; charset=" . $GLOBALS['charset']);
    }
*/    
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

    if (isSubPage($pagename)) {
        $pages = explode(SUBPAGE_SEPARATOR, $pagename);
        $last_page = array_pop($pages); // deletes last element from array as side-effect
        $pagetitle = HTML::span(HTML::a(array('href' => WikiURL($pages[0]),
                                              'class' => 'pagetitle'
                                              ),
                                        SplitPagename($pages[0] . SUBPAGE_SEPARATOR)));
        $first_pages = $pages[0] . SUBPAGE_SEPARATOR;
        array_shift($pages);
        foreach ($pages as $p)  {
            $pagetitle->pushContent(HTML::a(array('href' => WikiURL($first_pages . $p),
                                                  'class' => 'backlinks'),
                                       SplitPagename($p . SUBPAGE_SEPARATOR)));
            $first_pages .= $p . SUBPAGE_SEPARATOR;
        }
        $backlink = HTML::a(array('href' => WikiURL($pagename,
                                                    array('action' => _("BackLinks"))),
                                  'class' => 'backlinks'),
                            SplitPagename($last_page));
        $backlink->addTooltip(sprintf(_("BackLinks for %s"), $pagename));
        $pagetitle->pushContent($backlink);
    } else {
        $pagetitle = HTML::a(array('href' => WikiURL($pagename,
                                                     array('action' => _("BackLinks"))),
                                   'class' => 'backlinks'),
                             SplitPagename($pagename));
        $pagetitle->addTooltip(sprintf(_("BackLinks for %s"), $pagename));
        if ($request->getArg('frame'))
            $pagetitle->setAttr('target', '_top');
    }

    $pageheader = $pagetitle;
    if (($redirect_from = $request->getArg('redirectfrom'))) {
        $redirect_message = HTML::span(array('class' => 'redirectfrom'),
                                       fmt("(Redirected from %s)",
                                           RedirectorLink($redirect_from)));
    }

    $request->appendValidators(array('pagerev' => $revision->getVersion(),
                                     '%mtime' => $revision->get('mtime')));
/*
    // FIXME: This is also in the template...
    if ($request->getArg('action') != 'pdf' and !headers_sent()) {
      // FIXME: enable MathML/SVG/... support
      if (ENABLE_XHTML_XML
             and (!isBrowserIE()
                  and strstr($request->get('HTTP_ACCEPT'),'application/xhtml+xml')))
            header("Content-Type: application/xhtml+xml; charset=" . $GLOBALS['charset']);
        else
            header("Content-Type: text/html; charset=" . $GLOBALS['charset']);
    }
*/
    $page_content = $revision->getTransformedContent();

    // if external searchengine (google) referrer, highlight the searchterm
    // FIXME: move that to the transformer?
    // OR: add the searchhightplugin line to the content?
    if ($result = isExternalReferrer($request)) {
    	if (DEBUG and !empty($result['query'])) {
            include_once('lib/WikiPlugin.php');
	    $loader = new WikiPluginLoader;
            $xml = $loader->expandPI('<'.'?plugin SearchHighLight s="'.$result['query'].'"?'.'>', $request, $markup);
            if ($xml) {
              foreach (array_reverse($xml) as $line) {
                array_unshift($page_content->_content, $line);
              }
              array_unshift($page_content->_content, 
                            HTML::div(_("You searched for: "), HTML::strong($result['query'])));
            }
            if (0) {
            require_once("lib/TextSearchQuery.php");
            $query = new TextSearchQuery($result['query']);
            $hilight_re = $query->getHighlightRegexp();
            $matches = preg_grep("/$hilight_re/i", $revision->getContent());
            // FIXME!
            foreach ($page_content->_content as $line) {
            	if (is_string($line)) {
            	  $html = array();
                  while (preg_match("/^(.*?)($hilight_re)/i", $line, $m)) {
                    $line = substr($line, strlen($m[0]));
                    $html[] = $m[1];    // prematch
                    $html[] = HTML::strong(array('class' => 'search-term'), $m[2]); // match
                  }
            	}
                $html[] = $line;  // postmatch
                //$html = HTML::dd(HTML::small(array('class' => 'search-context'),
                //                             $html));
            }
            }
        }
    }
   
    $toks['CONTENT'] = new Template('browse', $request, $page_content);
    
    $toks['TITLE'] = $pagetitle;
    $toks['HEADER'] = $pageheader;
    $toks['revision'] = $revision;
    if (!empty($redirect_message))
        $toks['redirected'] = $redirect_message;
    $toks['ROBOTS_META'] = 'index,follow';
    $toks['PAGE_DESCRIPTION'] = $page_content->getDescription();
    $toks['PAGE_KEYWORDS'] = GleanKeywords($page);
    if (!$template)
        $template = new Template('html', $request);
    
    $template->printExpansion($toks);
    $page->increaseHitCount();

    if ($request->getArg('action') != 'pdf')
        $request->checkValidators();
    flush();
}

// $Log: not supported by cvs2svn $
// Revision 1.54  2004/09/17 14:19:41  rurban
// disable Content-Type header for now, until it is fixed
//
// Revision 1.53  2004/06/25 14:29:20  rurban
// WikiGroup refactoring:
//   global group attached to user, code for not_current user.
//   improved helpers for special groups (avoid double invocations)
// new experimental config option ENABLE_XHTML_XML (fails with IE, and document.write())
// fixed a XHTML validation error on userprefs.tmpl
//
// Revision 1.52  2004/06/14 11:31:37  rurban
// renamed global $Theme to $WikiTheme (gforge nameclash)
// inherit PageList default options from PageList
//   default sortby=pagename
// use options in PageList_Selectable (limit, sortby, ...)
// added action revert, with button at action=diff
// added option regex to WikiAdminSearchReplace
//
// Revision 1.51  2004/05/18 16:23:39  rurban
// rename split_pagename to SplitPagename
//
// Revision 1.50  2004/05/04 22:34:25  rurban
// more pdf support
//
// Revision 1.49  2004/04/18 01:11:52  rurban
// more numeric pagename fixes.
// fixed action=upload with merge conflict warnings.
// charset changed from constant to global (dynamic utf-8 switching)
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
