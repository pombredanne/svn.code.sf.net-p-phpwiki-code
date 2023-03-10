<?php
/**
 * Copyright © 1999-2002,2004,2005,2007,2009 $ThePhpWikiProgrammingTeam
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

require_once 'lib/TextSearchQuery.php';
require_once 'lib/PageList.php';

/**
 * Case insensitive fulltext search
 * Options: case_exact, regex, hilight
 *          Stoplist
 *
 * See also:
 *   Hooks to search in external documents: ExternalTextSearch
 *   Only uploaded: textfiles, PDF, HTML, DOC, XLS, ... or
 *   External apps: xapian-omages seems to be the better than lucene,
 *   lucene.net, swish, nakamazu, ...
 */
class WikiPlugin_FullTextSearch extends WikiPlugin
{
    public function getDescription()
    {
        return _("Search the content of all pages in this wiki.");
    }

    public function getDefaultArguments()
    {
        // All PageList::supportedArgs, except 'pagename'
        $args = array_merge(
            PageList::supportedArgs(), // paging and more.
            array('s' => '',
                'hilight' => true,
                'case_exact' => false,
                'regex' => 'auto',
                'sortby' => '-hi_content',
                'noheader' => false,
                'exclude' => '', // comma-separated list of glob
                'quiet' => true)
        ); // be less verbose
         unset($args['pagename']);
        return $args;
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

        if (empty($args['s'])) {
            return HTML::p(
                array('class' => 'error'),
                _("You must enter a search term.")
            );
        }
        extract($args);

        if (!is_bool($hilight)) {
            if (($hilight == '0') || ($hilight == 'false')) {
                $hilight = false;
            } elseif (($hilight == '1') || ($hilight == 'true')) {
                $hilight = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "hilight"));
            }
        }

        if (!is_bool($case_exact)) {
            if (($case_exact == '0') || ($case_exact == 'false')) {
                $case_exact = false;
            } elseif (($case_exact == '1') || ($case_exact == 'true')) {
                $case_exact = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "case_exact"));
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

        if (!is_bool($quiet)) {
            if (($quiet == '0') || ($quiet == 'false')) {
                $quiet = false;
            } elseif (($quiet == '1') || ($quiet == 'true')) {
                $quiet = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "quiet"));
            }
        }

        $query = new TextSearchQuery($s, $case_exact, $regex);
        $pages = $dbi->fullSearch($query, $sortby, $limit, $exclude);
        $hilight_re = $hilight ? $query->getHighlightRegexp() : false;
        $count = 0;

        if ($quiet) { // see how easy it is with PageList...
            unset($args['info']);
            $args['listtype'] = 'dl';
            $args['types'] = array(new _PageList_Column_content('rev:hi_content', _("Content"), "left", $s, $hilight_re));
            $list = new PageList(array(), $exclude, $args);
            $list->setCaption(fmt("Full text search results for “%s”", $s));
            while ($page = $pages->next()) {
                $list->addPage($page);
            }
            return $list;
        }

        // Todo: we should better define a new PageListDL class for dl/dt/dd lists
        // But the new column types must have a callback then. (showhits)
        // See e.g. WikiAdminSearchReplace for custom pagelist columns
        $list = HTML::dl();
        if (!$limit or !is_int($limit)) {
            $limit = 0;
        }
        // expand all page wildcards to a list of pages which should be ignored
        if ($exclude) {
            $exclude = explodePageList($exclude);
        }
        while ($page = $pages->next() and (!$limit or ($count < $limit))) {
            $name = $page->getName();
            if ($exclude and in_array($name, $exclude)) {
                continue;
            }
            $count++;
            $list->pushContent(HTML::dt(WikiLink($page)));
            if ($hilight_re) {
                $list->pushContent($this->showhits($page, $hilight_re));
            }
            unset($page);
        }
        if ($limit and $count >= $limit) { //todo: pager link to list of next matches
            $list->pushContent(HTML::dd(fmt("only %d pages displayed", $limit)));
        }
        if (!$list->getContent()) {
            $list->pushContent(HTML::dd(_("No matches")));
        }

        if (!empty($pages->stoplisted)) {
            $list = HTML(
                HTML::p(fmt(
                _("Ignored stoplist words “%s”"),
                join(', ', $pages->stoplisted)
            )),
                $list
            );
        }
        if ($noheader) {
            return $list;
        }
        return HTML(
            HTML::p(fmt("Full text search results for “%s”", $s)),
            $list
        );
    }

    /**
     * @param WikiDB_Page $page
     * @param string $hilight_re
     * @return array
     */
    public function showhits($page, $hilight_re)
    {
        $current = $page->getCurrentRevision();
        $matches = preg_grep("/$hilight_re/i", $current->getContent());
        $html = array();
        foreach ($matches as $line) {
            $line = $this->highlight_line($line, $hilight_re);
            $html[] = HTML::dd(HTML::small(
                array('class' => 'search-context'),
                $line
            ));
        }
        return $html;
    }

    public static function highlight_line($line, $hilight_re)
    {
        while (preg_match("/^(.*?)($hilight_re)/i", $line, $m)) {
            $line = substr($line, strlen($m[0]));
            $html[] = $m[1]; // prematch
            $html[] = HTML::strong(array('class' => 'search-term'), $m[2]); // match
        }
        $html[] = $line; // postmatch
        return $html;
    }
}

/*
 * List of Links and link to ListLinks
 */
class _PageList_Column_hilight extends _PageList_Column
{
    private $parentobj;

    public function __construct(&$params)
    {
        $this->parentobj =& $params[3];
        parent::__construct($params[0], $params[1], $params[2]);
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $pagename = $page_handle->getName();
        $count = count($this->parentobj->_wpagelist[$pagename]);
        return LinkURL(
            WikiURL($page_handle, array('action' => 'BackLinks')),
            fmt("(%d Links)", $count)
        );
    }
}
