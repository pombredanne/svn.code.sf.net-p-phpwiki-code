<?php rcs_id('$Id: savepage.php,v 1.30 2002-01-21 06:55:47 dairiki Exp $');
require_once('lib/Template.php');
require_once('lib/transform.php');
require_once('lib/ArchiveCleaner.php');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

// FIXME: some links so that it's easy to get back to someplace useful
// from these error pages.

function ConcurrentUpdates($pagename) {
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

    foreach ($steps as $key => $step) {
        $step[$key] = Element('li', $step);
    }
    
        
    $html = QElement('p',
                     _("PhpWiki is unable to save your changes, because another user edited and saved the page while you were editing the page too. If saving proceeded now changes from the previous author would be lost."));

    $html .= Element('p',
                     _("In order to recover from this situation follow these steps:"));

    $html .= Element('ol', join("\n", $steps));

    $html .= QElement('p', _("Sorry for the inconvenience."));

    echo GeneratePage('MESSAGE', $html,
                      sprintf(_("Problem while updating %s"), $pagename));
    ExitWiki();
}

function PageIsLocked($pagename) {
    $html = QElement('p',
                     _("This page has been locked by the administrator so your changes could not be saved."));
    $html .= '<p>' ._("Use your browser's <b>Back</b> button to go back to the edit page.");
    $html .= _("Copy your changes to the clipboard. You can try editing a different page or save your text in a text editor.");
    $html .= '</p>';
    $html .= QElement('p',
                      _("Sorry for the inconvenience."));
    
    echo GeneratePage('MESSAGE', $html,
                      sprintf (_("Problem while editing %s"), $pagename));
    ExitWiki ("");
}

function BadFormVars($pagename) {
    $html = QElement('p', _("Bad form submission"));
    $html .= QElement('p', _("Required form variables are missing."));
    echo GeneratePage('MESSAGE', $html,
                      sprintf(_("Edit aborted: %s"), $pagename));
    ExitWiki ("");
}

function savePage ($dbi, $request) {
    global $user, $Theme;
    
    if ($request->get('REQUEST_METHOD') != 'POST')
        BadFormVars($request->getArg('pagename'));
    
    if ($request->getArg('preview') || !$request->getArg('save')) {
        include_once('lib/editpage.php');
        return editPage($dbi, $request, 'do_preview');
    }
    
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');
    
    $page = $dbi->getPage($pagename);
    $current = $page->getCurrentRevision();
    
    $content = $request->getArg('content');
    $editversion = $request->getArg('editversion');
    
    if ( $content === false || $editversion === false )
        BadFormVars($pagename); // noreturn

    if ($page->get('locked') && !$user->isAdmin())
        PageIsLocked($args->pagename); // noreturn.

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
        return displayPage($dbi, $request, 'nochanges');
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
        ConcurrentUpdates($pagename);
    }
    // New contents successfully saved...

    // Clean out archived versions of this page.
    $cleaner = new ArchiveCleaner($GLOBALS['ExpireParams']);
    $cleaner->cleanPageRevisions($page);
    
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
    
    $wrapper = new WikiTemplate('top');
    $wrapper->qreplace('TITLE', sprintf(_("Saved: %s"), $pagename));
    $wrapper->replace('HEADER', fmt("Saved: %s",
                                    $Theme->linkExistingWikiWord($pagename)));
    $wrapper->setPageRevisionTokens($newrevision);

    $template = new WikiTemplate('savepage');
    if (!empty($warnings))
        $template->qreplace('WARNINGS', $warnings);
    $template->replace('CONTENT', do_transform($newrevision->getContent()));

    $wrapper->printExpansion($template);
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
