<?php // -*-php-*-
rcs_id('$Id: Info.php,v 1.1 2002-02-21 03:12:37 carstenklapp Exp $');
/**
 *
 * Sorry, this isn't finished yet.
 *
 * Fatal error: Call to a member function on a non-object in
 * /Library/WebServer/Documents/phpwiki/lib/Template.php(116) : eval()'d code on line 5
 *
 * This looks like a situation similar to the Template problem of DumpHtmlToDir() in loadsave.php.
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
            $revision = $page->getRevision($version);
            if (!($new = $page->getRevision($version)))
                NoSuchRevision($request, $page, $version);
        }
        else {
            $revision = $page->getCurrentRevision();
        }

        global $Theme;
        $pagetitle = HTML(fmt("%s: %s", _("Info"),
                              $Theme->linkExistingWikiWord($pagename, false, $revision)));

        //$pagetitle->addTooltip(sprintf(_("Return to %s"), $pagename));
    
        $t = new Template('info', $request);
        return $t;

//The rest here is just feeble experimentation, to try to give Template what it needs to allow it to be called from here.

//        return $t->asXML();
//        return $t->printXML();

//        $html = Template('browse', array('CONTENT' => $t));
//        GeneratePage($t, $pagetitle, $revision);


//        $html = Template('browse', array('CONTENT' => $t));

//          return $html;
//        return $html->asXML();
//        return $html->printXML();

//        GeneratePage('info', $pagetitle, $version);
//        GeneratePage($html, $pagetitle, $version);
//        GeneratePage($html, $pagetitle, $revision);
//        flush();
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
