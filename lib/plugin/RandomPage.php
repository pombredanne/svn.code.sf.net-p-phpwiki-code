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

require_once 'lib/PageList.php';

/**
 * With 1.3.11 the "pages" argument was renamed to "numpages".
 * action=upgrade should deal with pages containing RandomPage modified earlier than 2005-01-24
 */

class WikiPlugin_RandomPage extends WikiPlugin
{
    public function getDescription()
    {
        return _("Display a list of randomly chosen pages or redirects to a random page.");
    }

    public function getDefaultArguments()
    {
        return array_merge(
            PageList::supportedArgs(),
            array('numpages' => 20, // was pages
                'pages' => false, // deprecated
                'redirect' => false,
                'hidename' => false, // only for numpages=1
                'exclude' => $this->default_exclude(),
                'info' => '')
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
        extract($args);

        if (!is_bool($redirect)) {
            if (($redirect == '0') || ($redirect == 'false')) {
                $redirect = false;
            } elseif (($redirect == '1') || ($redirect == 'true')) {
                $redirect = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "redirect"));
            }
        }

        if (!is_bool($hidename)) {
            if (($hidename == '0') || ($hidename == 'false')) {
                $hidename = false;
            } elseif (($hidename == '1') || ($hidename == 'true')) {
                $hidename = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "hidename"));
            }
        }

        // Redirect would break HTML dump
        if ($request->getArg('action') != 'browse') {
            return $this->disabled(_("Plugin not run: not in browse mode"));
        }

        // fix deprecated arg
        if (is_integer($pages)) {
            $numpages = $pages;
        // fix new pages handling in arg preprozessor.
        } elseif (is_array($pages)) {
            $numpages = (int)$pages[0];
            if ($numpages > 0 and !$dbi->isWikiPage($numpages)) {
                $pages = false;
            } else {
                $numpages = 1;
            }
        }

        $allpages = $dbi->getAllPages(false, $sortby, $limit, $exclude);
        $pagearray = $allpages->asArray();

        if (($numpages == 1) && $pagearray) {
            $page = $pagearray[array_rand($pagearray)];
            $pagename = $page->getName();
            if ($redirect) {
                $request->redirect(WikiURL($pagename, array(), 'absurl'));
            } // noreturn
            if ($hidename) {
                return WikiLink($pagename, false, _("RandomPage"));
            } else {
                return WikiLink($pagename);
            }
        }

        $numpages = min(max(1, (int)$numpages), 20, count($pagearray));
        $pagelist = new PageList($info, $exclude, $args);
        $shuffle = array_rand($pagearray, $numpages);
        if (is_array($shuffle)) {
            foreach ($shuffle as $i) {
                if (isset($pagearray[$i])) {
                    $pagelist->addPage($pagearray[$i]);
                }
            }
        } else { // if $numpages = 1
            if (isset($pagearray[$shuffle])) {
                $pagelist->addPage($pagearray[$shuffle]);
            }
        }
        return $pagelist;
    }

    private function default_exclude()
    {
        // Some useful default pages to exclude.
        $default_exclude = 'RandomPage,HomePage';
        foreach (explode(",", $default_exclude) as $e) {
            $exclude[] = __($e);
        }
        return implode(",", $exclude);
    }
}
