<?php rcs_id('$Id: PageType.php,v 1.2 2002-02-18 09:20:11 carstenklapp Exp $');
/*
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


include_once('lib/BlockParser.php');


/**
 * Get a PageType
 * 
 * usage:
 *
 * require_once('lib/PageType.php');
 * $transformedContent = PageType($pagerevisionhandle, $pagename, $markup);
 *
 * See http://phpwiki.sourceforge.net/phpwiki/PageType
 */
function PageType(&$rev, $pagename = false, $markup = false) {

    if (isa($rev, 'WikiDB_PageRevision')) {
        $text = $rev->getPackedContent();
        $pagename = $rev->_pagename; //is this _ok?
        $markup = $rev->get('markup');

    } else {
        // Hopefully only an edit preview gets us here, else we might be screwed.
        if ($pagename == false || $markup = false) {
            $error_text = "DEBUG: \$rev was not a 'WikiDB_PageRevision'. (Are you not previewing a page edit?)"; //debugging message only
            trigger_error($error_text, E_USER_NOTICE);
        }
        $text = $rev;
    }
    //echo $ContentTemplateName; //debugging
    //echo $text; //debugging


    // PageType currently only works with InterWikiMap.
    // Once a contentType field has been implemented in the
    // database then that can be used instead of this pagename check.
    switch($pagename) {
        case _("InterWikiMap"):
            $ContentTemplateName = 'interwikimap';
            break;
        default:
            $ContentTemplateName = 'wikitext';
    }

    $_ContentTemplates = array('wikitext' => new PageType($text, $markup),
                               'interwikimap' => new interWikiMapPageType($text, $markup));

    // Start making the actual content
    $content_template = $_ContentTemplates[$ContentTemplateName];
    return $content_template->getContent();
}


/**
 *
 */
class PageType {
    /**
     * This is a simple WikiPage
     */
    //var $_content = "";

    function PageType (&$content, $markup) {
        $this->_html = TransformText($content, $markup);
    }

    function getContent() {
        return $this->_html;
    }
};


class interWikiMapPageType extends PageType {
    var $_content = "";

    function interWikiMapPageType($content, $markup) {
        //echo $content; //debugging
        $this->_content = $content;
        $this->markup = $markup;
        $this->_html = HTML();

//        $this->_html->pushContent($this->_extractText());
//        $this->_html->pushContent($this->_getMap());

        $divs = array('interwikimap-header' => $this->_extractStartText(),
                      'interwikimap'        => $this->_getMap(),
                      'interwikimap-footer' => $this->_extractEndText());

        foreach ($divs as $class => $function)
            $this->_html->pushContent(HTML::div(array('class' => $class), $function));

    }

    function getContent() {
        return $this->_html;
    }

    function _getMap() {
        // plain text
        // return TransformText("<verbatim>" . $this->_extractMap() . "</verbatim>", $this->markup);
        global $request;
        // table with links
        //return $this->_arrayToTable($this->_extractMap(), $request);

        // let interwiki.php get the map
        include_once("lib/interwiki.php");
        $map = InterWikiMap::GetMap($request);
        return $this->_arrayToTable($map->_map, $request);
    }

    function _arrayToTable ($array, &$request) {
        $dbi = $request->getDbh();
        $table = HTML::table();
        foreach ($array as $moniker => $url) {
            if ($dbi->isWikiPage($moniker)) {
                $moniker = WikiLink($moniker);
            }
            $table->pushContent(HTML::tr(HTML::td($moniker), HTML::td(HTML::pre($url))));
        }
        return $table;
    }

    function _extractStartText() {
        // cut the map out of the text
        $v = strpos($this->_content, "<verbatim>");
        if ($v) {
            list($wikitext, $cruft) = explode("<verbatim>", $this->_content);
            //echo $cruft; //debugging
        } else {
            $wikitext = $this->_content;
        }
        return TransformText($wikitext, $this->markup);
    }

    function _extractEndText() {
        // cut the map out of the text
        $v = strpos($this->_content, "</verbatim>");
        if ($v) {
            list($cruft, $endtext) = explode("</verbatim>", $this->_content);
            //echo $cruft; //debugging
            return TransformText($endtext, $this->markup);
        } else {
            return "";
        }
    }

    /*
    function _extractMap() {
        if (preg_match('|^<verbatim>\n(.*)^</verbatim>|ms',
                    $this->_content['rawmarkup'], $m)) {
            $maptext = $m[1];
        }
        //return $maptext;
        global $AllowedProtocols;
        if (!preg_match_all("/^\s*(\S+)\s+(\S+)/m",
                            $maptext, $matches, PREG_SET_ORDER))
            return false;
        foreach ($matches as $m) {
            $map[$m[1]] = $m[2];
        }
        return $map;
    }
    */
};


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
