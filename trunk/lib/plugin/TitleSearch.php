<?php // -*-php-*-
rcs_id('$Id: TitleSearch.php,v 1.6 2002-01-21 19:14:30 carstenklapp Exp $');

require_once('lib/TextSearchQuery.php');
require_once('lib/PageList.php');

/**
 */
class WikiPlugin_TitleSearch
extends WikiPlugin
{
    function getName () {
        return _("TitleSearch");
    }

    function getDescription () {
        return _("Title Search");
    }
    
    function getDefaultArguments() {
        return array('s'		=> false,
                     'auto_redirect'	=> false,
                     'noheader'		=> false);
    }

    function run($dbi, $argstr, $request) {
        global $Theme;
        
        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->titleSearch($query);

        $pagelist = new PageList();
//        $pagelist->insertColumn(_("Hits"));
//        $pagelist->addColumn(_("Last Modified"));

        while ($page = $pages->next()) {
            $pagelist->addPage($page);
            $last_name = $page->getName();
        }

        if ($auto_redirect && ($pagelist->getTotal() == 1))
            $request->redirect(WikiURL($last_name));
        if ($pagelist->getTotal() == 0)
            $list = HTML::blockquote(_("<no matches>"));
        if ($noheader)
            return $pagelist->getContent();
        
        return array(HTML::p(fmt("Title search results for '%s'", $s)),
                     $pagelist->getContent());
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
