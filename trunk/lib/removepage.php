<?php
rcs_id('$Id: removepage.php,v 1.5 2002-01-23 05:10:22 dairiki Exp $');
require_once('lib/Template.php');

function RemovePage (&$request) {
    global $Theme;
    $pagename = $request->getArg('pagename');
    $pagelink = $Theme->linkExistingWikiWord($pagename);
    
    if ($request->getArg('verify') != 'okay') {
        $url = WikiURL($pagename, array('action' => 'remove', 'verify' => 'okay'));

        $removeB = $Theme->makeButton(_("Remove the page now"), $url, 'wikiadmin');
        $cancelB = $Theme->makeButton(_("Cancel"), WikiURL($pagename), 'wikiaction');
        
        $html[] = HTML::h2(fmt("You are about to remove '%s' permanently!", $pagelink));
        $html[] =HTML::div(array('class' => 'toolbar'),
                           $removeB,
                           $Theme->getButtonSeparator(),
                           $cancelB);
    }
    else {
        $dbi = $request->getDbh();
        $dbi->deletePage($pagename);
        $html[] = HTML::h2(fmt("Removed page '%s' succesfully.", $pagename));
    }
    echo GeneratePage('MESSAGE', $html, _("Remove page"));
}

RemovePage($request);

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>   
