<?php // -*-php-*-
rcs_id('$Id: Info.php,v 1.2 2002-02-21 03:24:19 dairiki Exp $');
/**
 *
 * Sorry, this isn't finished yet.
 *
 *
 */
class WikiPlugin_Info
extends WikiPlugin
{
    function getName () {
        return _("Info");
    }

    function getDescription () {
        return sprintf(_("Show extra page Info and statistics for %s"), '[pagename]');
    }

    function getDefaultArguments() {
        return array('page' => '[pagename]');
    }

    function run ($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);

        $pagename = $page;

        $page = $request->getPage();
    
        if (!empty($version)) {
            if (!($revision = $page->getRevision($version)))
                NoSuchRevision($request, $page, $version);
        }
        else {
            $revision = $page->getCurrentRevision();
        }

        global $Theme;
        $pagetitle = HTML(fmt("%s: %s", _("Info"),
                              $Theme->linkExistingWikiWord($pagename, false, $revision)));

        //$pagetitle->addTooltip(sprintf(_("Return to %s"), $pagename));
        $t = new Template('info', $request,
                          array('revision' => $revision));
        return $t;
    }
};


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
