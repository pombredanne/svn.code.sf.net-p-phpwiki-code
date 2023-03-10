<?php
/**
 * Copyright © 1999,2000,2001,2002,2004,2005,2010 $ThePhpWikiProgrammingTeam
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

require_once 'lib/TextSearchQuery.php';
require_once 'lib/PageList.php';

/**
 * Display results of pagename search.
 * Provides no own input box, just <?plugin-form TitleSearch?> is enough.
 * Fancier Inputforms can be made using <<WikiFormRich ...>> to support regex and case_exact args.
 *
 * If only one pages is found and auto_redirect is true, this page is displayed immediatly,
 * otherwise the found pagelist is displayed.
 * The workhorse TextSearchQuery converts the query string from google-style words
 * to the required DB backend expression.
 *   (word and word) OR word, -word, "two words"
 * regex=auto tries to detect simple glob-style wildcards and expressions,
 * like xx*, *xx, ^xx, xx$, ^word$.
 */

class WikiPlugin_TitleSearch extends WikiPlugin
{
    public function getDescription()
    {
        return _("Search the titles of all pages in this wiki.");
    }

    public function getDefaultArguments()
    {
        // All PageList::supportedArgs, except 'pagename'
        $args = array_merge(
            PageList::supportedArgs(), // paging and more.
            array('s' => '',
                'auto_redirect' => false,
                'noheader' => false,
                'exclude' => false,
                'info' => false,
                'case_exact' => false,
                'regex' => 'auto',
                'format' => false,
            )
        );
        unset($args['pagename']);
        return $args;
    }

    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=Php*,RecentChanges

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

        $auto_redirect = $args['auto_redirect'];
        if (!is_bool($auto_redirect)) {
            if (($auto_redirect == '0') || ($auto_redirect == 'false')) {
                $auto_redirect = false;
            } elseif (($auto_redirect == '1') || ($auto_redirect == 'true')) {
                $auto_redirect = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "auto_redirect"));
            }
        }

        $noheader = $args['noheader'];
        if (!is_bool($noheader)) {
            if (($noheader == '0') || ($noheader == 'false')) {
                $noheader = false;
            } elseif (($noheader == '1') || ($noheader == 'true')) {
                $noheader = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noheader"));
            }
        }

        $case_exact = $args['case_exact'];
        if (!is_bool($case_exact)) {
            if (($case_exact == '0') || ($case_exact == 'false')) {
                $case_exact = false;
            } elseif (($case_exact == '1') || ($case_exact == 'true')) {
                $case_exact = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "case_exact"));
            }
        }

        if (isset($args['limit']) && !is_limit($args['limit'])) {
            return HTML::p(
                array('class' => 'error'),
                _("Illegal “limit” argument: must be an integer or two integers separated by comma")
            );
        }

        if (empty($args['s'])) {
            return HTML::p(
                array('class' => 'error'),
                _("You must enter a search term.")
            );
        }

        if (empty($args['sortby'])) {
            $args['sortby'] = '-pagename';
        }

        // ^S != S*   ^  matches only beginning of phrase, not of word.
        //            x* matches any word beginning with x
        $query = new TextSearchQuery($args['s'], $args['case_exact'], $args['regex']);
        $pages = $dbi->titleSearch($query, $args['sortby'], $args['limit'], $args['exclude']);

        $pagelist = new PageList($args['info'], $args['exclude'], $args);
        $pagelist->addPages($pages);

        // Provide an unknown WikiWord link to allow for page creation
        // when a search returns no results
        if (!$args['noheader']) {
            $s = $args['s'];
            $total = $pagelist->getTotal();
            if (!$total and !$query->_regex) {
                $s = WikiLink($args['s'], 'auto');
            }
            if ($total) {
                $pagelist->setCaption(fmt("Title search results for “%s” (%d total)", $s, $total));
            } else {
                $pagelist->setCaption(fmt("Title search results for “%s”", $s));
            }
        }

        if ($args['auto_redirect'] && ($pagelist->getTotal() == 1)) {
            $page = $pagelist->first();
            $request->redirect(WikiURL($page->getName(), array(), 'absurl'), false);
        }

        return $pagelist;
    }
}
