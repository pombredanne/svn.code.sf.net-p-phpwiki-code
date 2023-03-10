<?php
/**
 * Copyright © 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam
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

// +---------------------------------------------------------------------+
// | TexToPng.php                                                        |
// +---------------------------------------------------------------------+
// | This is a WikiPlugin that surrounds tex commands given as parameter |
// | with a page description and renders it using several existing       |
// | engines into a gif, png or jpeg file.                               |
// | TexToPng is usage example for WikiPluginCached.                     |
// |                                                                     |
// | You may copy this code freely under the conditions of the GPL       |
// +---------------------------------------------------------------------+

/*-----------------------------------------------------------------------
 | CONFIGURATION
 *----------------------------------------------------------------------*/
// needs (la)tex, dvips, gs, netpbm, libpng
// LaTeX2HTML ftp://ftp.dante.de/tex-archive/support/latex2html

// output mere debug messages (should be set to false in a stable
// version)
define('TexToPng_debug', false);

/*-----------------------------------------------------------------------
 | OPTION DEFAULTS
 *----------------------------------------------------------------------*/
/*----
 | use antialias for rendering;
 | anitalias: blurs, _looks better_, needs twice space, renders slowlier
 |                                                                      */
define('TexToPng_antialias', true);

/*----
 | Use transparent background; dont combine with antialias on a dark
 | background. Seems to have a bug: produces strange effects for some
 | ps-files (almost non readable,blurred output) even when directly
 | invoked from shell. So its probably a pstoimg bug.
 |                                                                      */
define('TexToPng_transparent', false);

/*----
 | default value for rescaling
 | allowed range: 0 - 5 (integer)
 |                                                                      */
define('TexToPng_magstep', 3);

/*-----------------------------------------------------------------------
 |
 |  Source
 |
 *----------------------------------------------------------------------*/

/*-----------------------------------------------------------------------
 | WikiPlugin_TexToPng
 *----------------------------------------------------------------------*/

require_once 'lib/WikiPluginCached.php';

class WikiPlugin_TexToPng extends WikiPluginCached
{
    public $_errortext;

    public function getPluginType()
    {
        return PLUGIN_CACHED_IMG_ONDEMAND;
    }

    public function getDescription()
    {
        return _("Converts TeX to an image. May be used to embed formulas in PhpWiki.");
    }

    public function getDefaultArguments()
    {
        return array('tex' => "",
            'magstep' => TexToPng_magstep,
            'img' => 'png',
            'subslash' => 'off',
            'antialias' => TexToPng_antialias ? 'on' : 'off',
            'transparent' => TexToPng_transparent ? 'on' : 'off',
            'center' => 'off');
    }

    protected function getImage($dbi, $argarray, $request)
    {
        extract($argarray);
        $this->checkParams($tex, $magstep, $subslash, $antialias, $transparent);
        return $this->TexToImg($tex, $magstep, $antialias, $transparent);
    }

    protected function getMap($dbi, $argarray, $request)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    protected function getHtml($dbi, $argarray, $request, $basepage)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    public function getExpire($dbi, $argarray, $request)
    {
        return '0';
    }

    public function getImageType($dbi, $argarray, $request)
    {
        extract($argarray);
        return $img;
    }

    public function getAlt($dbi, $argarray, $request)
    {
        extract($argarray);
        return $tex;
    }

    public function embedImg($url, $dbi, $argarray, $request)
    {
        $html = HTML::img(array(
            'src' => $url,
            'alt' => htmlspecialchars($this->getAlt($dbi, $argarray, $request))
        ));
        if ($argarray['center'] == 'on') {
            return HTML::div(array('style' => 'text-align:center;'), $html);
        }
        return $html;
    }

    /* -------------------- error handling ---------------------------- */

    public function dbg($out)
    {
        // test if verbose debug info is selected
        if (TexToPng_debug) {
            $this->complain($out . "\n");
        } else {
            if (!$this->_errortext) {
                // yeah, I've been told to be quiet, but obviously
                // an error occurred. So at least complain silently.
                $this->complain(' ');
            }
        }
    } // dbg

    /* -------------------- parameter handling ------------------------ */

    public function helptext()
    {
        $aa = TexToPng_antialias ? 'on(default)$|$off' : 'on$|$off(default)';
        $tp = TexToPng_transparent ? 'on(default)$|$off' : 'on$|$off(default)';
        $help =
            '/settabs/+/indent&$<$?plugin /bf{Tex} & [{/tt transparent}] & = "png(default)$|$jpeg$|$gif"& /cr' . "\n" .
                '/+&$<$?plugin /bf{TexToPng} & /hfill {/tt tex}           & = "/TeX/  commands"& /cr' . "\n" .
                '/+&                         & /hfill [{/tt img}]         & = "png(default)$|$jpeg$|$gif"& /cr' . "\n" .
                '/+&                         & /hfill [{/tt magstep}]     & = "0 to 5 (' . TexToPng_magstep . ' default)"& /cr' . "\n" .
                '/+&                         & /hfill [{/tt center}]      & = "on$|$off(default)"& /cr' . "\n" .
                '/+&                         & /hfill [{/tt subslash}]    & = "on$|$off(default)"& /cr' . "\n" .
                '/+&                         & /hfill [{/tt antialias}]   & = "' . $aa . '"& /cr' . "\n" .
                '/+&                         & /hfill [{/tt transparent}] & = "' . $tp . '"&?$>$ /cr' . "\n";

        return strtr($help, '/', '\\');
    } // helptext

    public function checkParams(&$tex, &$magstep, $subslash, &$aalias, &$transp)
    {
        if ($subslash == 'on') {
            // WORKAROUND for backslashes
            $tex = strtr($tex, '/', '\\');
        }

        // ------- check parameters
        $def = $this->getDefaultArguments();

        if ($tex == '') {
            $tex = $this->helptext();
        }

        if ($magstep < 0 || $magstep > 5) {
            $magstep = $def["magstep"];
        }
        // calculate magnification factor
        $magstep = floor(10 * pow(1.2, $magstep)) / 10;

        $aalias = $aalias != 'off';
        $transp = $transp != 'off';
    } // checkParams

    /* ------------------ image creation ------------------------------ */

    public function execute($cmd, $complainvisibly = false)
    {
        exec($cmd, $errortxt, $returnval);
        $ok = $returnval == 0;

        if (!$ok) {
            if (!$complainvisibly) {
                $this->dbg('Error during execution of ' . $cmd);
            }
            foreach ($errortxt as $key => $value) {
                if ($complainvisibly) {
                    $this->complain($value . "\n");
                } else {
                    $this->dbg($value);
                }
            }
        }
        return $ok;
    } // execute

    /* ---------------------------------------------------------------- */

    public function createTexFile($texfile, $texstr)
    {
        if ($ok = ($fp = fopen($texfile, 'w')) != 0) {
            // prepare .tex file
            $texcommands =
                '\nopagenumbers' . "\n" .
                    '\hoffset=0cm' . "\n" .
                    '\voffset=0cm' . "\n" .
                    //    '\hsize=20cm'    . "\n" .
                    //    '\vsize=10ex'    . "\n" .
                    $texstr . "\n" .
                    '\vfill\eject' . "\n" .
                    '\end' . "\n\n";

            $ok = fwrite($fp, $texcommands);
            $ok = fclose($fp) && $ok; // close anyway
        }
        if (!$ok) {
            $this->dbg('could not write .tex file: ' . $texstr);
        }
        return $ok;
    } // createTexFile

    /* ---------------------------------------------------------------- */

    public function TexToImg($texstr, $scale, $aalias, $transp)
    {
        $texbin = '/usr/bin/tex';
        $dvipsbin = '/usr/bin/dvips';
        $pstoimgbin = '/usr/bin/pstoimg';
        $cache_dir = '/tmp/cache';
        $tempfiles = $this->tempnam('TexToPng');
        $img = 0; // $size = 0;

        // produce options for pstoimg
        $options =
            ($aalias ? '-aaliastext -color 8 ' : '-color 1 ') .
                ($transp ? '-transparent ' : '') .
                '-scale ' . $scale . ' ' .
                '-type png -crop btlr -geometry 600x150 -margins 0,0';

        // rely on intelligent bool interpretation
        $ok = $tempfiles &&
            $this->createTexFile($tempfiles . '.tex', $texstr) &&
            $this->execute('cd ' . $cache_dir . '; ' .
                "$texbin " . $tempfiles . '.tex', true) &&
            $this->execute("$dvipsbin -o" . $tempfiles . '.ps ' . $tempfiles . '.dvi') &&
            $this->execute("$pstoimgbin $options"
                . ' -out ' . $tempfiles . '.png ' .
                $tempfiles . '.ps') &&
            file_exists($tempfiles . '.png');

        if ($ok) {
            if (!($img = imagecreatefrompng($tempfiles . '.png'))) {
                $this->dbg("Could not open just created image file: $tempfiles");
                $ok = false;
            }
        }

        // clean up tmpdir; in debug mode only if no error occured

        if (!TexToPng_debug || (TexToPng_debug && $ok)) {
            if ($tempfiles) {
                if (file_exists($tempfiles)) {
                    unlink($tempfiles);
                }
                if (file_exists($tempfiles . '.ps')) {
                    unlink($tempfiles . '.ps');
                }
                if (file_exists($tempfiles . '.tex')) {
                    unlink($tempfiles . '.tex');
                }
                if (file_exists($tempfiles . '.dvi')) {
                    unlink($tempfiles . '.dvi');
                }
                if (file_exists($tempfiles . '.log')) {
                    unlink($tempfiles . '.log');
                }
                if (file_exists($tempfiles . '.png')) {
                    unlink($tempfiles . '.png');
                }
            }
        }

        if ($ok) {
            return $img;
        }
        return false;
    } // TexToImg
}
