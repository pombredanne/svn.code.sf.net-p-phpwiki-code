<?php
rcs_id('$Id: editpage.php,v 1.26 2002-01-21 06:55:47 dairiki Exp $');

require_once('lib/transform.php');
require_once('lib/Template.php');

function editPage($dbi, $request, $do_preview = false) {
    // editpage relies on $pagename, $version
    $pagename = $request->getArg('pagename');
    $version  = $request->getArg('version');
    
    $page    = $dbi->getPage($pagename);
    $current = $page->getCurrentRevision();

    if ($version === false) {
        $selected = $current;
    }
    else {
        $selected = $page->getRevision($version);
        if (!$selected)
            NoSuchRevision($page, $version); // noreturn
    }

    global $user, $Theme;               // FIXME: make this non-global.
    $pagelink = $Theme->linkExistingWikiWord($pagename, '', $version);

    $wrapper = new WikiTemplate('top');
    $wrapper->setPageRevisionTokens($selected);

    if ($page->get('locked') && !$user->isAdmin()) {
        $wrapper->qreplace('TITLE', sprintf(_("Page source for %s"), $pagename));
        $wrapper->replace('HEADER', fmt("View Source: %s", $pagelink));
        $template = new WikiTemplate('viewsource');
        $do_preview = false;
    }
    else {
        $wrapper->qreplace('TITLE', sprintf(_("Edit: %s"), split_pagename($pagename)));
        $wrapper->replace('HEADER', fmt("Edit: %s", $pagelink));
        $template = new WikiTemplate('editpage');
    }

    if ($do_preview) {
        foreach (array('minor_edit', 'convert') as $key)
            $formvars[$key] = (bool) $request->getArg($key);
        foreach (array('content', 'editversion', 'summary', 'pagename',
                       'version') as $key)
            @$formvars[$key] = htmlspecialchars($request->getArg($key));

        $template->replace('PREVIEW_CONTENT',
                           do_transform($request->getArg('content')));
    }
    else {
        $age = time() - $current->get('mtime');
        $minor_edit = ( $age < MINOR_EDIT_TIMEOUT && $current->get('author') == $user->getId() );

        $formvars = array('content'     => $selected->getPackedContent(),
                          'minor_edit'  => $minor_edit,
                          'version'     => $selected->getVersion(),
                          'editversion' => $current->getVersion(),
                          'summary'     => '',
                          'convert'     => '',
                          'pagename'    => $pagename);
    }

    $template->replace('FORMVARS', $formvars);
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
