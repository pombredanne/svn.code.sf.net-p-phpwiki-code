<?php // -*-php-*-
rcs_id('$Id: RandomPage.php,v 1.4 2002-01-30 22:45:33 carstenklapp Exp $');

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
        return array('pages'        => 1,
                     'showname'     => false,
                     'pagename'     => '[pagename]', // hackish
                     'exclude'      => '',
                     'include_self' => 0, // hackish
                     'info'         => '');
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
            $this->_init($pagename, &$PageList, $info, $exclude, $include_self);

            while ($PageList->getTotal() < $pages) {
                $PageList->addPage($pagearray[array_rand($pagearray)]);
            }
            return $PageList->getContent();
        }
    }

    function _init(&$page, &$pagelist, $info = '', $exclude = '', $include_self = '') {
	if ($info)
            foreach (explode(",", $info) as $col)
                $pagelist->insertColumn($col);

	if ($exclude)
            foreach (explode(",", $exclude) as $excludepage)
                $pagelist->excludePageName($excludepage);
	if (!$include_self)
            $pagelist->excludePageName($page);
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
