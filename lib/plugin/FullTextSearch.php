<?php // -*-php-*-
rcs_id('$Id: FullTextSearch.php,v 1.15 2003-01-18 21:41:01 carstenklapp Exp $');
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

require_once('lib/TextSearchQuery.php');

/**
 */
class WikiPlugin_FullTextSearch
extends WikiPlugin
{
    function getName() {
        return _("FullTextSearch");
    }

    function getDescription() {
        return _("Search the content of all pages in this wiki.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.15 $");
    }

    function getDefaultArguments() {
        return array('s'        => false,
                     'noheader' => false);
        // TODO: multiple page exclude
    }


    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);

        $query = new TextSearchQuery($s);
        $pages = $dbi->fullSearch($query);
        $lines = array();
        $hilight_re = $query->getHighlightRegexp();
        $count = 0;
        $found = 0;

        $list = HTML::dl();

        while ($page = $pages->next()) {
            $count++;
            $name = $page->getName();
            $list->pushContent(HTML::dt(WikiLink($name)));
            if ($hilight_re)
                $list->pushContent($this->showhits($page, $hilight_re));
        }
        if (!$list->getContent())
            $list->pushContent(HTML::dd(_("<no matches>")));

        if ($noheader)
            return $list;

        return HTML(HTML::p(fmt("Full text search results for '%s'", $s)),
                    $list);
    }

    function showhits($page, $hilight_re) {
        $current = $page->getCurrentRevision();
        $matches = preg_grep("/$hilight_re/i", $current->getContent());
        $html = array();
        foreach ($matches as $line) {
            $line = $this->highlight_line($line, $hilight_re);
            $html[] = HTML::dd(HTML::small(array('class' => 'search-context'),
                                           $line));
        }
        return $html;
    }

    function highlight_line ($line, $hilight_re) {
        while (preg_match("/^(.*?)($hilight_re)/i", $line, $m)) {
            $line = substr($line, strlen($m[0]));
            $html[] = $m[1];    // prematch
            $html[] = HTML::strong(array('class' => 'search-term'), $m[2]); // match
        }
        $html[] = $line;        // postmatch
        return $html;
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
