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
 * List of changes on all pages which are linked to from this page.
 * This is good usage for an action button, similar to LikePages.
 *
 * DONE: days links requires action=RelatedChanges arg
 */

require_once 'lib/plugin/RecentChanges.php';

class _RelatedChanges_HtmlFormatter extends _RecentChanges_HtmlFormatter
{
    public function description()
    {
        return HTML::p(
            false,
            $this->pre_description(),
            fmt(" (to pages linked from “%s”)", $this->_args['page'])
        );
    }
}

class WikiPlugin_RelatedChanges extends WikiPlugin_RecentChanges
{
    public function getDescription()
    {
        return _("List of changes on all pages which are linked to from this page.");
    }

    public function getDefaultArguments()
    {
        $args = parent::getDefaultArguments();
        $args['page'] = '[pagename]';
        $args['show_minor'] = true;
        $args['show_all'] = true;
        $args['caption'] = _("Related Changes");
        return $args;
    }

    public function getChanges($dbi, $args)
    {
        $changes = $dbi->mostRecent($this->getMostRecentParams($args));

        $show_deleted = $args['show_deleted'];
        if ($show_deleted == 'sometimes') {
            $show_deleted = $args['show_minor'];
        }
        if (!$show_deleted) {
            $changes = new NonDeletedRevisionIterator($changes, !$args['show_all']);
        }

        // sort out pages not linked from our page
        $changes = new RelatedChangesRevisionIterator($changes, $dbi, $args['page']);
        return $changes;
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
        $args['show_minor'] = false;
        $args['show_major'] = true;
        $args['show_deleted'] = false;
        $args['show_all'] = false;
        $args['days'] = 90;
        return $this->makeBox(
            WikiLink(__("RelatedChanges"), '', _("Related Changes")),
            $this->format($this->getChanges($request->_dbi, $args), $args)
        );
    }

    public function format($changes, $args)
    {
        global $WikiTheme;
        $format = $args['format'];

        $fmt_class = $WikiTheme->getFormatter('RelatedChanges', $format);
        if (!$fmt_class) {
            if ($format == 'rss') {
                $fmt_class = '_RecentChanges_RssFormatter';
            } elseif ($format == 'rss2') {
                $fmt_class = '_RecentChanges_Rss2Formatter';
            } elseif ($format == 'rss091') {
                include_once 'lib/RssWriter091.php';
                $fmt_class = '_RecentChanges_RssFormatter091';
            } elseif ($format == 'sidebar') {
                $fmt_class = '_RecentChanges_SideBarFormatter';
            } elseif ($format == 'box') {
                $fmt_class = '_RecentChanges_BoxFormatter';
            } else {
                $fmt_class = '_RelatedChanges_HtmlFormatter';
            }
        }

        $fmt = new $fmt_class($args);
        return $fmt->format($changes);
    }
}

/**
 * list of pages which are linked from the current page.
 * i.e. sort out all non-linked pages.
 */
class RelatedChangesRevisionIterator extends WikiDB_PageRevisionIterator
{
    public function __construct($revisions, &$dbi, $pagename)
    {
        $this->_revisions = $revisions;
        $this->_wikidb = $dbi;
        $page = $dbi->getPage($pagename);
        $links = $page->getLinks();
        $this->_links = array();
        while ($linked_page = $links->next()) {
            $this->_links[$linked_page->_pagename] = 1;
        }
        $links->free();
    }

    public function next()
    {
        while (($rev = $this->_revisions->next())) {
            if (isset($this->_links[$rev->_pagename])) {
                return $rev;
            }
        }
        $this->free();
        return false;
    }
}
