<?php
rcs_id('$Id: editpage.php,v 1.21 2002-01-13 20:41:26 carstenklapp Exp $');

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
        // Perhaps this can be worked into display.php. It would be nice if:
        // 'Note: You are viewing an old revision of this page. "View the current version".'
        // would link to "View source of the current version".
        // Also the <h1> is hard-coded in brwose.html / editpage.html
        // so we can't get a nice title like "Page source for %s".
        if ($version) {
            $link = QElement('a',
                             array('href' =>
                                   WikiURL($pagename,
                                           array('version' => $version))),$pagename);
        } else {
            $link = LinkExistingWikiWord($pagename);
        }
        $html = QElement('p');
        $html .= QElement('strong', _("Note:")) . " ";
        $html .= sprintf(_("%s has been locked by the administrator and cannot be edited."), $link);
        $html .= "\n";

        $template = new WikiTemplate('BROWSE');
        $template->replace('TITLE', sprintf(_("Page source for %s"), $pagename));
        $template->replace('EDIT_FAIL_MESSAGES', $html
                           . QElement('hr', array('noshade' => 'noshade'))
                           . "\n");
        $prefs = $user->getPreferences();
        $template->replace('CONTENT', 
                           Element('p',
                                   QElement('textarea',
                                            array('class'  => 'wikiedit',
                                                  'rows'   => $prefs['edit_area.height'],
                                                  'cols'   => $prefs['edit_area.width'],
                                                  'wrap'   => 'virtual',
                                                  'readonly' => true),
                                            $selected->getPackedContent())));
        $template->setPageRevisionTokens($selected);

        require_once("lib/display.php");
        $template->qreplace('PAGE_DESCRIPTION', GleanDescription($selected));
        echo $template->getExpansion();
        flush();
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
