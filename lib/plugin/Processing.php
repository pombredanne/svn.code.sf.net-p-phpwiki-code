<?php
/**
 * Copyright © 2009 $ThePhpWikiProgrammingTeam
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
 * Interface to http://ejohn.org/blog/processingjs/
 * Syntax: http://ejohn.org/blog/overview-of-processing/
 */

class WikiPlugin_Processing extends WikiPlugin
{
    public $source;

    public function getDescription()
    {
        return _("Render inline Processing.");
    }

    public function getDefaultArguments()
    {
        return array('width' => 200,
            'height' => 200,
            'script' => false, // one line script. not very likely
            'onmousemove' => false
        );
    }

    public function handle_plugin_args_cruft($argstr, $args)
    {
        $this->source = $argstr;
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
        global $WikiTheme;
        $args = $this->getArgs($argstr, $request);
        if (empty($this->source)) {
            return '';
        }
        $html = HTML();
        if (empty($WikiTheme->_asciiSVG)) {
            $js = JavaScript('', array('src' => $WikiTheme->_findData('Processing.js')));
            if (empty($WikiTheme->_headers_printed)) {
                $WikiTheme->addMoreHeaders($js);
            } else {
                $html->pushContent($js);
            }
            $WikiTheme->_processing = 1; // prevent duplicates
        }
        // extract <script>
        if (preg_match("/^(.*)<script>(.*)<\/script>/ism", $this->source, $m)) {
            $this->source = $m[1];
            $args['script'] = $m[2];
        }
        $embedargs = array('width' => $args['width'],
            'height' => $args['height'],
            //'src'    => "d.svg",
            'script' => $this->source);
        // additional onmousemove argument
        if ($args['onmousemove']) {
            $embedargs['onmousemove'] = $args['onmousemove'];
        }
        // we need script='data' and not script="data"
        $embed = new Processing_HTML("embed", $embedargs);
        $html->pushContent($embed);
        if ($args['script']) {
            $html->pushContent(JavaScript($args['script']));
        }
        return $html;
    }
}

class Processing_HTML extends HtmlElement
{
    public function startTag()
    {
        $start = "<" . $this->_tag;
        $this->_setClasses();
        foreach ($this->_attr as $attr => $val) {
            if (is_bool($val)) {
                if (!$val) {
                    continue;
                }
                $val = $attr;
            }
            $qval = str_replace("\"", '&quot;', $this->_quote((string)$val));
            if ($attr == 'script') {
                // note the ' not "
                $start .= " $attr='$qval'";
            } else {
                $start .= " $attr=\"$qval\"";
            }
        }
        $start .= ">";
        return $start;
    }
}
