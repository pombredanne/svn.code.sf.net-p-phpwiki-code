<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.9 2002-01-21 19:18:16 carstenklapp Exp $');
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

        $pagelist = new PageList();

    // Currently only info="Last Modified" or info=hits works (I don't think
    // anything else would be useful anyway).

        if ($info)
            $pagelist->insertColumn(_($info));

        $n = false;
        while ($backlink = $backlinks->next()) {
            $name = $backlink->getName();
            if ($exclude && $name == $exclude)
                continue;
            if (!$include_self && $name == $page)
                continue;
            $pagelist->addPage($backlink);
            $n = true;
        }

        
        if ($noheader)
            return $pagelist->getContent();

//        global $Theme;
//        $pagelink = $Theme->linkExistingWikiWord($page);
        $pagelink = LinkExistingWikiWord($page);

        if ($n)
            //FIXME: use __sprintf
            //$head = sprintf("These %s pages link to %s:", '%d', $pagelink);
            $head = sprintf("These pages link to %s:", $pagelink);
        else
            $head = sprintf("No pages link to %s.", $pagelink);

//        $head = new RawXML($pagelist->setCaption($head));
//        $head = HTML::p(new RawXML($pagelist->setCaption($head)));
//        $head = HTML::p($pagelist->setCaption($head));
        return array($head, $pagelist->getContent());
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
