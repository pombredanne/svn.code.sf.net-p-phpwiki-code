<?php
/**
 * Copyright © 2004 $ThePhpWikiProgrammingTeam
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
 * IncludePages: Include a list of multiple pages, based on IncludePage.
 * usage:   <<IncludePages pages=<!plugin-list BackLinks !> >>
 * author:  Reini Urban
 */

include_once 'lib/plugin/IncludePage.php';

class WikiPlugin_IncludePages extends WikiPlugin_IncludePage
{
    public function getDescription()
    {
        return _("Include multiple pages.");
    }

    public function getDefaultArguments()
    {
        return array_merge(
            array('pages' => '', // the pages to include
                                 'exclude' => ''), // the pages to exclude
            WikiPlugin_IncludePage::getDefaultArguments()
        );
    }

    public function getWikiPageLinks($argstr, $basepage)
    {
        $args = $this->getArgs($argstr);
        if (is_string($args['exclude']) and !empty($args['exclude'])) {
            $exclude = explodePageList($args['exclude']);
        } elseif (is_array($args['exclude'])) {
            $exclude = $args['exclude'];
        } else {
            $exclude = array();
        }
        if (is_string($args['pages']) and !empty($args['pages'])) {
            $pages = explodePageList($args['pages']);
        } elseif (is_array($args['pages'])) {
            $pages = $args['pages'];
        } else {
            $pages = array();
        }
        $pages = array_diff($pages, $exclude);
        $links = array();
        global $request;
        $dbi = $request->_dbi;
        foreach ($pages as $page) {
            $page_handle = $dbi->getPage($page);
            $pagelinks = $page_handle->getPageLinks();
            while ($link_handle = $pagelinks->next()) {
                $linkname = $link_handle->getName();
                $links[] = array('linkto' => $linkname);
            }
        }
        return $links;
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

        if (isset($args['limit']) && !limit($args['limit'])) {
            return HTML::p(
                array('class' => "error"),
                _("Illegal “limit” argument: must be an integer or two integers separated by comma")
            );
        }

        $html = HTML();
        if (empty($args['pages'])) {
            return $html;
        }
        $include = new WikiPlugin_IncludePage();

        if (is_string($args['exclude']) and !empty($args['exclude'])) {
            $args['exclude'] = explodePageList($args['exclude']);
            $argstr = preg_replace("/exclude=\S*\s/", "", $argstr);
        } elseif (is_array($args['exclude'])) {
            $argstr = preg_replace("/exclude=<\?plugin-list.*?\>/", "", $argstr);
        }
        if (is_string($args['pages']) and !empty($args['pages'])) {
            $args['pages'] = explodePageList($args['pages']);
            $argstr = preg_replace("/pages=\S*\s/", "", $argstr);
        } elseif (is_array($args['pages'])) {
            $argstr = preg_replace("/pages=<\?plugin-list.*?\>/", "", $argstr);
        }

        // IncludePage plugin has no "pages" argument.
        // Remove it to avoid warning.
        $argstr = preg_replace('/pages=".*?"/', "", $argstr);
        $argstr = preg_replace('/pages=\S*\s/', "", $argstr);
        $argstr = preg_replace('/pages=\S*/', "", $argstr);

        foreach ($args['pages'] as $page) {
            if (empty($args['exclude']) or !in_array($page, $args['exclude'])) {
                $html = HTML($html, $include->run($dbi, "page='$page' " . $argstr, $request, $basepage));
            }
        }
        return $html;
    }
}
