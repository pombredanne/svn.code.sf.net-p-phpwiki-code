<?php // -*-php-*-
rcs_id('$Id: TitleSearch.php,v 1.12 2002-01-30 22:47:31 carstenklapp Exp $');

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
                     'exclude'          => '',
                     'include_self'     => 1,
                     'pagename'         => '[pagename]', // hackish
                     'info'             => false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->titleSearch($query);

        $pagelist = new PageList();
        $this->_init($pagename, &$pagelist, $info, $exclude, $include_self);

        while ($page = $pages->next()) {
            $pagelist->addPage($page);
            // if (!$pagelist->page_excluded($page->getName())); // not necessary
            $last_name = $page->getName();
        }

        if ($auto_redirect && ($pagelist->getTotal() == 1))
            $request->redirect(WikiURL($last_name));

        if (!$noheader)
            $pagelist->setCaption(fmt("Title search results for '%s'", $s));

        return $pagelist;
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
