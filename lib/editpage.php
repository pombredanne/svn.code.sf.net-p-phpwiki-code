<?php
rcs_id('$Id: editpage.php,v 1.31 2002-01-26 01:51:13 dairiki Exp $');

require_once('lib/transform.php');
require_once('lib/Template.php');

function editPage(&$request, $do_preview = false) {
    // editpage relies on $pagename, $version
    $pagename = $request->getArg('pagename');
    $version  = $request->getArg('version');
    
    $page    = $request->getPage();
    $current = $page->getCurrentRevision();

    if ($version === false) {
        $selected = $current;
    }
    else {
        $selected = $page->getRevision($version);
        if (!$selected)
            NoSuchRevision($request, $page, $version); // noreturn
    }

    global $Theme;
    $user = $request->getUser();
    $pagelink = $Theme->LinkExistingWikiWord($pagename, '', $version);

    if ($page->get('locked') && !$user->isAdmin()) {
        $title = fmt("View Source: %s", $pagelink);
        $template = Template('viewsource');
        $do_preview = false;
    }
    else {
        $title = fmt("Edit: %s", $pagelink);
        $template = Template('editpage');
    }

    if ($do_preview) {
        foreach (array('minor_edit', 'convert') as $key)
            $formvars[$key] = (bool) $request->getArg($key);
        foreach (array('content', 'editversion', 'summary', 'pagename',
                       'version', 'markup') as $key)
            $formvars[$key] = (string) $request->getArg($key);

        if ($formvars['markup'] == 'new') {
            include_once('lib/BlockParser.php');
            $trfm = 'NewTransform';
        }
        else {
            $trfm = 'do_transform';
        }
        $template->replace('PREVIEW_CONTENT', $trfm($request->getArg('content')));
    }
    else {
        $age = time() - $current->get('mtime');
        $minor_edit = ( $age < MINOR_EDIT_TIMEOUT && $current->get('author') == $user->getId() );
        
        $formvars = array('content'     => $selected->getPackedContent(),
                          'minor_edit'  => $minor_edit,
                          'version'     => $selected->getVersion(),
                          'editversion' => $current->getVersion(),
                          'markup'	=> $current->get('markup'),
                          'summary'     => '',
                          'convert'     => '',
                          'pagename'    => $pagename);
    }

    $template->replace('FORMVARS', $formvars);
    GeneratePage($template, $title, $selected);
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
