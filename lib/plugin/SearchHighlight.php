<?php
/**
 * Copyright © 2004,2007 $ThePhpWikiProgrammingTeam
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
 * When someone is referred from a search engine like Google, Yahoo
 * or our own fulltextsearch, the terms they search for are highlighted.
 * See http://wordpress.org/about/shots/1.2/plugins.png
 *
 * This plugin is normally just used to print a header through an action page.
 * The highlighting is done through InlineParser automatically if ENABLE_SEARCHHIGHLIGHT is enabled.
 * If hits = 1, then the list of found terms is also printed.
 */

class WikiPlugin_SearchHighlight extends WikiPlugin
{
    public function getDescription()
    {
        return _("Hilight referred search terms.");
    }

    public function getDefaultArguments()
    {
        // s, engine and engine_url are picked from the request
        return array(
            's' => '',             // search term
            'noheader' => false,   // don't print the header
            'hits' => false,       // print the list of lines with lines terms additionally
            'case_exact' => false, // not yet supported
            'regex' => '',         // not yet supported
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
        if (empty($args['s']) and isset($request->_searchhighlight)) {
            $args['s'] = $request->_searchhighlight['query'];
        }
        if (empty($args['s'])) {
            return HTML();
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
        if (!is_bool($hits)) {
            if (($hits == '0') || ($hits == 'false')) {
                $hits = false;
            } elseif (($hits == '1') || ($hits == 'true')) {
                $hits = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "hits"));
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

        $html = HTML();
        if (!$noheader and isset($request->_searchhighlight)) {
            $engine = $request->_searchhighlight['engine'];
            $html->pushContent(HTML::div(
                array('class' => 'search-context'),
                fmt(
                    "%s: Found %s through %s",
                    $basepage,
                    $request->_searchhighlight['query'],
                    $engine
                )
            ));
        }
        if ($hits) {
            $query = new TextSearchQuery($s, $case_exact, $regex);
            $hilight_re = $query->getHighlightRegexp();
            $page = $request->getPage();
            $html->pushContent($this->showhits($page, $hilight_re));
        }
        return $html;
    }

    public function showhits($page, $hilight_re)
    {
        $current = $page->getCurrentRevision();
        $matches = preg_grep("/$hilight_re/i", $current->getContent());
        $html = HTML::dl();
        foreach ($matches as $line) {
            $line = $this->highlight_line($line, $hilight_re);
            $html->pushContent(HTML::dd(
                array('class' => 'search-context'),
                HTML::small($line)
            ));
        }
        return $html;
    }

    public function highlight_line($line, $hilight_re)
    {
        $html = HTML();
        while (preg_match("/^(.*?)($hilight_re)/i", $line, $m)) {
            $line = substr($line, strlen($m[0]));
            // prematch + match
            $html->pushContent($m[1], HTML::strong(array('class' => 'search-term'), $m[2]));
        }
        $html->pushContent($line); // postmatch
        return $html;
    }
}
