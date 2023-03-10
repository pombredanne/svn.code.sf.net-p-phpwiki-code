<?php
/**
 * Copyright © 2010 Sébastien Le Callonnec
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
 * @author: Sébastien Le Callonnec
 */

require_once 'lib/WikiPlugin.php';
require_once 'lib/AtomParser.php';

class WikiPlugin_AtomFeed extends WikiPlugin
{
    public function getDescription()
    {
        return _('Atom Aggregator Plugin.');
    }

    public function getDefaultArguments()
    {
        return array(
            'feed' => "",
            'description' => "",
            'url' => "",
            'maxitem' => 0,
            'titleonly' => false
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
        extract($this->getArgs($argstr, $request));

        if (!is_bool($titleonly)) {
            if (($titleonly == '0') || ($titleonly == 'false')) {
                $titleonly = false;
            } elseif (($titleonly == '1') || ($titleonly == 'true')) {
                $titleonly = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "titleonly"));
            }
        }

        $parser = new AtomParser();

        assert(!empty($url));
        $parser->parse_url($url);

        $html = '';

        $items = HTML::dl();
        foreach ($parser->feed as $feed) {
            $title = HTML::h3(HTML::a(array('href' => $feed["links"]["0"]["href"]), $feed["title"]));
            $counter = 1;
            foreach ($parser->entries as $entry) {
                $item = HTML::dt(HTML::a(array('href' => $entry["links"]["0"]["href"]), $entry["title"]));
                $items->pushContent($item);

                if (!$titleonly) {
                    $description = HTML::dd(HTML::raw(html_entity_decode($entry["content"])));
                } else {
                    $description = HTML::dd();
                }
                $items->pushContent($description);

                if ($maxitem > 0 && $counter >= $maxitem) {
                    break;
                }
                $counter++;
            }
            $html = HTML::div(array('class' => 'rss'), $title);
            $html->pushContent($items);
        }

        return $html;
    }
}
