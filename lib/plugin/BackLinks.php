<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.7 2002-01-21 06:55:47 dairiki Exp $');
/**
 */
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

    // Currently only info=false or info=hits works (I don't think
    // anything else would be useful anyway).

    function run($dbi, $argstr, $request) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);
        if (!$page)
            return '';

        $p = $dbi->getPage($page);
        $backlinks = $p->getLinks();

        if ($info)
            $list = $this->_info_listing($backlinks);
        else
            $list = $this->_plain_listing($backlinks);
        
        if ($noheader)
            return $list;
        
        global $Theme;
        $pagelink = $Theme->linkExistingWikiWord($page);
        
        if ($list)
            $head = fmt("These pages link to %s:", $pagelink);
        else
            $head = fmt("No pages link to %s.", $pagelink);

        $head = HTML::p($head);

        return array($head, $list);
    }

    function _plain_listing ($backlinks) {
        extract($this->_args);

        $ul = HTML::ul();
        $n = 0;
        while ($backlink = $backlinks->next()) {
            $name = $backlink->getName();
            if ($exclude && $name == $exclude)
                continue;
            if (!$include_self && $name == $page)
                continue;
            $ul->pushContent(HTML::li(_LinkWikiWord($name)));
            $n++;
        }
        return $n ? $ul : '';
    }

    function _info_listing ($backlinks) {
        extract($this->_args);

        $tab = HTML::table(array('cellpadding' => 0,
                                 'cellspacing' => 1,
                                 'border' => 0));
        $tab->pushContent($this->_tr(HTML::u(_(ucfirst($info))),
                                     HTML::u(_("Page Name"))));
        $n = 0;
        while ($backlink = $backlinks->next()) {
            $name = $backlink->getName();
            if ($exclude && $name == $exclude)
                continue;
            if (!$include_self && $name == $page)
                continue;
            $tab->pushContent($this->_tr($backlink->get($info),
                                         _LinkWikiWord($name)));
            $n++;
        }
        return $n ? HTML::blockquote($tab) : '';
    }
    

    function _tr ($col1, $col2) {
        return HTML::tr(HTML::td(array('align' => 'right'),
                                 $col1, new RawXml('&nbsp;&nbsp;')),
                        HTML::td(new RawXml('&nbsp;&nbsp;'), $col2));
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
