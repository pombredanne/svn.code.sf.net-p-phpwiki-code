<?php // -*-php-*-
rcs_id('$Id: LikePages.php,v 1.16 2002-01-30 23:41:54 dairiki Exp $');

require_once('lib/TextSearchQuery.php');
require_once('lib/PageList.php');

/**
 */
class WikiPlugin_LikePages
extends WikiPlugin
{
    function getName() {
        return _("LikePages");
    }
    
    function getDescription() {
        return sprintf(_("List LikePages for %s"), '[pagename]');
    }
    
    function getDefaultArguments() {
        return array('page'	=> '[pagename]',
                     'prefix'	=> false,
                     'suffix'	=> false,
                     'exclude'	=> '',
                     'noheader'	=> false,
                     'info'     => ''
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page) && empty($prefix) && empty($suffix))
            return '';

        
        if ($prefix) {
            $suffix = false;
            $descrip = fmt("Page names with prefix '%s'", $prefix);
        }
        elseif ($suffix) {
            $descrip = fmt("Page names with suffix '%s'", $suffix);
        }
        elseif ($page) {
            $words = preg_split('/[\s:-;.,]+/',
                                split_pagename($page));
            $words = preg_grep('/\S/', $words);
            
            $prefix = reset($words);
            $suffix = end($words);

            $descrip = fmt("These pages share an initial or final title word with '%s'",
                           WikiLink($page, 'auto'));
        }

        // Search for pages containing either the suffix or the prefix.
        $search = $match = array();
        if (!empty($prefix)) {
            $search[] = $this->_quote($prefix);
            $match[]  = '^' . preg_quote($prefix, '/');
        }
        if (!empty($suffix)) {
            $search[] = $this->_quote($suffix);
            $match[]  = preg_quote($suffix, '/') . '$';
        }

        if ($search)
            $query = new TextSearchQuery(join(' OR ', $search));
        else
            $query = new NullTextSearchQuery; // matches nothing
        
        $match_re = '/' . join('|', $match) . '/';

        $pagelist = new PageList;
        $this->_init($page, &$pagelist, $info, $exclude);

        $pages = $dbi->titleSearch($query);

        while ($page = $pages->next()) {
            $name = $page->getName();
            if (!preg_match($match_re, $name))
                continue;

            $pagelist->addPage($page);
        }

        if (!$noheader)
            $pagelist->setCaption($descrip);

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

    function _quote($str) {
        return "'" . str_replace("'", "''", $str) . "'";
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
