<?php
rcs_id('$Id: removepage.php,v 1.9 2002-01-29 01:17:50 carstenklapp Exp $');
require_once('lib/Template.php');

function RemovePage (&$request) {
    global $Theme;

    $pagename = $request->getArg('pagename');

    $pagelink = $Theme->linkExistingWikiWord($pagename);
    $page = $request->getPage();
    $rev = $page->getCurrentRevision();
    $version = $rev->getVersion();

    if ($request->getArg('cancel')) {
        $request->redirect(WikiURL($pagename));
        // The user probably doesn't see the rest of this.
        $html = HTML(HTML::h2(_("Request Cancelled!")),
                     HTML::p(fmt("Return to %s.", $pagelink)));
    }

    
    if (!$request->isPost() || !$request->getArg('verify')) {
        $url = WikiURL($pagename, array('action' => 'remove', 'verify' => 'okay'));

        $removeB = $Theme->makeSubmitButton(_("Remove the page now"), 'verify', 'wikiadmin');
        $cancelB = $Theme->makeSubmitButton(_("Cancel"), 'cancel', 'wikiaction');
        
        $html = HTML(HTML::h2(fmt("You are about to remove '%s' permanently!", $pagelink)),
                     HTML::form(array('method' => 'post',
                                      'action' => WikiURL($pagename)),
                                HTML::input(array('type' => 'hidden',
                                                  'name' => 'currentversion',
                                                  'value' => $version)),
                                HTML::input(array('type' => 'hidden',
                                                  'name' => 'action',
                                                  'value' => 'remove')),
                                HTML::div(array('class' => 'toolbar'),
                                          $removeB,
                                          $Theme->getButtonSeparator(),
                                          $cancelB)));
    }
    elseif ($request->getArg('currentversion') != $version) {
        $html = HTML(HTML::h2(_("Someone has edited the page!")),
                     HTML::p(fmt("Since you started the deletion process, someone has saved a new version of %s.  Please check to make sure you still want to permanently remove the page from the database.", $pagelink)));
    }
    else {
        // Real delete.
        $dbi = $request->getDbh();
        $dbi->deletePage($pagename);
        $html = HTML(HTML::h2(fmt("Removed page '%s' succesfully.", $pagename)));
    }

    GeneratePage($html, _("Remove page"));
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
