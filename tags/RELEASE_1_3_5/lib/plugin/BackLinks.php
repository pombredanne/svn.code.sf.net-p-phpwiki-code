<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.19 2003-01-18 21:19:25 carstenklapp Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 */
require_once('lib/PageList.php');

class WikiPlugin_BackLinks
extends WikiPlugin
{
    function getName() {
        return _("BackLinks");
    }

    function getDescription() {
        return sprintf(_("List all pages which link to %s."), '[pagename]');
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.19 $");
    }

    function getDefaultArguments() {
        return array('exclude'      => '',
                     'include_self' => 0,
                     'noheader'     => 0,
                     'page'         => '[pagename]',
                     'info'         => false
                     );
    }
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);
        if (!$page)
            return '';

        $exclude = $exclude ? explode(",", $exclude) : array();
        if (!$include_self)
            $exclude[] = $page;

        $pagelist = new PageList($info, $exclude);

        $p = $dbi->getPage($page);
        $pagelist->addPages($p->getLinks());

        if (!$noheader) {
            $pagelink = WikiLink($page, 'auto');

            if ($pagelist->isEmpty())
                return HTML::p(fmt("No pages link to %s.", $pagelink));

            if ($pagelist->getTotal() == 1)
                $pagelist->setCaption(fmt("One page links to %s:",
                                          $pagelink));
            else
                $pagelist->setCaption(fmt("%s pages link to %s:",
                                          $pagelist->getTotal(), $pagelink));
        }

        return $pagelist;
    }

};

// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
