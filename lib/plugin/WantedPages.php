<?php
/**
 * Copyright © 2002, 2004 $ThePhpWikiProgrammingTeam
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

/**
 * A plugin which returns a list of referenced pages which do not exist yet.
 * All empty pages which are linked from any page - with an ending question mark,
 * or for just a single page, when the page argument is present.
 *
 * TODO: sort pagename col: disable backend fallback
 */

include_once 'lib/PageList.php';

class WikiPlugin_WantedPages extends WikiPlugin
{
    public $_wpagelist;

    public function getDescription()
    {
        return _("List referenced page names which do not exist yet.");
    }

    public function getDefaultArguments()
    {
        return array_merge(
            PageList::supportedArgs(),
            array('page' => '', // just for a single page.
                  'withlinks' => false,
                  'noheader' => false,
                  'exclude_from' => __("InterWikiMap"),
                  'limit' => '100')
        );
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        $args = $this->getArgs($argstr, $request);

        if (isset($args['limit']) && !is_limit($args['limit'])) {
            return HTML::p(
                array('class' => "error"),
                _("Illegal “limit” argument: must be an integer or two integers separated by comma")
            );
        }

        if (!empty($args['exclude_from'])) {
            $args['exclude_from'] = is_string($args['exclude_from'])
                ? explodePageList($args['exclude_from'])
                : $args['exclude_from'];
        } // <! plugin-list !>

        extract($args);

        if (!is_bool($withlinks)) {
            if (($withlinks == '0') || ($withlinks == 'false')) {
                $withlinks = false;
            } elseif (($withlinks == '1') || ($withlinks == 'true')) {
                $withlinks = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "withlinks"));
            }
        }

        if (!is_bool($noheader)) {
            if (($noheader == '0') || ($noheader == 'false')) {
                $noheader = false;
            } elseif (($noheader == '1') || ($noheader == 'true')) {
                $noheader = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noheader"));
            }
        }

        // There's probably a more memory-efficient way to do this (eg
        // a tailored SQL query via the backend, but this gets the job
        // done.
        // TODO: Move this to backend/dumb/WantedPagesIter.php

        if (!$page and $withlinks) {
            $GLOBALS['WikiTheme']->addPageListColumn(
                array('wanted' => array('_PageList_Column_WantedPages_wanted', 'custom:wanted', _("Wanted From"), 'left'))
            );
            $info = "pagename,wanted";
        } elseif ($page) {
            //only get WantedPages links for one page
            $info = "";
        } else {
            // just link to links
            $GLOBALS['WikiTheme']->addPageListColumn(
                array('links' => array('_PageList_Column_WantedPages_links', 'custom:links', _("Links"), 'left'))
            );
            $info = "pagename,links";
        }
        $pagelist = new PageList($info, $exclude, $args); // search button?
        $pagelist->_wpagelist = array();

        if (!$page) {
            list($offset, $maxcount) = $pagelist->limit($limit);
            $wanted_iter = $dbi->wantedPages($exclude, $sortby, $limit);
            while ($row = $wanted_iter->next()) {
                $wantedfrom = $row['wantedfrom'];
                $wanted = $row['pagename'];
                if (!in_array($wantedfrom, $exclude_from)) {
                    // ignore duplicates:
                    if (empty($pagelist->_wpagelist[$wanted])) {
                        $pagelist->addPage($wanted);
                    }
                    if (!isset($pagelist->_wpagelist[$wanted])) {
                        $pagelist->_wpagelist[$wanted][] = $wantedfrom;
                    } elseif (!in_array($wantedfrom, $pagelist->_wpagelist[$wanted])) {
                        $pagelist->_wpagelist[$wanted][] = $wantedfrom;
                    }
                }
            }
            $wanted_iter->free();
            unset($wanted_iter);
            // update limit, but it's still a hack.
            $pagelist->_options['limit'] = "$offset," . min($pagelist->getTotal(), $maxcount);
        } elseif ($dbi->isWikiPage($page)) {
            //only get WantedPages links for one page
            $page_handle = $dbi->getPage($page);
            $links = $page_handle->getPageLinks(true); // include_empty
            while ($link_handle = $links->next()) {
                $linkname = $link_handle->getName();
                if (!$dbi->isWikiPage($linkname)) {
                    $pagelist->addPage($linkname);
                    //if (!array_key_exists($linkname, $this->_wpagelist))
                    $pagelist->_wpagelist[$linkname][] = 1;
                }
            }
        }
        /*
        if ($sortby) {
            ksort($this->_wpagelist);
            arsort($this->_wpagelist);
        }*/
        if (!$noheader) {
            if ($page) {
                $pagelist->setCaption(sprintf(_("Wanted Pages for %s:"), $page));
            } else {
                $pagelist->setCaption(_("Wanted Pages in this wiki:"));
            }
        }
        // reference obviously doesn't work, so force an update to add _wpagelist to parentobj
        if (isset($pagelist->_columns[1])
            and in_array($pagelist->_columns[1]->_field, array('wanted', 'links'))
        ) {
            $pagelist->_columns[1]->parentobj =& $pagelist;
        }
        return $pagelist;
    }
}

// which links to the missing page
class _PageList_Column_WantedPages_wanted extends _PageList_Column
{
    public function __construct(&$params)
    {
        $this->parentobj =& $params[3];
        parent::__construct($params[0], $params[1], $params[2]);
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $html = false;
        $pagename = $page_handle->getName();
        foreach ($this->parentobj->_wpagelist[$pagename] as $page) {
            if ($html) {
                $html->pushContent(', ', WikiLink($page));
            } else {
                $html = HTML(WikiLink($page));
            }
        }
        return $html;
    }
}

/*
 * List of Links and link to ListLinks
 */
class _PageList_Column_WantedPages_links extends _PageList_Column
{
    public function __construct(&$params)
    {
        $this->parentobj =& $params[3];
        parent::__construct($params[0], $params[1], $params[2]);
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $pagename = $page_handle->getName();
        $count = count($this->parentobj->_wpagelist[$pagename]);
        if ($count == 1) {
            return LinkURL(WikiURL($pagename, array('action' => 'BackLinks')), _("(1 Link)"));
        } else {
            return LinkURL(
                WikiURL($pagename, array('action' => 'BackLinks')),
                fmt("(%d Links)", $count)
            );
        }
    }
}
