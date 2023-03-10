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

require_once 'lib/plugin/RecentChanges.php';

class WikiPlugin_RecentEdits extends WikiPlugin_RecentChanges
{
    public function getDescription()
    {
        return _("List all recent edits in this wiki.");
    }

    public function getDefaultArguments()
    {
        $args = parent::getDefaultArguments();
        $args['show_minor'] = true;
        $args['show_all'] = true;
        return $args;
    }

    // box is used to display a fixed-width, narrow version with common header.
    // just a numbered list of limit pagenames, without date.
    /**
     * @param string $args
     * @param WikiRequest $request
     * @param string $basepage
     * @return $this|HtmlElement
     */
    public function box($args = '', $request = null, $basepage = '')
    {
        if (!$request) {
            $request =& $GLOBALS['request'];
        }
        if (!isset($args['limit'])) {
            $args['limit'] = 15;
        }
        $args['format'] = 'box';
        $args['show_minor'] = true;
        $args['show_major'] = true;
        $args['show_deleted'] = false;
        $args['show_all'] = true;
        $args['days'] = 90;
        return $this->makeBox(
            WikiLink(__("RecentEdits"), '', _("Recent Edits")),
            $this->format($this->getChanges($request->_dbi, $args), $args)
        );
    }
}
