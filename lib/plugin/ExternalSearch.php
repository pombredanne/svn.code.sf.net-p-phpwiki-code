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
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

/**
 * Redirect to an external web site based on form input.
 *
 * useimage sample:
ExternalSearch
url="http://www.geourl.org/near/?xsize=2048&ysize=1024&xoffset=1650&yoffset=550"
useimage="http://www.geourl.org/maps/au.png"
name="Go Godzilla All Over It"
 */

if (!defined("EXTERNALSEARCH_DEFAULT_BUTTON_POSITION")) {
    define("EXTERNALSEARCH_DEFAULT_BUTTON_POSITION", "right");
}

class WikiPlugin_ExternalSearch extends WikiPlugin
{
    public $_url;
    public $_name;
    public $_s;

    public function getDescription()
    {
        return _("Redirect to an external web site based on form input.");
        //fixme: better description
    }

    private function getInterWikiUrl()
    {
        $intermap = getInterwikiMap();
        $map = $intermap->_map;

        if (in_array($this->_url, array_keys($map))) {
            if (empty($this->_name)) {
                $this->_name = $this->_url;
            }
            $this->_url = sprintf($map[$this->_url], '%s');
        }
        if (empty($this->_name)) {
            $this->_name = $this->getName();
        }
    }

    public function getDefaultArguments()
    {
        return array('s' => false,
            'formsize' => 30,
            'url' => false,
            'name' => '',
            'useimage' => false,
            'width' => false,
            'height' => false,
            'debug' => false,
            'button_position' => EXTERNALSEARCH_DEFAULT_BUTTON_POSITION,
            // 'left' or 'right'
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
        if (empty($args['url'])) {
            return '';
        }

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

        $posted = $GLOBALS['HTTP_POST_VARS'];
        if (in_array('url', array_keys($posted))) {
            $s = $posted['s'];
            $this->_url = $posted['url'];
            $this->getInterWikiUrl();
            if (strstr($this->_url, '%s')) {
                $this->_url = sprintf($this->_url, $s);
            } else {
                $this->_url .= $s;
            }
            if (defined('DEBUG') && DEBUG && $debug) {
                trigger_error("redirect url: " . $this->_url);
            } else {
                $request->redirect($this->_url); //no return!
            }
        }
        $this->_name = $name;
        $this->_s = $s;
        if ($formsize < 1) {
            $formsize = 30;
        }
        $this->_url = $url;
        $this->getInterWikiUrl();
        $form = HTML::form(
            array('action' => $request->getPostURL(),
                'method' => 'post',
                //'class'  => 'class', //fixme
                'accept-charset' => 'UTF-8'),
            HiddenInputs(array('pagename' => $basepage))
        );

        $form->pushContent(HTML::input(array('type' => 'hidden',
            'name' => 'url',
            'value' => $this->_url)));
        $s = HTML::input(array('type' => 'text',
            'value' => $this->_s,
            'name' => 's',
            'size' => $formsize));
        if (!empty($args["useimage"])) {
            //FIXME: This does not work with Gecko
            $button = HTML::img(array('src' => $useimage, 'alt' => 'imagebutton'));
            if (!empty($width)) {
                $button->setAttr('width', $width);
            }
            if (!empty($height)) {
                $button->setAttr('height', $height);
            }
            // on button_position => none display no input form
            if ($button_position == 'right') {
                $form->pushContent($s);
            }
            $form->pushContent(HTML::button(
                array('type' => 'button',
                    'class' => 'button',
                    'value' => $this->_name,
                ),
                $button
            ));
            if ($button_position == 'left') {
                $form->pushContent($s);
            }
        } else {
            if ($button_position != 'left' and $button_position != 'right') {
                return $this->error(fmt(
                    "Invalid argument: %s=%s",
                    'button_position',
                    $button_position
                ));
            }
            $button = HTML::input(array('type' => 'submit',
                'class' => 'button',
                'value' => $this->_name));
            if ($button_position == 'left') {
                $form->pushContent($button);
                $form->pushContent($s);
            } elseif ($button_position == 'right') {
                $form->pushContent($s);
                $form->pushContent($button);
            }
        }
        return $form;
    }
}
