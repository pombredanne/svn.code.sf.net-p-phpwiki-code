<?php // -*-php-*-
rcs_id('$Id: MostPopular.php,v 1.11 2002-01-21 16:31:53 carstenklapp Exp $');
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
        return array('limit'	=> 20,
                     'noheader'	=> 0);
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pages = $dbi->mostPopular($limit);

        $list = new PageList();
        $list->insertColumn(_("Hits"));
        //$list->addcolumn(_("Last Modified"));

        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            if ($hits == 0)
                break;
            $list->addPage($page);
        }
        $pages->free();
        
        if (! $noheader) {
            if ($limit > 0) {
                $list->setCaption(_("The %d most popular pages of this wiki:"));
            } else {
                $list->setCaption(_("Visited pages on this wiki, ordered by popularity:"));
            }
        }
        return $list->getHTML();
        
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
