<?php
/*
 * Copyright © 2009 Roger Guignard and Marc-Etienne Vargenau, Alcatel-Lucent
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
 * "The VideoPlugin ("Contribution") has not been tested and/or
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

class WikiPlugin_Video extends WikiPlugin
{
    public function getDescription()
    {
        return _("Display video in HTML5.");
    }

    public function getDefaultArguments()
    {
        return array('width' => 460,
            'height' => 320,
            'url' => '',
            'file' => '',
            'autoplay' => 'false'
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
        global $WikiTheme;
        $args = $this->getArgs($argstr, $request);
        $url = $args['url'];
        $file = $args['file'];
        $width = $args['width'];
        $height = $args['height'];
        $autoplay = $args['autoplay'];

        if (!is_bool($autoplay)) {
            if (($autoplay == '0') || ($autoplay == 'false')) {
                $autoplay = false;
            } elseif (($autoplay == '1') || ($autoplay == 'true')) {
                $autoplay = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "autoplay"));
            }
        }

        if (!$url && !$file) {
            return $this->error(sprintf(_("Both '%s' and '%s' parameters missing."), 'url', 'file'));
        } elseif ($url && $file) {
            return $this->error(sprintf(_("Choose only one of '%s' or '%s' parameters."), 'url', 'file'));
        } elseif ($file) {
            // $url = SERVER_URL . getUploadDataPath() . '/' . $file;
            $url = getUploadDataPath() . '/' . $file;
        }

        if (string_ends_with($url, ".ogg")
           || string_ends_with($url, ".mp4")
           || string_ends_with($url, ".webm")) {
            $video = HTML::video(
                array('controls' => 'controls',
                                       'width' => $width,
                                       'height' => $height,
                                       'src' => $url),
                _("Your browser does not understand the HTML 5 video tag.")
            );
            if ($autoplay == 'true') {
                $video->setAttr('autoplay', 'autoplay');
            }
            return $video;
        }
        return HTML::span(
            array('class' => 'error'),
            _("Unknown video format")
        );
    }
}
