<?php // -*-php-*-
rcs_id('$Id: MostPopular.php,v 1.17 2002-01-30 18:29:10 carstenklapp Exp $');
/**
 */

require_once('lib/PageList.php');

class WikiPlugin_MostPopular
extends WikiPlugin
{
    function getName () {
        return _("MostPopular");
    }

    function getDescription () {
        return _("List the most popular pages");
    }

    function getDefaultArguments() {
        return array('pagename'	    => '[pagename]', // hackish
                     'exclude'      => '',
                     'include_self' => 1, // hackish
                     'limit'        => 20,
                     'noheader'	    => 0,
                     'info'         => false
                    );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pagelist = new PageList();

        if ($info)
            foreach (explode(",", $info) as $col)
                $pagelist->insertColumn($col);

        if (!$include_self)
                $pagelist->excludePageName($pagename); // hackish
        if ($exclude)
            foreach (explode(",", $exclude) as $excludepage)
                $pagelist->excludePageName($excludepage);

        $pagelist->insertColumn('hits');

        $pages = $dbi->mostPopular($limit);

        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            if ($hits == 0)
                break;
            $pagelist->addPage($page);
        }
        $pages->free();
        
        if (! $noheader) {
            if ($limit > 0) {
                $pagelist->setCaption(_("The %d most popular pages of this wiki:"));
            } else {
                $pagelist->setCaption(_("Visited pages on this wiki, ordered by popularity:"));
            }
        }

        return $pagelist;
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
