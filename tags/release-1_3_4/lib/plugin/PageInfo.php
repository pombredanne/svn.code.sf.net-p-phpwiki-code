<?php // -*-php-*-
rcs_id('$Id: PageInfo.php,v 1.1 2002-10-14 14:43:22 carstenklapp Exp $');
/**
 * ActionPage plugin returns extra information about a page.
 * The plugin just passes a page revision handle to the Template
 * info.tmpl, which does all the real work.
 */
class WikiPlugin_PageInfo
extends WikiPlugin
{
    function getName () {
        return _("PageInfo");
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

        $template = new Template('info', $request,
                                 array('revision' => $revision));
        return $template;
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
