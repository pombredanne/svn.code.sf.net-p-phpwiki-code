<?php
/**
 * Copyright © 1999,2000,2001,2002,2005 $ThePhpWikiProgrammingTeam
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
 * A simple PageTrail WikiPlugin.
 * Put this at the begin/end of each page to store the trail,
 * or better in a template (body or bottom) to support it for all pages.
 * But Cache should be turned off then.
 *
 * Usage:
 * <<PageTrail>>
 * <<PageTrail numberlinks=5>>
 * <<PageTrail invisible=1>>
 */

if (!defined('PAGETRAIL_ARROW')) {
    define('PAGETRAIL_ARROW', " => ");
}

class WikiPlugin_PageTrail extends WikiPlugin
{
    public $def_numberlinks = 5;

    public function getDescription()
    {
        return _("Display PageTrail.");
    }

    // default values
    public function getDefaultArguments()
    {
        return array('numberlinks' => $this->def_numberlinks,
            'invisible' => false,
            'duplicates' => false,
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
        extract($this->getArgs($argstr, $request));

        if ($numberlinks > 10 || $numberlinks < 0) {
            $numberlinks = $this->def_numberlinks;
        }

        // Get name of the current page we are on
        $thispage = $request->getArg('pagename');
        $pages = $request->session->get("PageTrail");
        if (!is_array($pages)) {
            $pages = array();
        }

        $wikipages = array();
        foreach ($pages as $page) {
            if ($dbi->isWikiPage($page)) {
                $wikipages[] = $page;
            }
        }

        if (!isset($wikipages[0]) or ($duplicates || ($thispage != $wikipages[0]))) {
            array_unshift($wikipages, $thispage);
            $request->session->set("PageTrail", $wikipages);
        }

        $numberlinks = min(count($wikipages), $numberlinks);
        if (!$invisible and $numberlinks) {
            $html = HTML::span(array('class' => 'pagetrail'));
            $html->pushContent(WikiLink($wikipages[$numberlinks - 1], 'auto'));
            for ($i = $numberlinks - 2; $i >= 0; $i--) {
                if (!empty($wikipages[$i])) {
                    $html->pushContent(
                        PAGETRAIL_ARROW,
                        WikiLink($wikipages[$i], 'auto')
                    );
                }
            }
            return $html;
        } else {
            return HTML();
        }
    }
}
