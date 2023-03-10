<?php
/*
 * Copyright © 2002 Johannes Große
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
 * Produces graphical site map of PhpWiki
 * @author Johannes Große
 * @version 0.9
 */
/* define('VISUALWIKI_ALLOWOPTIONS', true); */
if (!defined('VISUALWIKI_ALLOWOPTIONS')) {
    define('VISUALWIKI_ALLOWOPTIONS', false);
}

require_once 'lib/plugin/GraphViz.php';

class WikiPlugin_VisualWiki extends WikiPlugin_GraphViz
{
    public $pages;
    public $names;
    public $ColorTab;
    public $oldest;

    /**
     * Sets plugin type to map production
     */
    public function getPluginType()
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        return ($request->getArg('debug')) ? PLUGIN_CACHED_IMG_ONDEMAND
            : PLUGIN_CACHED_MAP;
    }

    public function getDescription()
    {
        return _("Visualizes the Wiki structure in a graph using the 'dot' commandline tool from graphviz.");
    }

    public function defaultArguments()
    {
        return array('imgtype' => 'png',
            'width' => false, // was 5, scale it automatically
            'height' => false, // was 7, scale it automatically
            'colorby' => 'age', // sort by 'age' or 'revtime'
            'fillnodes' => 'off',
            'label' => 'name',
            'shape' => 'ellipse',
            'large_nb' => 5,
            'recent_nb' => 5,
            'refined_nb' => 15,
            'backlink_nb' => 5,
            'neighbour_list' => '',
            'exclude_list' => '',
            'include_list' => '',
            'fontsize' => 9,
            'debug' => false,
            'help' => false);
    }

    /**
     * Sets the default arguments. WikiPlugin also regards these as
     * the allowed arguments. Since WikiPluginCached stores an image
     * for each different set of parameters, there can be a lot of
     * these (large) graphs if you allow different parameters.
     * Set <code>VISUALWIKI_ALLOWOPTIONS</code> to <code>false</code>
     * to allow no options to be set and use only the default parameters.
     * This will need an disk space of about 20 Kbyte all the time.
     */
    public function getDefaultArguments()
    {
        if (VISUALWIKI_ALLOWOPTIONS) {
            return $this->defaultArguments();
        } else {
            return array();
        }
    }

    /**
     * Substitutes each forbidden parameter value by the default value
     * defined in <code>defaultArguments</code>.
     */
    public function checkArguments(&$arg)
    {
        extract($arg);
        $def = $this->defaultArguments();
        if (($width < 3) || ($width > 15)) {
            $arg['width'] = $def['width'];
        }
        if (($height < 3) || ($height > 20)) {
            $arg['height'] = $def['height'];
        }
        if (($fontsize < 8) || ($fontsize > 24)) {
            $arg['fontsize'] = $def['fontsize'];
        }
        if (!in_array($label, array('name', 'number'))) {
            $arg['label'] = $def['label'];
        }

        if (!in_array($shape, array('ellipse', 'box', 'point', 'circle',
            'plaintext'))
        ) {
            $arg['shape'] = $def['shape'];
        }
        if (!in_array($colorby, array('age', 'revtime'))) {
            $arg['colorby'] = $def['colorby'];
        }
        if (!in_array($fillnodes, array('on', 'off'))) {
            $arg['fillnodes'] = $def['fillnodes'];
        }
        if (($large_nb < 0) || ($large_nb > 50)) {
            $arg['large_nb'] = $def['large_nb'];
        }
        if (($recent_nb < 0) || ($recent_nb > 50)) {
            $arg['recent_nb'] = $def['recent_nb'];
        }
        if (($refined_nb < 0) || ($refined_nb > 50)) {
            $arg['refined_nb'] = $def['refined_nb'];
        }
        if (($backlink_nb < 0) || ($backlink_nb > 50)) {
            $arg['backlink_nb'] = $def['backlink_nb'];
        }
        // ToDo: check if "ImageCreateFrom$imgtype"() exists.
        if (!in_array($imgtype, $GLOBALS['PLUGIN_CACHED_IMGTYPES'])) {
            $arg['imgtype'] = $def['imgtype'];
        }
        if (empty($fontname)) {
            $arg['fontname'] = VISUALWIKIFONT;
        }
    }

    /**
     * Checks options, creates help page if necessary, calls both
     * database access and image map production functions.
     * @param WikiDB $dbi
     * @param array $argarray
     * @param Request $request
     * @return array($map,$html)
     */
    protected function getMap($dbi, $argarray, $request)
    {
        if (!VISUALWIKI_ALLOWOPTIONS) {
            $argarray = $this->defaultArguments();
        }
        $this->checkArguments($argarray);
        $request->setArg('debug', $argarray['debug']);
        //extract($argarray);
        if ($argarray['help']) {
            return array($this->helpImage(), ' ');
        } // FIXME
        $this->createColors();
        $this->extract_wikipages($dbi, $argarray);
        /* ($dbi,  $large, $recent, $refined, $backlink,
            $neighbour, $excludelist, $includelist, $color); */
        $result = $this->invokeDot($argarray);
        if (is_a($result, 'HtmlElement')) {
            return array(false, $result);
        } else {
            return $result;
        }
        /* => ($width, $height, $color, $shape, $text); */
    }

    /**
     * Returns an image containing a usage description of the plugin.
     * @return string image handle
     */
    public function helpImage()
    {
        $def = $this->defaultArguments();
        $other_imgtypes = $GLOBALS['PLUGIN_CACHED_IMGTYPES'];
        unset($other_imgtypes[$def['imgtype']]);
        $helparr = array(
            '<<' . $this->getName() .
                ' img' => ' = "' . $def['imgtype'] . "(default)|" . join('|', $GLOBALS['PLUGIN_CACHED_IMGTYPES']) . '"',
            'width' => ' = "width in inches"',
            'height' => ' = "height in inches"',
            'fontname' => ' = "font family"',
            'fontsize' => ' = "fontsize in points"',
            'colorby' => ' = "age|revtime|none"',
            'fillnodes' => ' = "on|off"',
            'shape' => ' = "ellipse(default)|box|circle|point"',
            'label' => ' = "name|number"',
            'large_nb' => ' = "number of largest pages to be selected"',
            'recent_nb' => ' = "number of youngest pages"',
            'refined_nb' => ' = "#pages with smallest time between revisions"',
            'backlink_nb' => ' = "number of pages with most backlinks"',
            'neighbour_list' => ' = "find pages linked from and to these pages"',
            'exclude_list' => ' = "colon separated list of pages to be excluded"',
            'include_list' => ' = "colon separated list"     >>'
        );
        $length = 0;
        foreach ($helparr as $alignright => $alignleft) {
            $length = max($length, strlen($alignright));
        }
        $helptext = '';
        foreach ($helparr as $alignright => $alignleft) {
            $helptext .= substr('                                                        '
                . $alignright, -$length) . $alignleft . "\n";
        }
        return $this->text2img(
            $helptext,
            4,
            array(1, 0, 0),
            array(255, 255, 255)
        );
    }

    /**
     * Selects the first (smallest or biggest) WikiPages in
     * a given category.
     *
     * @param int $number
     * @param string $category
     * @param bool $minimum
     * @internal param int $number number of page names to be found
     * @internal param string $category attribute of the pages which is used
     *                           to compare them
     * @internal param bool $minimum true finds smallest, false finds biggest
     * @return array list of page names found to be the best
     */
    public function findbest($number, $category, $minimum)
    {
        // select the $number best in the category '$category'
        $pages = &$this->pages;
        $names = &$this->names;

        $selected = array();
        $i = 0;
        foreach ($names as $name) {
            if ($i++ >= $number) {
                break;
            }
            $selected[$name] = $pages[$name][$category];
        }
        //echo "<pre>$category "; var_dump($selected); "</pre>";
        $compareto = $minimum ? 0x79999999 : -0x79999999;

        $i = 0;
        foreach ($names as $name) {
            if ($i++ < $number) {
                continue;
            }
            if ($minimum) {
                if (($crit = $pages[$name][$category]) < $compareto) {
                    $selected[$name] = $crit;
                    asort($selected, SORT_NUMERIC);
                    array_pop($selected);
                    $compareto = end($selected);
                }
            } elseif (($crit = $pages[$name][$category]) > $compareto) {
                $selected[$name] = $crit;
                arsort($selected, SORT_NUMERIC);
                array_pop($selected);
                $compareto = end($selected);
            }
        }
        //echo "<pre>$category "; var_dump($selected); "</pre>";

        return array_keys($selected);
    }

    /**
     * Extracts a subset of all pages from the wiki and find their
     * connections to other pages. Also collects some page features
     * like size, age, revision number which are used to find the
     * most attractive pages.
     *
     * @param WikiDB $dbi
     * @param $argarray
     * @internal param \WikiDB $dbi database handle to access all Wiki pages
     * @internal param int $LARGE number of largest pages which should
     *                              be included
     * @internal param int $RECENT number of the youngest pages to be included
     * @internal param int $REFINED number of the pages with shortes revision
     *                              interval
     * @internal param int $BACKLINK number of the pages with most backlinks
     * @internal param string $EXCLUDELIST colon ':' separated list of page names which
     *                              should not be displayed (like PhpWiki, for
     *                              example)
     * @internal param string $INCLUDELIST colon separated list of pages which are
     *                              always included (for example your own
     *                              page :)
     * @internal param string $COLOR 'age', 'revtime' or 'none'; Selects which
     *                              page feature is used to determine the
     *                              filling color of the nodes in the graph.
     * @return void
     */
    public function extract_wikipages($dbi, $argarray)
    {
        // $LARGE, $RECENT, $REFINED, $BACKLINK, $NEIGHBOUR,
        // $EXCLUDELIST, $INCLUDELIST,$COLOR
        $now = time();

        extract($argarray);
        // FIXME: gettextify?
        $exclude_list = $exclude_list ? explode(':', $exclude_list) : array();
        $include_list = $include_list ? explode(':', $include_list) : array();
        $neighbour_list = $neighbour_list ? explode(':', $neighbour_list) : array();

        // remove INCLUDED from EXCLUDED, includes override excludes.
        if ($exclude_list and $include_list) {
            $diff = array_diff($exclude_list, $include_list);
            if ($diff) {
                $exclude_list = $diff;
            }
        }

        // collect all pages
        $allpages = $dbi->getAllPages(false, false, false, $exclude_list);
        $pages = &$this->pages;
        while ($page = $allpages->next()) {
            $name = $page->getName();

            // skip excluded pages
            if (in_array($name, $exclude_list)) {
                $page->free();
                continue;
            }

            // false = get links from actual page
            // true  = get links to actual page ("backlinks")
            $backlinks = $page->getLinks();
            unset($bconnection);
            $bconnection = array();
            while ($blink = $backlinks->next()) {
                array_push($bconnection, $blink->getName());
            }
            $backlinks->free();
            unset($backlinks);

            // include all neighbours of pages listed in $NEIGHBOUR
            if (in_array($name, $neighbour_list)) {
                $ln = $page->getLinks(false);
                $con = array();
                while ($link = $ln->next()) {
                    array_push($con, $link->getName());
                }
                $include_list = array_merge($include_list, $bconnection, $con);
                $ln->free();
                unset($l);
                unset($con);
            }

            unset($rev);
            $rev = $page->getCurrentRevision();

            $pages[$name] = array(
                'age' => $now - $rev->get('mtime'),
                'revnr' => $rev->getVersion(),
                'links' => array(),
                'backlink_nb' => count($bconnection),
                'backlinks' => $bconnection,
                'size' => 1000 // FIXME
            );
            $pages[$name]['revtime'] = $pages[$name]['age'] / ($pages[$name]['revnr']);

            unset($page);
        }
        $allpages->free();
        unset($allpages);
        $this->names = array_keys($pages);

        // now select each page matching to given parameters
        $all_selected = array_unique(array_merge(
            $this->findbest($recent_nb, 'age', true),
            $this->findbest($refined_nb, 'revtime', true),
            $x = $this->findbest($backlink_nb, 'backlink_nb', false),
//          $this->findbest($large_nb,    'size',        false),
            $include_list
        ));

        foreach ($all_selected as $name) {
            if (isset($pages[$name])) {
                $newpages[$name] = $pages[$name];
            }
        }
        unset($this->names);
        unset($this->pages);
        $this->pages = $newpages;
        $pages = &$this->pages;
        $this->names = array_keys($pages);
        unset($newpages);
        unset($all_selected);

        // remove dead links and collect links
        reset($pages);
        foreach ($pages as $name => $page) {
            if (is_array($page['backlinks'])) {
                reset($page['backlinks']);
                foreach ($page['backlinks'] as $index => $link) {
                    if (!isset($pages[$link]) || $link == $name) {
                        unset($pages[$name]['backlinks'][$index]);
                    } else {
                        array_push($pages[$link]['links'], $name);
                        //array_push($this->everylink, array($link,$name));
                    }
                }
            }
        }

        if ($colorby == 'none') {
            return;
        }
        list($oldestname) = $this->findbest(1, $colorby, false);
        $this->oldest = $pages[$oldestname][$colorby];
        foreach ($this->names as $name) {
            $pages[$name]['color'] = $this->getColor($pages[$name][$colorby] / $this->oldest);
        }
    }

    /**
     * Creates the text file description of the graph needed to invoke
     * <code>dot</code>.
     *
     * @param string $tempfile
     * @param array $argarray
     * @internal param float $width width of the output graph in inches
     * @internal param float $height height of the graph in inches
     * @internal param string $colorby color sceme beeing used ('age', 'revtime',
     *                                                   'none')
     * @internal param string $shape node shape; 'ellipse', 'box', 'circle', 'point'
     * @internal param string $label 'name': label by name,
     *                          'number': label by unique number
     * @return bool error status; true=ok; false=error
     */
    public function createDotFile($tempfile = '', $argarray = array())
    {
        extract($argarray);
        if (!$fp = fopen($tempfile, 'w')) {
            return false;
        }

        $fillstring = ($fillnodes == 'on') ? 'style=filled,' : '';

        $names = &$this->names;
        $pages = &$this->pages;
        if ($names) {
            $nametonumber = array_flip($names);
        }

        $dot = "digraph VisualWiki {\n" // }
            . (!empty($fontpath) ? "    fontpath=\"$fontpath\"\n" : "");
        if ($width and $height) {
            $dot .= "    size=\"$width,$height\";\n    ";
        }

        switch ($shape) {
            case 'point':
                $dot .= "edge [arrowhead=none];\nnode [shape=$shape,fontname=$fontname,width=0.15,height=0.15,fontsize=$fontsize];\n";
                break;
            case 'box':
                $dot .= "node [shape=$shape,fontname=$fontname,width=0.4,height=0.4,fontsize=$fontsize];\n";
                break;
            case 'circle':
                $dot .= "node [shape=$shape,fontname=$fontname,width=0.25,height=0.25,fontsize=$fontsize];\n";
                break;
            default:
                $dot .= "node [fontname=$fontname,shape=$shape,fontsize=$fontsize];\n";
        }
        $dot .= "\n";
        foreach ($names as $name) {
            $url = rawurlencode($name);
            // patch to allow Page/SubPage
            $url = str_replace(urlencode('/'), '/', $url);
            $nodename = ($label != 'name' ? $nametonumber[$name] + 1 : $name);

            $dot .= "    \"$nodename\" [URL=\"$url\"";
            if ($colorby != 'none') {
                $col = $pages[$name]['color'];
                $dot .= sprintf(
                    ',%scolor="#%02X%02X%02X"',
                    $fillstring,
                    $col[0],
                    $col[1],
                    $col[2]
                );
            }
            $dot .= "];\n";

            if (!empty($pages[$name]['links'])) {
                unset($linkarray);
                if ($label != 'name') {
                    foreach ($pages[$name]['links'] as $linkname) {
                        $linkarray[] = $nametonumber[$linkname] + 1;
                    }
                } else {
                    $linkarray = $pages[$name]['links'];
                }
                $linkstring = join('"; "', $linkarray);

                $c = count($pages[$name]['links']);
                $dot .= "        \"$nodename\" -> "
                    . ($c > 1 ? '{' : '')
                    . "\"$linkstring\";"
                    . ($c > 1 ? '}' : '')
                    . "\n";
            }
        }
        if ($colorby != 'none') {
            $dot .= "\n    subgraph cluster_legend {\n"
                . "         node[fontname=$fontname,shape=box,width=0.4,height=0.4,fontsize=$fontsize];\n"
                . "         fillcolor=lightgrey;\n"
                . "         style=filled;\n"
                . "         fontname=$fontname;\n"
                . "         fontsize=$fontsize;\n"
                . "         label=\"" . gettext("Legend") . "\";\n";
            $oldest = ceil($this->oldest / (24 * 3600));
            $max = 5;
            $legend = array();
            for ($i = 0; $i < $max; $i++) {
                $time = floor($i / $max * $oldest);
                $name = '"' . $time . ' ' . _("days") . '"';
                $col = $this->getColor($i / $max);
                $dot .= sprintf(
                    '       %s [%scolor="#%02X%02X%02X"];',
                    $name,
                    $fillstring,
                    $col[0],
                    $col[1],
                    $col[2]
                )
                    . "\n";
                $legend[] = $name;
            }
            $dot .= '        ' . join(' -> ', $legend)
                . ";\n    }\n";
        }

        // {
        $dot .= "}\n";
        $this->source = $dot;
        // write a temp file
        $ok = fwrite($fp, $dot);
        $ok = fclose($fp) && $ok; // close anyway

        return $ok;
    }

    /**
     * static workaround on broken Cache or broken dot executable,
     * called only if debug=static.
     *
     * @param string $url
     * @param WikiDB $dbi
     * @param array $argarray
     * @param  request  Request ???
     * @internal param string $url url pointing to the image part of the map
     * @internal param string $map &lt;area&gt; tags defining active
     *                          regions in the map
     * @internal param \WikiDB $dbi database abstraction class
     * @internal param array $argarray complete (!) arguments to produce
     *                          image. It is not necessary to call
     *                          WikiPlugin->getArgs anymore.
     * @return string html output
     */
    private function embedImg($url, &$dbi, $argarray, &$request)
    {
        if (!VISUALWIKI_ALLOWOPTIONS) {
            $argarray = $this->defaultArguments();
        }
        $this->checkArguments($argarray);
        //extract($argarray);
        if ($argarray['help']) {
            return array($this->helpImage(), ' ');
        } // FIXME
        $this->createColors();
        $this->extract_wikipages($dbi, $argarray);
        list($imagehandle, $content['html']) = $this->invokeDot($argarray);
        // write to uploads and produce static url
        $file_dir = getUploadFilePath();
        $upload_dir = getUploadDataPath();
        $tmpfile = tempnam($file_dir, "VisualWiki") . "." . $argarray['imgtype'];
        WikiPluginCached::writeImage($argarray['imgtype'], $imagehandle, $tmpfile);
        imagedestroy($imagehandle);
        return WikiPluginCached::embedMap(
            1,
            $upload_dir . basename($tmpfile),
            $content['html'],
            $dbi,
            $argarray,
            $request
        );
    }

    /**
     * Prepares some rainbow colors for the nodes of the graph
     * and stores them in an array which may be accessed with
     * <code>getColor</code>.
     */
    public function createColors()
    {
        $predefcolors = array(
            array('red' => 255, 'green' => 0, 'blue' => 0),
            array('red' => 255, 'green' => 255, 'blue' => 0),
            array('red' => 0, 'green' => 255, 'blue' => 0),
            array('red' => 0, 'green' => 255, 'blue' => 255),
            array('red' => 0, 'green' => 0, 'blue' => 255),
            array('red' => 100, 'green' => 100, 'blue' => 100)
        );

        $steps = 2;
        $numberofcolors = count($predefcolors) * $steps;

        $promille = -1;
        foreach ($predefcolors as $color) {
            if ($promille < 0) {
                $oldcolor = $color;
                $promille = 0;
                continue;
            }
            for ($i = 0; $i < $steps; $i++) {
                $this->ColorTab[++$promille / $numberofcolors * 1000] = array(
                    floor(interpolate($oldcolor['red'], $color['red'], $i / $steps)),
                    floor(interpolate($oldcolor['green'], $color['green'], $i / $steps)),
                    floor(interpolate($oldcolor['blue'], $color['blue'], $i / $steps))
                );
            }
            $oldcolor = $color;
        }
        //echo"<pre>";  var_dump($this->ColorTab); echo "</pre>";
    }

    /**
     * Translates a value from 0.0 to 1.0 into rainbow color.
     * red -&gt; orange -&gt; green -&gt; blue -&gt; gray
     *
     * @param float $promille
     * @internal param float $promille value between 0.0 and 1.0
     * @return array(red,green,blue)
     */
    public function getColor($promille)
    {
        foreach ($this->ColorTab as $pro => $col) {
            if ($promille * 1000 < $pro) {
                return $col;
            }
        }
        return end($this->ColorTab);
    }
}

/**
 * Linear interpolates a value between two point a and b
 * at a value pos.
 * @param $a
 * @param $b
 * @param $pos
 * @return float  interpolated value
 */
function interpolate($a, $b, $pos)
{
    return $a + ($b - $a) * $pos;
}
