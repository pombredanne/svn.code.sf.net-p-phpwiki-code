<?php // -*-php-*-
rcs_id('$Id: TitleSearch.php,v 1.9 2002-01-22 06:15:52 carstenklapp Exp $');

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
                     'noheader'		=> false,
                     'info'             => false
                     );
    }
    // info arg now allows multiple columns info=mtime,hits,summary,author,locked,minor

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->titleSearch($query);

        $pagelist = new PageList();

        if ($info)
            foreach (explode(",", $info) as $col)
                $pagelist->insertColumn($col);

        while ($page = $pages->next()) {
            $pagelist->addPage($page);
            $last_name = $page->getName();
        }

        if ($auto_redirect && ($pagelist->getTotal() == 1))
            $request->redirect(WikiURL($last_name));

        if (!$noheader)
            $pagelist->setCaption(fmt("Title search results for '%s'", $s));

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
