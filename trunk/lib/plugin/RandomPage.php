<?php // -*-php-*-
rcs_id('$Id: RandomPage.php,v 1.3 2002-01-29 20:08:29 carstenklapp Exp $');

require_once('lib/PageList.php');

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
        return array('pages'    => 1,
                     'showname' => false,
                     'info'     => '');
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $allpages = $dbi->getAllPages();

        while ($page = $allpages->next())
            $pagearray[] = $page;

        better_srand(); // Start with a good seed.

        global $Theme;
        if ($pages < 2) {
            $page = $pagearray[array_rand($pagearray)];
            if (($showname == 'true') || ($showname == 1))
                return $Theme->linkExistingWikiWord($page->getName());
            else
                return $Theme->linkExistingWikiWord($page->getName(), _("RandomPage"));
        } else {
            if ($pages > 20)
                $pages = 20;
            $PageList = new PageList();
            if ($info)
                foreach (explode(",", $info) as $col)
                    $PageList->insertColumn($col);
            while ($PageList->getTotal() < $pages) {
                $PageList->addPage($pagearray[array_rand($pagearray)]);
            }
        }
        return $PageList->getContent();
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
