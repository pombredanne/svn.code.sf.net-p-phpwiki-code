<?php // -*-php-*-
rcs_id('$Id: ExternalSearch.php,v 1.3 2003-01-18 21:41:01 carstenklapp Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once("lib/interwiki.php");

/**
 */
class WikiPlugin_ExternalSearch
extends WikiPlugin
{
    function getName () {
        return _("ExternalSearch");
    }

    function getDescription () {
        return _("Redirects to an external web site based on form input");
        //fixme: better description
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }

    function _getInterWikiUrl(&$request) {
        $intermap = InterWikiMap::GetMap($request);
        $map = $intermap->_map;

        if (in_array($this->_url, array_keys($map))) {
            if (empty($this->_name))
                $this->_name = $this->_url;
            $this->_url = sprintf($map[$this->_url],'%s');
        }
        if (empty($this->_name))
            $this->_name = $this->getName();
    }

    function getDefaultArguments() {
        return array('s'        => false,
                     'formsize' => 30,
                     'url'      => false,
                     'name'     => '',
                     'debug'    => false
                     );
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (empty($args['url']))
            return '';

        extract($args);

        $posted = $GLOBALS['HTTP_POST_VARS'];
        if (in_array('url', array_keys($posted))) {
            $s = $posted['s'];
            $this->_url = $posted['url'];
            $this->_getInterWikiUrl($request);
            if (strstr($this->_url, '%s')) {
                $this->_url = sprintf($this->_url, $s);
            } else
                $this->_url .= $s;

            if ($debug) {
                trigger_error("redirect url: " . $this->_url);
            } else
                $request->redirect($this->_url); //no return!
        }

        $this->_name = $name;

        $this->_s = $s;
        if ($formsize < 1)
            $formsize = 30;
        $this->_url = $url;

        $this->_getInterWikiUrl($request);

        $form = HTML::form(array('action' => $this->getname(),
                                 'method' => 'POST',
                                 //'class'  => 'class', //fixme
                                 'accept-charset' => CHARSET));

        $form->pushContent(HTML::input(array('type' => 'text',
                                             'value' => $this->_s,
                                             'name'  => 's',
                                             'size'  => $formsize)));

        $form->pushContent(HTML::input(array('type' => 'hidden',
                                             'name'  => 'url',
                                             'value' => $this->_url)));

        $form->pushContent(HTML::input(array('type' => 'submit',
                                             'class' => 'button',
                                             'value' => $this->_name)));
        return $form;
    }
};

// $Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
