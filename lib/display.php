<?php
// display.php: fetch page or get default content
rcs_id('$Id$');

require_once('lib/Template.php');

/**
 * Extract keywords from Category* links on page. 
 */
function GleanKeywords ($page) {
    if (!defined('KEYWORDS')) return '';
    require_once("lib/TextSearchQuery.php");
    $search = new TextSearchQuery(KEYWORDS, true);
    $KeywordLinkRegexp = $search->asRegexp();
    // iterate over the pagelinks (could be a large number) [15ms on PluginManager]
    // or do a titleSearch and check the categories if they are linked?
    $links = $page->getPageLinks();
    $keywords[] = SplitPagename($page->getName());
    while ($link = $links->next()) {
    	$m = array();
        if (preg_match($KeywordLinkRegexp, $link->getName(), $m))
            $keywords[] = SplitPagename($m[0]);
    }
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

    $request->setValidators(array('pageversion' => $revision->getVersion(),
                                  '%mtime' => $revision->get('mtime')));
    $request->appendValidators(array('pagerev' => $revision->getVersion(),
                                     '%mtime' => $revision->get('mtime')));
    $request->appendValidators(array('actionpagerev' => $actionrev->getVersion(),
                                     '%mtime' => $actionrev->get('mtime')));

    $transformedContent = $actionrev->getTransformedContent();
 
   /* Optionally tell google (and others) not to take notice of action pages.
      RecentChanges or AllPages might be an exception.
   */
    $args = array();
    if (GOOGLE_LINKS_NOFOLLOW)
	$args = array('ROBOTS_META' => "noindex,nofollow");

    /* Handle other formats: So far we had html only.
       xml is requested by loaddump, rss is handled by recentchanges, 
       pdf is a special action, but should be a format to dump multiple pages
       if the actionpage plugin returns a pagelist.
       rdf and owl are handled by SemanticWeb.
    */
    $format = $request->getArg('format');
    
    /* At first the single page formats: html, xml */
    if ($pagename == _("LinkDatabase")) {
        $template = Template('browse', array('CONTENT' => $transformedContent));
	GeneratePage($template, $pagetitle, $revision, $args);
    } elseif (!$format or $format == 'html' or $format == 'sidebar' or $format == 'contribs') {
	$template = Template('browse', array('CONTENT' => $transformedContent));
	GeneratePage($template, $pagetitle, $revision, $args);
    } elseif ($format == 'xml') {
	$template = new Template('browse', $request,
                                 array('revision' => $revision,
                                       'CONTENT'  => $transformedContent,
				       ));
	$html = GeneratePageAsXML($template, $pagename, $revision /*, 
				  array('VALID_LINKS' => $args['VALID_LINKS'])*/);
	header("Content-Type: application/xhtml+xml; charset=" . $GLOBALS['charset']);
	echo $html;
    } else {
    	$pagelist = null;
    	require_once('lib/WikiPlugin.php');
	// Then the multi-page formats
	// rss (if not already handled by RecentChanges)
	// Need the pagelist from the first plugin
	foreach($transformedContent->_content as $cached_element) {
	    if (is_a($cached_element, "Cached_PluginInvocation")) {
	        $loader = new WikiPluginLoader;
	        $markup = null;
	        // return the first found pagelist
	        $pagelist = $loader->expandPI($cached_element->_pi, $request, 
	                                      $markup, $pagename);
	        if (is_a($pagelist, 'PageList'))
	            break;
	    }
	}
        $args['VALID_LINKS'] = array($pagename);
        if (!$pagelist or !is_a($pagelist, 'PageList')) {
	    if (!in_array($format, array("rss91","rss2","rss","atom","rdf")))
		trigger_error(sprintf("Format %s requires an actionpage returning a pagelist.", 
				      $format)
			      ."\n".("Fall back to single page mode"), E_USER_WARNING);
	    require_once('lib/PageList.php');
	    $pagelist = new PageList();
	    $pagelist->addPage($page);
	} else {
            foreach ($pagelist->_pages as $page) {
            	$name = $page->getName();
            	if ($name != $pagename and $page->exists())
                    $args['VALID_LINKS'][] = $name;
            }
	}
	if ($format == 'pdf') {
	    require_once("lib/pdf.php");
	    ConvertAndDisplayPdfPageList($request, $pagelist, $args);
	}
	elseif ($format == 'ziphtml') { // need to fix links
	    require_once('lib/loadsave.php');
	    $request->setArg('zipname', FilenameForPage($pagename).".zip");
	    $request->setArg('pages', $args['VALID_LINKS']);
	    MakeWikiZipHtml($request);
	} // time-sorted RDF á la RecentChanges 
	elseif (in_array($format, array("rss91","rss2","rss","atom"))) {
            $args = $request->getArgs();
            if ($pagename == _("RecentChanges")) {
                $template->printExpansion($args);
	    } else {
	        require_once("lib/plugin/RecentChanges.php");
	        $plugin = new WikiPlugin_RecentChanges();
                return $plugin->format($plugin->getChanges($request->_dbi, $args), $args);
	    }
	} elseif ($format == 'rdf') { // all semantic relations and attributes
	    require_once("lib/SemanticWeb.php");
	    $rdf = new RdfWriter($request, $pagelist);
	    $rdf->format();
	} elseif ($format == 'rdfs') {
	    require_once("lib/SemanticWeb.php");
	    $rdf = new RdfsWriter($request, $pagelist);
	    $rdf->format();
	} elseif ($format == 'owl') { // or daml?
	    require_once("lib/SemanticWeb.php");
	    $rdf = new OwlWriter($request, $pagelist);
	    $rdf->format();
	} else {
	    if (!in_array($pagename, array(_("LinkDatabase"))))
		trigger_error(sprintf(_("Unsupported argument: %s=%s"),"format",$format),
	    	              E_USER_WARNING);
	    $template = Template('browse', array('CONTENT' => $transformedContent));
	    GeneratePage($template, $pagetitle, $revision, $args);
	}
    }
    $request->checkValidators();
    flush();
    return '';
}

function displayPage(&$request, $template=false) {
    global $WikiTheme;
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');
    $page = $request->getPage();
    if ($version) {
        $revision = $page->getRevision($version);
        if (!$revision)
            NoSuchRevision($request, $page, $version);
        /* Tell Google (and others) to ignore old versions of pages */
	$toks['ROBOTS_META'] = "noindex,nofollow";
    }
    else {
        $revision = $page->getCurrentRevision();
    }

    if (isSubPage($pagename)) {
        $pages = explode(SUBPAGE_SEPARATOR, $pagename);
        $last_page = array_pop($pages); // deletes last element from array as side-effect
        $pageheader = HTML::span(HTML::a(array('href' => WikiURL($pages[0]),
                                               'class' => 'pagetitle'
                                              ),
                                        $WikiTheme->maybeSplitWikiWord($pages[0] . SUBPAGE_SEPARATOR)));
        $first_pages = $pages[0] . SUBPAGE_SEPARATOR;
        array_shift($pages);
        foreach ($pages as $p)  {
            $pageheader->pushContent(HTML::a(array('href' => WikiURL($first_pages . $p),
                                                  'class' => 'backlinks'),
                                            $WikiTheme->maybeSplitWikiWord($p . SUBPAGE_SEPARATOR)));
            $first_pages .= $p . SUBPAGE_SEPARATOR;
        }
        $backlink = HTML::a(array('href' => WikiURL($pagename,
                                                    array('action' => _("BackLinks"))),
                                  'class' => 'backlinks'),
                            $WikiTheme->maybeSplitWikiWord($last_page));
        $backlink->addTooltip(sprintf(_("BackLinks for %s"), $pagename));
        $pageheader->pushContent($backlink);
    } else {
        $pageheader = HTML::a(array('href' => WikiURL($pagename,
                                                     array('action' => _("BackLinks"))),
                                   'class' => 'backlinks'),
                             $WikiTheme->maybeSplitWikiWord($pagename));
        $pageheader->addTooltip(sprintf(_("BackLinks for %s"), $pagename));
        if ($request->getArg('frame'))
            $pageheader->setAttr('target', '_top');
    }

    $pagetitle = SplitPagename($pagename);
    if (($redirect_from = $request->getArg('redirectfrom'))) {
        $redirect_message = HTML::span(array('class' => 'redirectfrom'),
                                       fmt("(Redirected from %s)",
                                           RedirectorLink($redirect_from)));
    // abuse the $redirected template var for some status update notice                                       
    } elseif ($request->getArg('errormsg')) { 
        $redirect_message = $request->getArg('errormsg');
        $request->setArg('errormsg', false);
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

    $toks['TITLE'] = $pagetitle;   // <title> tag
    $toks['HEADER'] = $pageheader; // h1 with backlink
    $toks['revision'] = $revision;

    // On external searchengine (google) referrer, highlight the searchterm and 
    // pass through the Searchhighlight actionpage.
    if ($result = isExternalReferrer($request)) {
    	if (!empty($result['query'])) {
    	    if (ENABLE_SEARCHHIGHLIGHT) {
                $request->_searchhighlight = $result;
                $request->appendValidators(array('%mtime' => time())); // force no cache(?)
                // Should be changed to check the engine and search term only
                // $request->setArg('nocache', 1); 
                $page_content = new TransformedText($revision->getPage(),
                                                    $revision->getPackedContent(),
                                                    $revision->getMetaData());
		/* Now add the SearchHighlight plugin to the top of the page, in memory only.
		   You can parametrize this by changing the SearchHighlight action page.
		*/
                if ($actionpage = $request->findActionPage('SearchHighlight')) {
                    $actionpage = $request->getPage($actionpage);
                    $actionrev = $actionpage->getCurrentRevision();
                    $pagetitle = HTML(fmt("%s: %s", 
                                          $actionpage->getName(),
                                          $WikiTheme->linkExistingWikiWord($pagename, false, $version)));
                    $request->appendValidators(array('actionpagerev' => $actionrev->getVersion(),
                                                     '%mtime' => $actionrev->get('mtime')));
                    $toks['SEARCH_ENGINE'] = $result['engine'];
                    $toks['SEARCH_ENGINE_URL'] = $result['engine_url'];
                    $toks['SEARCH_TERM'] = $result['query'];
		    //$toks['HEADER'] = HTML($actionpage->getName(),": ",$pageheader); // h1 with backlink
                    $actioncontent = new TransformedText($actionrev->getPage(),
                                                         $actionrev->getPackedContent(),
                                                         $actionrev->getMetaData());
		    // prepend the actionpage in front of the hightlighted content
	            $toks['CONTENT'] = HTML($actioncontent, $page_content);
                }
	    }
	} else {
            $page_content = $revision->getTransformedContent();
	}
    } else {
        $page_content = $revision->getTransformedContent();
    }
   
    /* Check for special pagenames, which are no actionpages. */
    /*
    if ( $pagename == _("RecentVisitors")) {
        $toks['ROBOTS_META']="noindex,follow";
    } else
    */
    if ($pagename == _("SandBox")) {
        $toks['ROBOTS_META']="noindex,nofollow";
    } else if (!isset($toks['ROBOTS_META'])) {
        $toks['ROBOTS_META'] = "index,follow";
    }
    if (!isset($toks['CONTENT']))
        $toks['CONTENT'] = new Template('browse', $request, $page_content);
    if (!empty($redirect_message))
        $toks['redirected'] = $redirect_message;

    // Massive performance problem parsing at run-time into all xml objects 
    // looking for p's. Should be optional, if not removed at all.
    //$toks['PAGE_DESCRIPTION'] = $page_content->getDescription();
    $toks['PAGE_KEYWORDS'] = GleanKeywords($page);
    if (!$template)
        $template = new Template('html', $request);

    // Handle other formats: So far we had html only.
    // xml is requested by loaddump, rss is handled by RecentChanges, 
    // pdf is a special action, but should be a format to dump multiple pages
    // if the actionpage plugin returns a pagelist.
    // rdf, owl, kbmodel, daml, ... are handled by SemanticWeb.
    $format = $request->getArg('format');
    /* Only single page versions. rss only if not already handled by RecentChanges.
     */
    if (!$format or $format == 'html' or $format == 'sidebar' or $format == 'contribs') {
	$template->printExpansion($toks);
    } elseif ($format == 'xml') {
        $template = new Template('htmldump', $request);
	$template->printExpansion($toks);
    } else {
	// No pagelist here. Single page version only
	require_once("lib/PageList.php");
	$pagelist = new PageList();
	$pagelist->addPage($page);
	if ($format == 'pdf') {
	    require_once("lib/pdf.php");
	    ConvertAndDisplayPdfPageList($request, $pagelist);
	// time-sorted rdf a la RecentChanges
	} elseif (in_array($format, array("rss91","rss2","rss","atom"))) {
            if ($pagename == _("RecentChanges"))
                $template->printExpansion($toks);
            else {    
	        require_once("lib/plugin/RecentChanges.php");
	        $plugin = new WikiPlugin_RecentChanges();
                $args = $request->getArgs();
                return $plugin->format($plugin->getChanges($request->_dbi, $args), $args);
            }
	} elseif ($format == 'rdf') { // all semantic relations and attributes
	    require_once("lib/SemanticWeb.php");
	    $rdf = new RdfWriter($request, $pagelist);
	    $rdf->format();
	} elseif ($format == 'owl') { // or daml?
	    require_once("lib/SemanticWeb.php");
	    $rdf = new OwlWriter($request, $pagelist);
	    $rdf->format();
	} else {
	    if (!in_array($pagename, array(_("LinkDatabase"))))
		trigger_error(sprintf(_("Unsupported argument: %s=%s"),"format",$format),
	    	              E_USER_WARNING);
	    $template->printExpansion($toks);
	}
    }
    
    $page->increaseHitCount();

    if ($request->getArg('action') != 'pdf') {
        $request->checkValidators();
    	flush();
    }
    return '';
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
