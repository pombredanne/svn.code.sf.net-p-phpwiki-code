<?php // -*-php-*-
rcs_id('$Id: RandomPage.php,v 1.1 2002-01-28 15:54:49 carstenklapp Exp $');

/**
 */
class WikiPlugin_RandomPage
extends WikiPlugin
{
    function getName () {
        return _("RandomPage");
    }

    function getDescription () {
        return _("RandomPage");
    }

    function getDefaultArguments() {
        return array('showname' => false);
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pages = $dbi->getAllPages();

        while ($page = $pages->next())
            $pagearray[] = $page->getname();

        better_srand(); // Start with a good seed.
        $pagename = $pagearray[array_rand($pagearray)];

        global $Theme;
        if ($showname)
            return $Theme->linkExistingWikiWord($pagename);
        else
            return $Theme->linkExistingWikiWord($pagename,_("RandomPage"));
    }
};


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
