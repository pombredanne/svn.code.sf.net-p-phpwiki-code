<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.14 2002-01-28 01:01:27 dairiki Exp $');
/**
 */

require_once('lib/PageList.php');

class WikiPlugin_BackLinks
extends WikiPlugin
{
    function getName () {
        return _("BackLinks");
    }

    function getDescription () {
        return sprintf(_("Get BackLinks for %s"),'[pagename]');
    }
  
    function getDefaultArguments() {
        // FIXME: how to exclude multiple pages?
        return array('exclude'		=> '',
                     'include_self'	=> 0,
                     'noheader'		=> 0,
                     'page'		=> '[pagename]',
                     'info'		=> false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor

    function run($dbi, $argstr, $request) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);
        if (!$page)
            return '';

        $p = $dbi->getPage($page);
        $backlinks = $p->getLinks();

        $pagelist = new PageList;

        if ($info)
            foreach (explode(",", $info) as $col)
                $pagelist->insertColumn($col);

        while ($backlink = $backlinks->next()) {
            $name = $backlink->getName();
            if ($exclude && $name == $exclude)
                continue;
            if (!$include_self && $name == $page)
                continue;

            $pagelist->addPage($backlink);
        }

        if (!$noheader) {
            $pagelink = LinkWikiWord($page);
            
            if ($pagelist->isEmpty())
                return HTML::p(fmt("No pages link to %s.", $pagelink));

            $pagelist->setCaption(fmt("%d pages link to %s:",
                                      $pagelist->getTotal(), $pagelink));
            $pagelist->setMessageIfEmpty('');
        }

        return $pagelist;
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
        
?>
