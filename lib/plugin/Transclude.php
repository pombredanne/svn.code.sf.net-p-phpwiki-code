<?php
/**
 * Copyright © 1999,2000,2001,2002,2006 $ThePhpWikiProgrammingTeam
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
 * SPDX-License-Identifier: GPL-2.0+
 *
 */

/**
 * Transclude:  Include an external web page within the body of a wiki page.
 *
 * Usage:
 *  <<Transclude
 *           src=http://www.internet-technology.de/fourwins_de.htm
 *  >>
 *
 * @author Geoffrey T. Dairiki
 *
 * @see http://www.cs.tut.fi/~jkorpela/html/iframe.html
 *
 * KNOWN ISSUES
 *  The auto-vertical resize javascript code only works if the transcluded
 *  page comes from the PhpWiki server.  Otherwise (due to "tainting"
 *  security checks in JavaScript) I can't figure out how to deduce the
 *  height of the transcluded page via JavaScript... :-/
 *
 *  Sometimes the auto-vertical resize code doesn't seem to make the iframe
 *  quite big enough --- the scroll bars remain.  Not sure why.
 */

class WikiPlugin_Transclude
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Include an external web page within the body of a wiki page.");
    }

    function getDefaultArguments()
    {
        return array('src' => false, // the src url to include
            'title' => _("Transcluded page"), // title of the iframe
            'height' => 450, // height of the iframe
            'quiet' => false // if set, iframe appears as normal content
        );
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

        $args = ($this->getArgs($argstr, $request));
        extract($args);

        if (!$src) {
            return $this->error(fmt("%s parameter missing", "'src'"));
        }
        // Expand possible interwiki link for src
        if (strstr($src, ':')
            and (!strstr($src, '://'))
                and ($intermap = getInterwikiMap())
                    and preg_match("/^" . $intermap->getRegexp() . ":/", $src)
        ) {
            $link = $intermap->link($src);
            $src = $link->getAttr('href');
        }

        // FIXME: Better recursion detection.
        // FIXME: Currently this doesnt work at all.
        if ($src == $request->getURLtoSelf()) {
            return $this->error(fmt("Recursive inclusion of url %s", $src));
        }
        if (!IsSafeURL($src)) {
            return $this->error(_("Bad URL in src"));
        }

        $params = array('title' => $title,
            'src' => $src,
            'width' => "100%",
            'height' => $height,
            'marginwidth' => 0,
            'marginheight' => 0,
            'class' => 'autoHeight transclude');

        $noframe_msg[] = fmt("See: %s", HTML::a(array('href' => $src), $src));

        $noframe_msg = HTML::div(array('class' => 'transclusion'),
            HTML::p(array(), $noframe_msg));

        $iframe = HTML::iframe($params, $noframe_msg);

        /* This doesn't work very well...  maybe because CSS screws up NS4 anyway...
        $iframe = new HtmlElement('ilayer', array('src' => $src), $iframe);
        */

        if ($quiet) {
            return $iframe;
        } else {
            return HTML(HTML::p(array('class' => 'transclusion-title'),
                    fmt("Transcluded from %s", LinkURL($src))), $iframe);
        }
    }
}
