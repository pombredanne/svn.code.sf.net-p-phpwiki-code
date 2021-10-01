<?php
/**
 * Copyright © 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam
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
 * FuzzyPages is plugin which searches for similar page titles.
 *
 * Pages are considered similar by averaging the similarity scores of
 * the spelling comparison and the metaphone comparison for each page
 * title in the database (php's metaphone() is an improved soundex
 * function).
 *
 * https://www.php.net/manual/en/function.similar-text.php
 * https://www.php.net/manual/en/function.metaphone.php
 */
class WikiPlugin_FuzzyPages
    extends WikiPlugin
{
    private $searchterm;
    private $searchterm_metaphone;
    private $debug;
    private $list;

    function getDescription()
    {
        return sprintf(_("Search for page titles similar to %s."),
            '[pagename]');
    }

    function getDefaultArguments()
    {
        return array('s' => '',
                     'debug' => false);
    }

    private function spelling_similarity($subject)
    {
        $spelling_similarity_score = 0;
        similar_text($subject, $this->searchterm,
            $spelling_similarity_score);
        return $spelling_similarity_score;
    }

    private function sound_similarity($subject)
    {
        $sound_similarity_score = 0;
        similar_text(metaphone($subject), $this->searchterm_metaphone,
            $sound_similarity_score);
        return $sound_similarity_score;
    }

    private function averageSimilarities($subject)
    {
        return ($this->spelling_similarity($subject)
            + $this->sound_similarity($subject)) / 2;
    }

    private function collectSimilarPages(&$list, $dbi)
    {
        if (!defined('MIN_SCORE_CUTOFF'))
            define('MIN_SCORE_CUTOFF', 33);

        $this->searchterm_metaphone = metaphone($this->searchterm);

        $allPages = $dbi->getAllPages();

        while ($pagehandle = $allPages->next()) {
            $pagename = $pagehandle->getName();
            $similarity_score = $this->averageSimilarities($pagename);
            if ($similarity_score > MIN_SCORE_CUTOFF)
                $list[$pagename] = $similarity_score;
        }
    }

    private function sortCollectedPages(&$list)
    {
        arsort($list, SORT_NUMERIC);
    }

    private function addTableCaption($table, $dbi)
    {
        if ($dbi->isWikiPage($this->searchterm))
            $link = WikiLink($this->searchterm, 'auto');
        else
            $link = $this->searchterm;
        $caption = fmt("These page titles match fuzzy with “%s”", $link);
        $table->pushContent(HTML::caption($caption));
    }

    private function addTableHead($table)
    {
        $row = HTML::tr(HTML::th(_("Name")), HTML::th(_("Score")));

        if (defined('DEBUG') && DEBUG && $this->debug) {
            $this->pushDebugHeadingTDinto($row);
        }

        $table->pushContent(HTML::thead($row));
    }

    private function addTableBody($list, $table)
    {
        if (!defined('HIGHLIGHT_ROWS_CUTOFF_SCORE'))
            define('HIGHLIGHT_ROWS_CUTOFF_SCORE', 60);

        $tbody = HTML::tbody();
        foreach ($list as $found_pagename => $score) {
            $row = HTML::tr(array('class' =>
                $score > HIGHLIGHT_ROWS_CUTOFF_SCORE
                    ? 'evenrow' : 'oddrow'),
                HTML::td(WikiLink($found_pagename)),
                HTML::td(array('class' => 'align-right'),
                    round($score)));

            if (defined('DEBUG') && DEBUG && $this->debug) {
                $this->pushDebugTDinto($row, $found_pagename);
            }

            $tbody->pushContent($row);
        }
        $table->pushContent($tbody);
    }

    private function formatTable($list, $dbi)
    {

        if (empty($list)) {
            return HTML::p(fmt("No fuzzy matches with “%s”", $this->searchterm));
        }
        $table = HTML::table(array('class' => 'pagelist'));
        $this->addTableCaption($table, $dbi);
        $this->addTableHead($table);
        $this->addTableBody($list, $table);
        return $table;
    }

    private function pushDebugHeadingTDinto($row)
    {
        $row->pushContent(HTML::td(_("Spelling Score")),
            HTML::td(_("Sound Score")),
            HTML::td('Metaphones'));
    }

    private function pushDebugTDinto($row, $pagename)
    {
        // This actually calculates everything a second time for each pagename
        // so the individual scores can be displayed separately for debugging.
        $debug_spelling = round($this->spelling_similarity($pagename), 1);
        $debug_sound = round($this->sound_similarity($pagename), 1);
        $debug_metaphone = sprintf("(%s, %s)", metaphone($pagename),
            $this->searchterm_metaphone);

        $row->pushContent(HTML::td(array('class' => 'align-center'), $debug_spelling),
            HTML::td(array('class' => 'align-center'), $debug_sound),
            HTML::td($debug_metaphone));
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    function run($dbi, $argstr, &$request, $basepage)
    {
        $args = $this->getArgs($argstr, $request);
        extract($args);

        if (!is_bool($debug)) {
            if (($debug == '0') || ($debug == 'false')) {
                $debug = false;
            } elseif (($debug == '1') || ($debug == 'true')) {
                $debug = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "debug"));
            }
        }

        if (empty($s)) {
            return HTML::p(array('class' => 'warning'),
                           _("You must enter a search term."));
        }

        if (defined('DEBUG') && DEBUG) {
            $this->debug = $debug;
        }

        $this->searchterm = $s;
        $this->list = array();

        $this->collectSimilarPages($this->list, $dbi);
        $this->sortCollectedPages($this->list);
        return $this->formatTable($this->list, $dbi);
    }
}
