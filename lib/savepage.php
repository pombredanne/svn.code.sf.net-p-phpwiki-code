<?php rcs_id('$Id: savepage.php,v 1.17 2001-11-14 21:05:38 dairiki Exp $');
require_once('lib/Template.php');
require_once('lib/transform.php');
require_once('lib/ArchiveCleaner.php');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

// FIXME: some links so that it's easy to get back to someplace useful from these
// error pages.

function ConcurrentUpdates($pagename) {
   /* xgettext only knows about c/c++ line-continuation strings
     is does not know about php's dot operator.
     We want to translate this entire paragraph as one string, of course.
   */
    $html = "<p>";
    $html .= gettext ("PhpWiki is unable to save your changes, because another user edited and saved the page while you were editing the page too. If saving proceeded now changes from the previous author would be lost.");
    $html .= "</p>\n<p>";
    $html .= gettext ("In order to recover from this situation follow these steps:");
    $html .= "\n<ol><li>";
    $html .= gettext ("Use your browser's <b>Back</b> button to go back to the edit page.");
    $html .= "</li>\n<li>";
    $html .= gettext ("Copy your changes to the clipboard or to another temporary place (e.g. text editor).");
    $html .= "</li>\n<li>";
    $html .= gettext ("<b>Reload</b> the page. You should now see the most current version of the page. Your changes are no longer there.");
    $html .= "</li>\n<li>";
    $html .= gettext ("Make changes to the file again. Paste your additions from the clipboard (or text editor).");
    $html .= "</li>\n<li>";
    $html .= gettext ("Press <b>Save</b> again.");
    $html .= "</li></ol></p>\n";
    $html .= QElement('p', gettext ("Sorry for the inconvenience."));

    echo GeneratePage('MESSAGE', $html,
                      sprintf (gettext ("Problem while updating %s"), $pagename));
    ExitWiki();
}

function PageIsLocked($pagename) {
    $html = QElement('p',
                     gettext("This page has been locked by the administrator and cannot be edited."));
    $html .= QElement('p',
                      gettext ("Sorry for the inconvenience."));

    echo GeneratePage('MESSAGE', $html,
                      sprintf (gettext ("Problem while editing %s"), $pagename));
    ExitWiki ("");
}

function NoChangesMade($pagename) {
    $html = QElement('p', gettext ("You have not made any changes."));
    $html .= QElement('p', gettext ("New version not saved."));
    echo GeneratePage('MESSAGE', $html,
                      sprintf(gettext("Edit aborted: %s"), $pagename));
    ExitWiki ("");
}

function BadFormVars($pagename) {
    $html = QElement('p', gettext ("Bad form submission"));
    $html .= QElement('p', gettext ("Required form variables are missing."));
    echo GeneratePage('MESSAGE', $html,
                      sprintf(gettext("Edit aborted: %s"), $pagename));
    ExitWiki ("");
}
    
function savePreview($dbi, $request) {
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');

    $page = $dbi->getPage($pagename);
    $selected = $page->getRevision($version);

    // FIXME: sanity checking about posted variables
    // FIXME: check for simultaneous edits.
    foreach (array('minor_edit', 'convert') as $key)
        $formvars[$key] = $request->getArg($key) ? 'checked' : '';
    foreach (array('content', 'editversion', 'summary', 'pagename', 'version') as $key)
        @$formvars[$key] = htmlspecialchars($request->getArg($key));

    $template = new WikiTemplate('EDITPAGE');
    $template->setPageRevisionTokens($selected);
    $template->replace('FORMVARS', $formvars);
    $template->replace('PREVIEW_CONTENT', do_transform($request->getArg('content')));
    echo $template->getExpansion();
}

function savePage ($dbi, $request) {
    global $user;

    // FIXME: fail if this check fails?
    assert($request->get('REQUEST_METHOD') == 'POST');
    
    if ($request->getArg('preview'))
        return savePreview($dbi, $request);
    
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');

    $page = $dbi->getPage($pagename);
    $current = $page->getCurrentRevision();

    $content = $request->getArg('content');
    $editversion = $request->getArg('editversion');
    
    if ( $content === false || $editversion === false )
        BadFormVars($pagename); // noreturn

    if ($page->get('locked') && !$user->is_admin())
        PageIsLocked($args->pagename); // noreturn.

    $meta['author'] = $user->id();
    $meta['author_id'] = $user->authenticated_id();
    $meta['is_minor_edit'] = (bool) $request->getArg('minor_edit');
    $meta['summary'] = trim($request->getArg('summary'));

    $content = preg_replace('/[ \t\r]+\n/', "\n", chop($content));
    if ($request->getArg('convert'))
        $content = CookSpaces($content);

    if ($content == $current->getPackedContent()) {
        NoChangesMade($pagename); // noreturn
    }

    ////////////////////////////////////////////////////////////////
    //
    // From here on, we're actually saving.
    //
    $newrevision = $page->createRevision($editversion + 1,
                                         $content, $meta,
                                         ExtractWikiPageLinks($content));
    if (!is_object($newrevision)) {
        // Save failed.
        ConcurrentUpdates($pagename);
    }

    // Clean out archived versions of this page.
    $cleaner = new ArchiveCleaner($GLOBALS['ExpireParams']);
    $cleaner->cleanPageRevisions($page);
    
    $warnings = $dbi->GenericWarnings();
    if (empty($warnings)) {
        // Do redirect to browse page.
        // In this case, the user will most likely not see the rest of
        // the HTML we generate (below).
        $request->redirect(WikiURL($pagename, false, 'absolute_url'));
    }

    $html = sprintf(gettext("Thank you for editing %s."),
                    LinkExistingWikiWord($pagename));
    $html .= "<br>\n";
    $html .= gettext ("Your careful attention to detail is much appreciated.");
    $html .= "\n";

    if ($warnings) {
        $html .= Element('p', "<b>Warning!</b> "
                         . htmlspecialchars($warnings)
                         . "<br>\n");
    }

    global $SignatureImg;
    if (!empty($SignatureImg))
        $html .= sprintf("<P><img src=\"%s\"></P>\n", DataURL($SignatureImg));
    
    $html .= "<hr noshade>\n";
    $html .= do_transform($newrevision->getContent());
    echo GeneratePage('BROWSE', $html, $pagename, $newrevision);
}

    
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
