<?php // -*-php-*-
rcs_id('$Id: AllPages.php,v 1.7 2002-01-30 22:47:30 carstenklapp Exp $');

require_once('lib/PageList.php');

/**
 */
class WikiPlugin_AllPages
extends WikiPlugin
{
    function getName () {
        return _("AllPages");
    }

    function getDescription () {
        return _("All Pages");
    }
    
    function getDefaultArguments() {
        return array('noheader'	     => false,
		     'include_empty' => false,
		     'pagename'      => '[pagename]', // hackish
		     'exclude'       => '',
		     'include_self'  => 1, // hackish
		     'info'          => ''
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pagelist = new PageList();
        $this->_init($pagename, &$pagelist, $info, $exclude, $include_self);

        if (!$noheader)
            $pagelist->setCaption(_("Pages in this wiki (%d total):"));

        $pages = $dbi->getAllPages($include_empty);

        while ($page = $pages->next())
            $pagelist->addPage($page);

        return $pagelist;
    }

    function _init($page, &$pagelist, $info = '', $exclude = '', $include_self = '') {
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
