<?php // -*-php-*-
rcs_id('$Id: InterWikiSearch.php,v 1.1 2003-01-31 22:56:21 carstenklapp Exp $');
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
/**
 * @description
 */
require_once('lib/PageType.php');

class WikiPlugin_InterWikiSearch
extends WikiPlugin
{
    function getName() {
        return _("InterWikiSearch");
    }

    function getDescription() {
        return _("Perform searches on InterWiki sites listed in InterWikiMap.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array();
    }

    function run ($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);

        return PageType($pagerevisionhandle,
                        $pagename = _('InterWikiMap'),
                        $markup = 2,
                        $overridePageType = 'searcableInterWikiMapPageType');
    }
};


/**
 * @desc
 */
class searcableInterWikiMapPageType
extends interWikiMapPageType
{
    function _arrayToTable ($array, &$request) {
        $thead = HTML::thead();
        $label[0] = _("Wiki Name");
        $label[1] = _("Search");
        $thead->pushContent(HTML::tr(HTML::td($label[0]),
                                     HTML::td($label[1])));

        $tbody = HTML::tbody();
        $dbi = $request->getDbh();
        if ($array) {
            foreach ($array as $moniker => $interurl) {
                $monikertd = HTML::td(array('class' => 'interwiki-moniker'),
                                      $dbi->isWikiPage($moniker)
                                      ? WikiLink($moniker)
                                      : $moniker);

                $w = new WikiPluginLoader;
                $p = $w->getPlugin('ExternalSearch');
                $argstr = "url=" . $moniker; // is this the right way to pass args to a plugin?
                $searchtd = HTML::td($p->run(&$dbi, &$argstr, &$request));

                $tbody->pushContent(HTML::tr($monikertd, $searchtd));
            }
        }
        $table = HTML::table();
        $table->setAttr('class', 'interwiki-map');
        $table->pushContent($thead);
        $table->pushContent($tbody);

        return $table;
    }
};

// $Log: not supported by cvs2svn $

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
