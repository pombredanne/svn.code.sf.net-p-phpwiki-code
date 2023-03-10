<?php
/*
 * Copyright © 2002 $ThePhpWikiProgrammingTeam
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
 * ListSubpages:  Lists the names of all SubPages of the current page.
 *                Based on UnfoldSubpages.
 * Usage:   <<ListSubpages noheader=1 info=pagename,hits,mtime >>
 */

require_once 'lib/PageList.php';

class WikiPlugin_ListSubpages extends WikiPlugin
{
    public function getDescription()
    {
        return _("Lists the names of all SubPages of the current page.");
    }

    public function getDefaultArguments()
    {
        return array_merge(
            PageList::supportedArgs(),
            array('noheader' => false, // no header
                'basepage' => false, // subpages of which page, default: current
                'maxpages' => 0, // maximum number of pages to include, change that to limit
                'exclude'  => '',
                'info' => ''
            )
        );
    }

    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor,count
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

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

        if ($args['basepage']) {
            $pagename = $args['basepage'];
        } else {
            $pagename = $request->getArg('pagename');
        }

        // FIXME: explodePageList from stdlib doesn't seem to work as
        // expected when there are no subpages. (see also
        // UnfoldSubPages plugin)
        $subpages = explodePageList($pagename . '/' . '*');
        if (!$subpages) {
            return HTML::p(
                array('class' => 'warning'),
                sprintf(_("%s has no subpages defined."), $pagename)
            );
        }
        extract($args);

        if (!is_bool($noheader)) {
            if (($noheader == '0') || ($noheader == 'false')) {
                $noheader = false;
            } elseif (($noheader == '1') || ($noheader == 'true')) {
                $noheader = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noheader"));
            }
        }

        $content = HTML();
        //$subpages = array_reverse($subpages); // TODO: why?
        if ($maxpages) {
            $subpages = array_slice($subpages, 0, $maxpages);
        }

        $descrip = fmt(
            "SubPages of %s:",
            WikiLink($pagename, 'auto')
        );
        if ($info) {
            $info = explode(",", $info);
            if (in_array('count', $info)) {
                $args['types']['count'] = new _PageList_Column_ListSubpages_count('count', _("#"), 'center');
            }
        }
        $pagelist = new PageList($info, $exclude, $args);
        if (!$noheader) {
            $pagelist->setCaption($descrip);
        }

        foreach ($subpages as $page) {
            // A page cannot include itself. Avoid doublettes.
            static $included_pages = array();
            if (in_array($page, $included_pages)) {
                $content->pushContent(HTML::p(sprintf(
                    _("Recursive inclusion of page %s ignored"),
                    $page
                )));
                continue;
            }
            array_push($included_pages, $page);
            $pagelist->addPage($page);

            array_pop($included_pages);
        }
        $content->pushContent($pagelist);
        return $content;
    }
}

// how many backlinks for this subpage
class _PageList_Column_ListSubpages_count extends _PageList_Column
{
    public function _getValue($page_handle, $revision_handle)
    {
        $iter = $page_handle->getBackLinks();
        return $iter->count();
    }
}
