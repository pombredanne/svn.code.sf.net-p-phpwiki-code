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
 * Redirect to another page or external uri. Kind of PageAlias.
 * Usage:
 * <<RedirectTo href="http://www.internet-technology.de/fourwins_de.htm" >>
 *      or  <<RedirectTo page=AnotherPage >>
 *
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * BUGS/COMMENTS:
 * Todo: fix with USE_PATH_INFO = false
 *
 * This plugin could probably result in a lot of confusion, especially when
 * redirecting to external sites.  (Perhaps it can even be used for dastardly
 * purposes?)  Maybe it should be disabled by default.
 */

class WikiPlugin_RedirectTo extends WikiPlugin
{
    public function getDescription()
    {
        return _("Redirect to another URL or page.");
    }

    public function getDefaultArguments()
    {
        return array('href' => '',
                     'page' => '');
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return HTML|XmlContent
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        $args = $this->getArgs($argstr, $request);

        $href = $args['href'];
        $page = $args['page'];

        if (!$href && !$page) {
            return $this->error(sprintf(_("Both '%s' and '%s' parameters missing."), 'href', 'page'));
        } elseif ($href && $page) {
            return $this->error(sprintf(_("Choose only one of '%s' or '%s' parameters."), 'href', 'page'));
        }

        if ($href) {
            // If URL is urlencoded, decode it.
            if (strpos('%', $href) !== false) {
                $href = urldecode($href);
            }
            $url = strip_tags($href);
            if ($url != $href) { // URL contains tags
                return $this->disabled(_("Illegal characters in external URL."));
            }
            if (!IsSafeURL($url, true)) { // http or https only
                return $this->error(fmt("Malformed URL: “%s”", $url));
            }
            $thispage = $request->getPage();
            if (!$thispage->get('locked')) {
                return $this->disabled(_("Redirect to an external URL is only allowed in locked pages."));
            }
        } elseif ($page) {
            $url = WikiURL($page, array('redirectfrom' => $request->getArg('pagename')));
        }

        if ($page == $request->getArg('pagename')) {
            return $this->error(fmt("Recursive redirect to self: “%s”", $url));
        }

        if ($request->getArg('action') != 'browse') {
            return $this->disabled(_("Plugin not run: not in browse mode"));
        }

        $redirectfrom = $request->getArg('redirectfrom');
        if ($redirectfrom !== false) {
            if ($redirectfrom) {
                return $this->disabled(_("Double redirect not allowed."));
            } else {
                // Got here by following the "Redirected from ..." link
                // on a browse page.
                return $this->disabled(_("Viewing redirecting page."));
            }
        }

        $request->redirect($url);
        return HTML();
    }
}
