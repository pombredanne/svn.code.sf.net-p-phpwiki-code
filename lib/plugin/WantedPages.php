<?php // -*-php-*-
rcs_id('$Id: WantedPages.php,v 1.1 2002-02-24 20:18:36 carstenklapp Exp $');
/*
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
 * A plugin which returns a list of referenced pages which do not exist yet.
 * 
 **/
//require_once('lib/PageList.php');

/**
 */
class WikiPlugin_WantedPages
extends WikiPlugin
{
    function getName () {
        return _("WantedPages");
    }

    function getDescription () {
        return _("Wanted Pages");
    }

    function getDefaultArguments() {
        return array('noheader' => false,
                     'exclude'  => _("PgsrcTranslation"),
                     'page'     => '');
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        if ($exclude) {
            if (!is_array($exclude))
                $exclude = explode(',', $exclude);
        }

        // The PageList class can't handle the 'count' column needed for this table
        $pagelist = array();

        // There's probably a more efficient way to do this (eg a tailored SQL query via the
        // backend, but this does the job

        if ($page) { //only get WantedPages links for one page
            $page_handle = $dbi->getPage($page);
            $links_iter = $page_handle->getLinks($reversed = false);
            while ($link_handle = $links_iter->next())
            {
                if (! $dbi->isWikiPage($linkname = $link_handle->getName()))
                    if (! in_array($linkname, array_keys($pagelist)))
                        $pagelist[$linkname] = 1;
                    else
                        $pagelist[$linkname] += 1;
            }
        }
        else {
            $allpages_iter = $dbi->getAllPages($include_empty = false);
            while ($page_handle = $allpages_iter->next())
            {
                $name = $page_handle->getName();
                if (! in_array($name, $exclude))
                {
                    $links_iter = $page_handle->getLinks($reversed = false);
                    while ($link_handle = $links_iter->next())
                    {
                        if (! $dbi->isWikiPage($linkname = $link_handle->getName()))
                            if (! in_array($linkname, array_keys($pagelist)))
                                $pagelist[$linkname] = 1;
                            else
                                $pagelist[$linkname] += 1;
                    }
                }
            }
        }
        ksort($pagelist);
        arsort($pagelist);

        if ($page) // link count always seems to be 1 for a single page
            $this->_columns = array(_("Page Name"));
        else
            $this->_columns = array(_("Count"), _("Page Name"));

        if (!$noheader) {
            $c = count($pagelist);
            if ($page)
                $caption = sprintf(_("Wanted Pages for %s (%d total):"), $page, $c);
            else
            $caption = sprintf(_("Wanted Pages in this wiki (%d total):"), $c);
        } else
            $caption = false;

        $this->_rows = HTML();
        $spacer = new RawXml("&nbsp;&nbsp;&nbsp;&nbsp;");
        foreach ($pagelist as $key => $val) {
            if ($page)
                $row = HTML::li(WikiLink($key, 'unknown'));
            else
                $row = HTML::tr(HTML::td(array('align' => 'right'), $val),
                                HTML::td(HTML($spacer, WikiLink($key, 'unknown'))));
            $this->_rows->pushContent($row);
        }

        if ($page)
            return $this->_generateList($caption);
        else
            return $this->_generateTable($caption);
    }

    function _generateTable($caption) {
        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class'       => 'pagelist'));
        if ($caption)
            $table->pushContent(HTML::caption(array('align'=>'top'), $caption));

        $row = HTML::tr();
        $spacer = new RawXml("&nbsp;&nbsp;&nbsp;&nbsp;");
        foreach ($this->_columns as $col_heading) {
            $row->pushContent(HTML::td(HTML($spacer, HTML::u($col_heading))));
            $table_summary[] = $col_heading;
        }
        // Table summary for non-visual browsers.
        $table->setAttr('summary', sprintf(_("Columns: %s."), implode(", ", $table_summary)));

        $table->pushContent(HTML::thead($row),
                            HTML::tbody(false, $this->_rows));
        return $table;
    }

    function _generateList($caption) {
        $list = HTML();
        if ($caption)
            $list->pushContent(HTML::p($caption));
        $list->pushContent(HTML::ul($this->_rows));
        return $list;
    }
};


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
