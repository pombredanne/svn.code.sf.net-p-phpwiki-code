<?php
/**
 * Copyright © 2008-2009, 2011 Marc-Etienne Vargenau, Alcatel-Lucent
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

/*
 * Standard Alcatel-Lucent disclaimer for contributing to open source
 *
 * "The WikicreoleTablePlugin ("Contribution") has not been tested and/or
 * validated for release as or in products, combinations with products or
 * other commercial use. Any use of the Contribution is entirely made at
 * the user's own responsibility and the user can not rely on any features,
 * functionalities or performances Alcatel-Lucent has attributed to the
 * Contribution.
 *
 * THE CONTRIBUTION BY ALCATEL-LUCENT IS PROVIDED AS IS, WITHOUT WARRANTY
 * OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, COMPLIANCE,
 * NON-INTERFERENCE AND/OR INTERWORKING WITH THE SOFTWARE TO WHICH THE
 * CONTRIBUTION HAS BEEN MADE, TITLE AND NON-INFRINGEMENT. IN NO EVENT SHALL
 * ALCATEL-LUCENT BE LIABLE FOR ANY DAMAGES OR OTHER LIABLITY, WHETHER IN
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * CONTRIBUTION OR THE USE OR OTHER DEALINGS IN THE CONTRIBUTION, WHETHER
 * TOGETHER WITH THE SOFTWARE TO WHICH THE CONTRIBUTION RELATES OR ON A STAND
 * ALONE BASIS."
 */

/**
 * WikicreoleTablePlugin
 * A PhpWiki plugin that allows insertion of tables using the Wikicreole
 * syntax.
 */

class WikiPlugin_WikicreoleTable extends WikiPlugin
{
    public function getDescription()
    {
        return _("Layout tables using the Wikicreole syntax.");
    }

    public function getDefaultArguments()
    {
        return array();
    }

    /**
     * return links
     *
     * @param  string $argstr   The plugin argument string.
     * @param  string $basepage The pagename the plugin is invoked from.
     * @return array  List of pagenames linked to.
     */
    public function getWikiPageLinks($argstr, $basepage)
    {
        return getTextLinks($argstr);
    }

    public function handle_plugin_args_cruft($argstr, $args)
    {
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
        include_once 'lib/InlineParser.php';

        $table = array();

        $lines = preg_split('/\s*?\n\s*/', $argstr);

        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }
            $line = trim($line);
            // If line ends with a '|', remove it
            if ($line[strlen($line) - 1] == '|') {
                $line = substr($line, 0, -1);
            }
            if ((strlen($line) > 0) && ($line[0] == '|')) {
                $table[] = $this->parse_row($line);
            }
        }

        $nb_rows = count($table);
        // If table is empty, do not generate table markup
        if ($nb_rows == 0) {
            return HTML::raw('');
        }

        // Number of columns is the number of cells in the longer row
        $nb_cols = 0;
        for ($i = 0; $i < $nb_rows; $i++) {
            $nb_cols = max($nb_cols, count($table[$i]));
        }

        for ($i = 0; $i < $nb_rows; $i++) {
            for ($j = 0; $j < $nb_cols; $j++) {
                if (!isset($table[$i][$j])) {
                    $table[$i][$j] = '';
                }
            }
        }

        for ($i = 0; $i < $nb_rows; $i++) {
            for ($j = 0; $j < $nb_cols; $j++) {
                if (preg_match('/@@/', $table[$i][$j])) {
                    $table[$i][$j] = $this->compute_table_cell($table, $i, $j, $nb_rows, $nb_cols);
                }
            }
        }

        $html_table = HTML::table(array('class' => "bordered"));
        foreach ($table as $row) {
            $html_row = HTML::tr();
            foreach ($row as $cell) {
                if ($cell && $cell[0] == '=') {
                    $cell = trim(substr($cell, 1));
                    $html_row->pushContent(HTML::th(TransformInline($cell, $basepage)));
                } else {
                    if ($this->is_wiki_numeric($cell)) {
                        $html_row->pushContent(HTML::td(array('style' => "text-align:right"), TransformInline($cell, $basepage)));
                    } else {
                        $html_row->pushContent(HTML::td(TransformInline($cell, $basepage)));
                    }
                }
            }
            $html_table->pushContent($html_row);
        }
        return $html_table;
    }

    // $cell is a number, possibly in bold, italics or underlined
    private function is_wiki_numeric($cell)
    {
        return is_numeric(trim($cell, "*/_'"));
    }

    private function parse_row($line)
    {
        $line = str_replace('|', ' |', $line);

        $bracket_link = "\\[ .*? [^]\s] .*? \\]";
        $cell_content = "(?: [^[] | " . ESCAPE_CHAR . "\\[ | $bracket_link )*?";

        preg_match_all(
            "/(\\|+) \s* ($cell_content) \s* (?=\\||\$)/x",
            $line,
            $matches,
            PREG_SET_ORDER
        );

        $row = array();

        foreach ($matches as $m) {
            $cell = $m[2];
            $row[] = $cell;
        }
        return $row;
    }

    /**
     * Compute cell in spreadsheet table
     * @param array $table: two-dimensional table
     * @param int $i: first index of cell to compute
     * @param int $j: second index of cell to compute
     * @param int $imax: first table dimension
     * @param int $jmax: second table dimension
     * @return int
     */
    private function compute_table_cell($table, $i, $j, $imax, $jmax)
    {

        // What is implemented:
        // @@=SUM(R)@@ : sum of cells in current row
        // @@=SUM(C)@@ : sum of cells in current column
        // @@=AVERAGE(R)@@ : average of cells in current row
        // @@=AVERAGE(C)@@ : average of cells in current column
        // @@=MAX(R)@@ : maximum value of cells in current row
        // @@=MAX(C)@@ : maximum value of cells in current column
        // @@=MIN(R)@@ : minimum value of cells in current row
        // @@=MIN(C)@@ : minimum value of cells in current column
        // @@=COUNT(R)@@ : number of cells in current row
        //                (numeric or not, excluding headers and current cell)
        // @@=COUNT(C)@@ : number of cells in current column
        //                (numeric or not, excluding headers and current cell)

        $result = 0;
        $counter = 0;
        $found = false;

        if (strpos($table[$i][$j], "@@=SUM(C)@@") !== false) {
            for ($index = 0; $index < $imax; $index++) {
                if (is_numeric($table[$index][$j])) {
                    $result += $table[$index][$j];
                }
            }
            return str_replace("@@=SUM(C)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=SUM(R)@@") !== false) {
            for ($index = 0; $index < $jmax; $index++) {
                if (is_numeric($table[$i][$index])) {
                    $result += $table[$i][$index];
                }
            }
            return str_replace("@@=SUM(R)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=AVERAGE(C)@@") !== false) {
            for ($index = 0; $index < $imax; $index++) {
                if (is_numeric($table[$index][$j])) {
                    $result += $table[$index][$j];
                    $counter++;
                }
            }
            $result = $result / $counter;
            return str_replace("@@=AVERAGE(C)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=AVERAGE(R)@@") !== false) {
            for ($index = 0; $index < $jmax; $index++) {
                if (is_numeric($table[$i][$index])) {
                    $result += $table[$i][$index];
                    $counter++;
                }
            }
            $result = $result / $counter;
            return str_replace("@@=AVERAGE(R)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=MAX(C)@@") !== false) {
            for ($index = 0; $index < $imax; $index++) {
                if (is_numeric($table[$index][$j])) {
                    if (!$found) {
                        $found = true;
                        $result = $table[$index][$j];
                    } else {
                        $result = max($result, $table[$index][$j]);
                    }
                }
            }
            if (!$found) {
                $result = "";
            }
            return str_replace("@@=MAX(C)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=MAX(R)@@") !== false) {
            for ($index = 0; $index < $jmax; $index++) {
                if (is_numeric($table[$i][$index])) {
                    if (!$found) {
                        $found = true;
                        $result = $table[$i][$index];
                    } else {
                        $result = max($result, $table[$i][$index]);
                    }
                }
            }
            if (!$found) {
                $result = "";
            }
            return str_replace("@@=MAX(R)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=MIN(C)@@") !== false) {
            for ($index = 0; $index < $imax; $index++) {
                if (is_numeric($table[$index][$j])) {
                    if (!$found) {
                        $found = true;
                        $result = $table[$index][$j];
                    } else {
                        $result = min($result, $table[$index][$j]);
                    }
                }
            }
            if (!$found) {
                $result = "";
            }
            return str_replace("@@=MIN(C)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=MIN(R)@@") !== false) {
            for ($index = 0; $index < $jmax; $index++) {
                if (is_numeric($table[$i][$index])) {
                    if (!$found) {
                        $found = true;
                        $result = $table[$i][$index];
                    } else {
                        $result = min($result, $table[$i][$index]);
                    }
                }
            }
            if (!$found) {
                $result = "";
            }
            return str_replace("@@=MIN(R)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=COUNT(C)@@") !== false) {
            for ($index = 0; $index < $imax; $index++) {
                // exclude header
                if (!string_starts_with(trim($table[$index][$j]), "=")) {
                    $counter++;
                }
            }
            if (string_starts_with(trim($table[$i][$j]), "=")) {
                $result = $counter;
            } else {
                $result = $counter - 1; // exclude self
            }
            return str_replace("@@=COUNT(C)@@", $result, $table[$i][$j]);
        } elseif (strpos($table[$i][$j], "@@=COUNT(R)@@") !== false) {
            for ($index = 0; $index < $jmax; $index++) {
                // exclude header
                if (!string_starts_with(trim($table[$i][$index]), "=")) {
                    $counter++;
                }
            }
            if (string_starts_with(trim($table[$i][$j]), "=")) {
                $result = $counter;
            } else {
                $result = $counter - 1; // exclude self
            }
            return str_replace("@@=COUNT(R)@@", $result, $table[$i][$j]);
        }

        return $table[$i][$j];
    }
}
