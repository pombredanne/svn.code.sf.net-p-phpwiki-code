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
 * The Ploticus plugin passes all its arguments to the ploticus
 * binary and displays the result as PNG, GIF, EPS or SVG.
 * Ploticus is a free, GPL, non-interactive software package
 * for producing plots, charts, and graphics from data.
 * See http://ploticus.sourceforge.net/doc/welcome.html
 *
 * @Author: Reini Urban
 *
 * Note:
 * - For windows you need either a gd library with GIF support or
 *   a Ploticus with PNG support. This comes e.g. with the Cygwin build.
 * - We support only images supported by GD so far (PNG most likely).
 *   No EPS, PS, SVG or SVGZ support yet, due to limitations in WikiPluginCached.
 *   This will be fixed soon.
 *
 * Usage:
<<Ploticus device=png [ploticus options...]
multiline ploticus script ...
>>
 * or without any script: (not tested)
<<Ploticus -prefab vbars data=myfile.dat delim=tab y=1 clickmapurl="http://mywiki.url/wiki/?pagename=@2" clickmaplabel="@3" -csmap >>
 *
 * TODO: PloticusSql - create intermediate data from SQL. Similar to SqlResult, just in graphic form.
 * For example to produce nice looking pagehit statistics or ratings statistics.
 * Ploticus has its own sql support within #getproc data, but this would expose security information.
 */

if (!defined("PLOTICUS_EXE")) {
    if (isWindows()) {
        define('PLOTICUS_EXE', 'pl.exe');
    } else {
        define('PLOTICUS_EXE', '/usr/local/bin/pl');
    }
}
//TODO: check $_ENV['PLOTICUS_PREFABS'] and default directory

require_once 'lib/WikiPluginCached.php';

class WikiPlugin_Ploticus extends WikiPluginCached
{
    public $_args;
    public $source;
    public $_mapfile;
    public $_errortext;

    /**
     * Sets plugin type to MAP if -csmap (-map or -mapdemo or -csmapdemo not supported)
     * or HTML if the imagetype is not supported by GD (EPS, SVG, SVGZ) (not yet)
     * or IMG_INLINE if device = png, gif or jpeg
     */
    public function getPluginType()
    {
        if (!empty($this->_args['-csmap'])) {
            return PLUGIN_CACHED_MAP;
        } // not yet tested
        // produce these on-demand so far, uncached.
        // will get better support in WikiPluginCached soon.
        // FIXME: html also? what about ''?
        $type = $this->decideImgType($this->_args['device']);
        if ($type == $this->_args['device']) {
            return PLUGIN_CACHED_IMG_INLINE;
        }
        $device = strtolower($this->_args['device']);
        if (in_array($device, array('svg', 'svgz', 'eps', 'ps', 'pdf', 'html'))) {
            switch ($this->_args['device']) {
                case 'svg':
                case 'svgz':
                    return PLUGIN_CACHED_STATIC | PLUGIN_CACHED_SVG_PNG;
                default:
                    return PLUGIN_CACHED_STATIC | PLUGIN_CACHED_HTML;
            }
        } else {
            return PLUGIN_CACHED_IMG_INLINE;
        } // normal cached libgd image handles
    }

    public function getDescription()
    {
        return _("Ploticus image creation.");
    }

    public function managesValidators()
    {
        return true;
    }

    public function getDefaultArguments()
    {
        return array(
            'device' => 'png', // png,gif,svgz,svg,...
            '-prefab' => '',
            '-csmap' => false,
            'data' => false, // <!plugin-list !> support
            'alt' => false,
            'help' => false,
        );
    }

    public function handle_plugin_args_cruft($argstr, $args)
    {
        $this->source = $argstr;
    }

    /**
     * Sets the expire time to one day (so the image producing
     * functions are called seldomly) or to about two minutes
     * if a help screen is created.
     */
    public function getExpire($dbi, $argarray, $request)
    {
        if (!empty($argarray['help'])) {
            return '+120';
        } // 2 minutes
        return sprintf('+%d', 3 * 86000); // approx 3 days
    }

    /**
     * Sets the imagetype according to user wishes and
     * relies on WikiPluginCached to catch illegal image
     * formats.
     * (I feel unsure whether this option is reasonable in
     *  this case, because png will definitely have the
     *  best results.)
     *
     * @param WikiDB $dbi
     * @param array $argarray
     * @param Request $request
     * @return string 'png', 'jpeg', 'gif'
     */
    public function getImageType($dbi, $argarray, $request)
    {
        return $argarray['device'];
    }

    protected function getHtml($dbi, $argarray, $request, $basepage)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    /**
     * This gives an alternative text description of
     * the image.
     */
    public function getAlt($dbi, $argarray, $request)
    {
        return (!empty($this->_args['alt'])) ? $this->_args['alt']
            : $this->getDescription();
    }

    /**
     * Returns an image containing a usage description of the plugin.
     *
     * TODO: -csmap pointing to the Ploticus documentation at sf.net.
     * @return string image handle
     */
    public function helpImage()
    {
        $def = $this->defaultArguments();
        //$other_imgtypes = $GLOBALS['PLUGIN_CACHED_IMGTYPES'];
        //unset ($other_imgtypes[$def['imgtype']]);
        $helparr = array(
            '<<Ploticus ' .
                'device' => ' = "' . $def['device'] . "(default)|"
                . join('|', $GLOBALS['PLUGIN_CACHED_IMGTYPES']) . '"',
            'data' => ' <!plugin-list !>: pagelist as input',
            'alt' => ' = "alternate text"',
            '-csmap' => ' bool: clickable map?',
            'help' => ' bool: displays this screen',
            '...' => ' all further lines below the first plugin line ',
            '' => ' and inside the tags are the ploticus script.',
            "\n  >>"
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

    public function withShellCommand($script)
    {
        $findme = 'shell';
        $pos = strpos($script, $findme); // uppercase?
        if ($pos === false) {
            return 0;
        }
        return 1;
    }

    protected function getImage($dbi, $argarray, $request)
    {
        // Check device
        $device = strtolower($argarray['device']);
        if (!in_array($device, array('gif', 'png', 'jpeg', 'svg', 'svgz', 'eps', 'ps', 'pdf', 'html'))) {
            $this->_errortext = _("wrong device");
            return false;
        }
        $source =& $this->source;
        if (empty($source)) {
            $this->_errortext = _("empty source");
            return false;
        }
        if ($this->withShellCommand($source)) {
            $this->_errortext .= _("shell commands not allowed in Ploticus");
            return false;
        }
        if (is_array($argarray['data'])) { // support <!plugin-list !> pagelists
            $src = "#proc getdata\ndata:";
            $i = 0;
            foreach ($argarray['data'] as $data) {
                // hash or array?
                if (is_array($data)) {
                    $src .= ("\t" . join(" ", $data) . "\n");
                } else {
                    $src .= ("\t" . '"' . $data . '" ' . $i++ . "\n");
                }
            }
            $src .= $source;
            $source = $src;
        }
        $tempfile = $this->tempnam('Ploticus');
        @unlink($tempfile);
        $args = "-$device -o $tempfile.$device";
        if (!empty($argarray['-csmap'])) {
            $args .= " -csmap -mapfile $tempfile.map";
            $this->_mapfile = "$tempfile.map";
        }
        if (!empty($argarray['-prefab'])) {
            //check $_ENV['PLOTICUS_PREFABS'] and default directory
            global $HTTP_ENV_VARS;
            if (empty($HTTP_ENV_VARS['PLOTICUS_PREFABS'])) {
                if (file_exists("/usr/share/ploticus")) {
                    $HTTP_ENV_VARS['PLOTICUS_PREFABS'] = "/usr/share/ploticus";
                } elseif (defined('PLOTICUS_PREFABS')) {
                    $HTTP_ENV_VARS['PLOTICUS_PREFABS'] = constant('PLOTICUS_PREFABS');
                }
            }
            $args .= (" -prefab " . $argarray['-prefab']);
        }
        if (isWindows()) {
            $fp = fopen("$tempfile.plo", "w");
            fwrite($fp, $source);
            fclose($fp);
            $this->execute(PLOTICUS_EXE . " $tempfile.plo $args", $tempfile . ".$device");
        } else {
            $this->filterThroughCmd($source, PLOTICUS_EXE . " -stdin $args");
            sleep(1);
        }
        if (!file_exists($tempfile . ".$device")) {
            $this->_errortext .= sprintf(
                _("%s error: outputfile “%s” not created"),
                "Ploticus",
                "$tempfile.$device"
            );
            if (isWindows()) {
                $this->_errortext .= ("\ncmd-line: " . PLOTICUS_EXE . " $tempfile.plo $args");
            } else {
                $this->_errortext .= ("\ncmd-line: cat script | " . PLOTICUS_EXE . " $args");
            }
            @unlink("$tempfile.pl");
            @unlink("$tempfile");
            return false;
        }
        $ImageCreateFromFunc = "ImageCreateFrom$device";
        if (function_exists($ImageCreateFromFunc)) {
            $handle = $ImageCreateFromFunc("$tempfile.$device");
            if ($handle) {
                @unlink("$tempfile.$device");
                @unlink("$tempfile.plo");
                @unlink("$tempfile");
                return $handle;
            }
        }
        return "$tempfile.$device";
    }

    /**
     * Which argument must be set to 'png', for the fallback image when svg
     * will fail on the client.
     * type: SVG_PNG
     *
     * @return string
     */
    protected function pngArg()
    {
        return 'device';
    }

    protected function getMap($dbi, $argarray, $request)
    {
        $img = $this->getImage($dbi, $argarray, $request);
        return array($this->_mapfile, $img);
    }
}
