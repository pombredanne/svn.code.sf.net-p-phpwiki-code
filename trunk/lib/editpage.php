<?php
rcs_id('$Id: editpage.php,v 1.20 2002-01-09 18:48:03 carstenklapp Exp $');

require_once('lib/transform.php');
require_once('lib/Template.php');

function editPage($dbi, $request) {
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

    global $user;               // FIXME: make this non-global.
    if ($page->get('locked') && !$user->is_admin()) {

        $html = QElement('p');
        $html .= QElement('strong', _("Note:")) . " ";
        $html .= _("This page has been locked by the administrator and cannot be edited.");
        $html .= "\n";
        //$html .= QElement('p', _("Sorry for the inconvenience.")) . "\n";

        //echo GeneratePage('MESSAGE', $html,
        //                  sprintf(_("Problem while editing %s"),
        //                          $request->getArg('pagename')),
        //                  $selected);

        //ExitWiki ("");

        // Page locked.
        // (This is a bit kludgy...)

        // This renders the page but the title and the links at the
        // bottom of the page don't draw:
        //
        // Fatal error: Call to a member function on a non-object
        //
        // FIXME: The ViewSource (ViewMarkup) plugin should be worked
        //        into here somehow.

        $template = new WikiTemplate('BROWSE');
        $template->replace('TITLE', $pagename);
        $template->replace('EDIT_FAIL_MESSAGES', $html
                           . QElement('hr', array('noshade' => 'noshade'))
                           . "\n");
	$template->replace('CONTENT', do_transform($selected->getContent()));
        echo $template->getExpansion();
        ExitWiki ("");
    }


    $age = time() - $current->get('mtime');
    $minor_edit = ( $age < MINOR_EDIT_TIMEOUT && $current->get('author') == $user->id() );

    $formvars = array('content'     => htmlspecialchars($selected->getPackedContent()),
                      'minor_edit'  => $minor_edit ? 'checked' : '',
                      'version'     => $selected->getVersion(),
                      'editversion' => $current->getVersion(),
                      'summary'     => '',
                      'convert'     => '',
                      'pagename'    => htmlspecialchars($pagename));

    $template = new WikiTemplate('EDITPAGE');
    $template->setPageRevisionTokens($selected);
    $template->replace('FORMVARS', $formvars);
    echo $template->getExpansion();
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
