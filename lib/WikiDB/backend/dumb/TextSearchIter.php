<?php
/**
 * Copyright © 2001 Jeff Dairiki
 * Copyright © 2004-2005,2007 Reini Urban
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

class WikiDB_backend_dumb_TextSearchIter extends WikiDB_backend_iterator
{
    private $_backend;
    private $_pages;
    private $_fulltext;
    private $_search;

    /**
     * @var int
     */
    private $_index;

    private $_stoplist;

    /**
     * @var array
     */
    public $stoplisted;

    private $_from;
    private $_count;
    private $_exclude;

    public function __construct(
        $backend,
        $pages,
        $search,
        $fulltext = false,
        $options = array()
    )
    {
        $this->_backend = &$backend;
        $this->_pages = $pages;
        $this->_fulltext = $fulltext;
        $this->_search =& $search;
        $this->_index = 0;
        $this->_stoplist =& $search->_stoplist;
        $this->stoplisted = array();

        $this->_from = 0;
        if (isset($options['limit'])) { // extract from,count from limit
            list($this->_from, $this->_count) = WikiDB_backend::limit($options['limit']);
        } else {
            $this->_count = 0;
        }

        if (isset($options['exclude'])) {
            $this->_exclude = $options['exclude'];
        } else {
            $this->_exclude = false;
        }
    }

    public function _get_content(&$page)
    {
        $backend = &$this->_backend;
        $pagename = $page['pagename'];

        if (!isset($page['versiondata'])) {
            $version = $backend->get_latest_version($pagename);
            $page['versiondata'] = $backend->get_versiondata($pagename, $version, true);
        }
        return $page['versiondata']['%content'];
    }

    public function _match(&$page)
    {
        $text = $page['pagename'];
        if ($result = $this->_search->match($text)) { // first match the pagename only
            return $this->_search->score($text) * 2.0;
        }

        if ($this->_fulltext) {
            // eliminate stoplist words from fulltext search
            if (preg_match("/^" . $this->_stoplist . "$/i", $text)) {
                $this->stoplisted[] = $text;
                return $result;
            }
            $text .= "\n" . $this->_get_content($page);
            // Todo: Bonus for meta keywords (* 1.5) and headers
            if ($this->_search->match($text)) {
                return $this->_search->score($text);
            }
        } else {
            return $result;
        }
    }

    public function next()
    {
        $pages = &$this->_pages;
        while ($page = $pages->next()) {
            if ($score = $this->_match($page)) {
                $this->_index++;
                if (($this->_from > 0) and ($this->_index <= $this->_from)) {
                    // not yet reached the offset
                    continue;
                }
                /*if ($this->_count and ($this->_index > $this->_count)) {
                    // reached the limit, but need getTotal
                    $this->_count++;
                    return false;
                }*/
                if (is_array($page)) {
                    $page['score'] = $score;
                } else {
                    $page->score = $score;
                }
                return $page;
            }
        }
        return false;
    }

    public function free()
    {
        $this->_pages->free();
    }
}
