<?php
/**
 * Copyright © 2004 $ThePhpWikiProgrammingTeam
 * Copyright © 2009 Marc-Etienne Vargenau, Alcatel-Lucent
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
 * Don't cache the following page. Mostly used for plugins, which
 * display dynamic content.
 *
 * Usage:
 *   <<NoCache >>
 * or to delete the whole cache for this page:
 *   <<NoCache nocache||=purge >>
 *
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 */

class WikiPlugin_NoCache extends WikiPlugin
{
    public function getDescription()
    {
        return _("Don't cache this page.");
    }

    public function getDefaultArguments()
    {
        return array('nocache' => 1);
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
        // works regardless of WIKIDB_NOCACHE_MARKUP
        // if WIKIDB_NOCACHE_MARKUP is false it doesn't hurt
        $request->setArg('nocache', $args['nocache']);
        return HTML::raw('');
    }
}
