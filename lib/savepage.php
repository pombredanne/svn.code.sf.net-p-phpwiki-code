<?php rcs_id('$Id: savepage.php,v 1.33 2002-01-24 00:45:28 dairiki Exp $');
require_once('lib/Template.php');
require_once('lib/transform.php');
require_once('lib/ArchiveCleaner.php');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

// converts spaces to tabs
function CookSpaces($pagearray) {
    return preg_replace("/ {3,8}/", "\t", $pagearray);
}

// FIXME: some links so that it's easy to get back to someplace useful
// from these error pages.

function ConcurrentUpdates(&$request) {
   /*
     xgettext only knows about c/c++ line-continuation strings
     it does not know about php's dot operator.
     We want to translate this entire paragraph as one string, of course.
   */
    $step = array(_("Use your browser's <b>Back</b> button to go back to the edit page."),
                  _("Copy your changes to the clipboard or to another temporary place (e.g. text editor)."),
                  _("<b>Reload</b> the page. You should now see the most current version of the page. Your changes are no longer there."),
                  _("Make changes to the file again. Paste your additions from the clipboard (or text editor)."),
                  _("Press <b>Save</b> again."));

    
        
    $html[] = HTML::p(_("PhpWiki is unable to save your changes, because another user edited and saved the page while you were editing the page too. If saving proceeded now changes from the previous author would be lost."));

    $html[] = HTML::p(_("In order to recover from this situation follow these steps:"));

    $steps = HTML::ol();
    foreach ($steps as $step)
        $steps->pushContent(HTML::li($step));
    $html[] = $steps;

    $html[] = HTML::p(_("Sorry for the inconvenience."));

    $pagelink = LinkWikiWord($request->getPage());
    GeneratePage($html, fmt("Problem while updating %s", $pagelink));
    $request->finish();
}

function PageIsLocked (&$request) {
    $html[] = HTML::p(_("This page has been locked by the administrator so your changes could not be saved."));
    $html[] = HTML::p(_("Use your browser's <b>Back</b> button to go back to the edit page."),
                      ' ',
                      _("Copy your changes to the clipboard. You can try editing a different page or save your text in a text editor."));
    $html[] = HTML::p(_("Sorry for the inconvenience."));
    
    $pagelink = LinkWikiWord($request->getPage());
    GeneratePage($html, fmt("Problem while editing %s", $pagelink));
    $request->finish();
}

function BadFormVars (&$request) {
    $html[] = HTML::p(_("Bad form submission"));
    $html[] = HTML::p(_("Required form variables are missing."));
    $pagelink = LinkWikiWord($request->getPage());
    GeneratePage($html, fmt("Edit aborted: %s", $pagelink));
    $request->finish();
}

function savePage (&$request) {
    global $Theme;
    
    if (! $request->isPost())
        BadFormVars($request);
    
    if ($request->getArg('preview') || !$request->getArg('save')) {
        include_once('lib/editpage.php');
        return editPage($request, 'do_preview');
    }
    
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');
    
    $page = $request->getPage();
    $current = $page->getCurrentRevision();
    
    $content = $request->getArg('content');
    $editversion = $request->getArg('editversion');
    
    if ( $content === false || $editversion === false )
        BadFormVars($request); // noreturn

    $user = $request->getUser();
    if ($page->get('locked') && !$user->isAdmin())
        PageIsLocked($request); // noreturn.

    $meta['author'] = $user->getId();
    $meta['author_id'] = $user->getAuthenticatedId();
    $meta['is_minor_edit'] = (bool) $request->getArg('minor_edit');
    $meta['summary'] = trim($request->getArg('summary'));

    $content = preg_replace('/[ \t\r]+\n/', "\n", chop($content));
    if ($request->getArg('convert'))
        $content = CookSpaces($content);

    if ($content == $current->getPackedContent()) {
        // Save failed. No changes made.
        include_once('lib/display.php');
        // force browse of current version:
        $request->setArg('version', false);
        return displayPage($request, 'nochanges');
    }

    ////////////////////////////////////////////////////////////////
    //
    // From here on, we're actually saving.
    //
    $newrevision = $page->createRevision($editversion + 1,
                                         $content, $meta,
                                         ExtractWikiPageLinks($content));
        
        
    if (!is_object($newrevision)) {
        // Save failed.  (Concurrent updates).
        // FIXME: this should return one to the editing form,
        //        (with an explanatory message, and options to
        //        view diffs & merge...)
        ConcurrentUpdates($request);
    }
    // New contents successfully saved...

    // Clean out archived versions of this page.
    $cleaner = new ArchiveCleaner($GLOBALS['ExpireParams']);
    $cleaner->cleanPageRevisions($page);

    $dbi = $request->getDbh();
    $warnings = $dbi->GenericWarnings();

    if (empty($warnings) && ! $Theme->getImageURL('signature')) {
        // Do redirect to browse page if no signature has
        // been defined.  In this case, the user will most
        // likely not see the rest of the HTML we generate
        // (below).
        $request->redirect(WikiURL($pagename, false,
                                   'absolute_url'));
    }

    // Force browse of current page version.
    $request->setArg('version', false);
    
    $template = Template('savepage', do_transform($newrevision->getContent()));
    if (!empty($warnings))
        $template->replace('WARNINGS', $warnings);

    $pagelink = $Theme->linkExistingWikiWord($pagename);
    
    GeneratePage($template, fmt("Saved: %s", $pagelink), $newrevision);
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
