<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.11 2002-01-22 05:06:50 dairiki Exp $');
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
                     'page'		=> false,
                     'info'		=> false);
    }

    function run($dbi, $argstr, $request) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);
        if (!$page)
            return '';

        $p = $dbi->getPage($page);
        $backlinks = $p->getLinks();

        $pagelist = new PageList;


        // Currently only info="Last Modified" or info=hits works (I
        // don't think anything else would be useful anyway).

        if ($info)
            $pagelist->insertColumn(_($info));

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
