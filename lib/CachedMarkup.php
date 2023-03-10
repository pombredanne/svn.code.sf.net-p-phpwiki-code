<?php
/**
 * Copyright © 2002 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright © 2004-2010 $ThePhpWikiProgrammingTeam
 * Copyright © 2008-2009 Marc-Etienne Vargenau, Alcatel-Lucent
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

require_once 'lib/Units.php';

class CacheableMarkup extends XmlContent
{
    public function __construct($content, $basepage)
    {
        $this->_basepage = $basepage;
        $this->_buf = '';
        $this->_content = array();
        $this->_append($content);
        if ($this->_buf != '') {
            $this->_content[] = $this->_buf;
        }
        unset($this->_buf);
    }

    public function pack()
    {
        // FusionForge hack
        // This causes a strange bug when a comment containing
        // a single quote is entered in the Summary box:
        // - the history is wrong (user and comment missing)
        // - the table of contents plugin no longer works
        global $WikiTheme;
        if (is_a($WikiTheme, 'WikiTheme_fusionforge')) {
            return serialize($this);
        }

        return gzcompress(serialize($this), 9);
    }

    public static function unpack($packed)
    {
        if (!$packed) {
            return false;
        }

        // ZLIB format has a five bit checksum in its header.
        // Let's check for sanity.
        if (((ord($packed[0]) * 256 + ord($packed[1])) % 31 == 0)
            and (substr($packed, 0, 2) == "\037\213")
            or (substr($packed, 0, 2) == "x\332")
        ) { // 120, 218
            // Looks like ZLIB.
            $data = gzuncompress($packed);
            return unserialize($data);
        }
        if (substr($packed, 0, 2) == "O:") {
            // Looks like a serialized object
            return unserialize($packed);
        }
        if (preg_match("/^\w+$/", $packed)) {
            return $packed;
        }
        // happened with DebugBackendInfo problem also.
        trigger_error(
            "Can't unpack bad cached markup. Probably php_zlib extension not loaded.",
            E_USER_WARNING
        );
        return false;
    }

    /** Get names of wiki pages linked to.
     *
     * @return array of hashes { linkto=>pagename, relation=>pagename }
     */
    public function getWikiPageLinks()
    {
        $links = array();
        foreach ($this->_content as $item) {
            if (!is_a($item, 'Cached_DynamicContent')) {
                continue;
            }
            if (!($item_links = $item->getWikiPageLinks($this->_basepage))) {
                continue;
            }
            $links = array_merge($links, $item_links);
        }
        // array_unique has a bug with hashes!
        // set_links checks for duplicates, array_merge does not
        //return array_unique($links);
        return $links;
    }

    /** Get link info.
     *
     * This is here to support the XML-RPC listLinks() method.
     *
     * @return array
     * Returns an array of hashes.
     */
    public function getLinkInfo()
    {
        $links = array();
        foreach ($this->_content as $link) {
            if (!is_a($link, 'Cached_Link')) {
                continue;
            }
            $info = $link->getLinkInfo($this->_basepage);
            $links[$info->href] = $info;
        }
        return array_values($links);
    }

    public function _append($item)
    {
        if (is_array($item)) {
            foreach ($item as $subitem) {
                $this->_append($subitem);
            }
        } elseif (!is_object($item)) {
            $this->_buf .= $this->_quote((string)$item);
        } elseif (is_a($item, 'Cached_DynamicContent')) {
            if ($this->_buf) {
                $this->_content[] = $this->_buf;
                $this->_buf = '';
            }
            $this->_content[] = $item;
        } elseif (is_a($item, 'XmlElement')) {
            if ($item->isEmpty()) {
                $this->_buf .= $item->emptyTag();
            } else {
                $this->_buf .= $item->startTag();
                foreach ($item->getContent() as $subitem) {
                    $this->_append($subitem);
                }
                $this->_buf .= "</$item->_tag>";

                if (!$this->getDescription() and $item->getTag() == 'p') {
                    // performance: when is this really needed?
                    $this->_glean_description($item->asString());
                }
            }
            if (!$item->isInlineElement()) {
                $this->_buf .= "\n";
            }
        } elseif (is_a($item, 'XmlContent')) {
            foreach ($item->getContent() as $item) {
                $this->_append($item);
            }
        } elseif (method_exists($item, 'asXML')) {
            $this->_buf .= $item->asXML();
        } elseif (method_exists($item, 'asString')) {
            $this->_buf .= $this->_quote($item->asString());
        } else {
            $this->_buf .= sprintf("==Object(%s)==", get_class($item));
        }
    }

    public function _glean_description($text)
    {
        static $two_sentences;
        if (!$two_sentences) {
            $two_sentences = "[.?!][\")]*\s+[\"(]*[[:upper:])]"
                . ".*"
                . "[.?!][\")]*\s*[\"(]*([[:upper:])]|$)";
        }

        if (!isset($this->_description) and preg_match("/$two_sentences/sx", $text)) {
            $this->_description = preg_replace("/\s*\n\s*/", " ", trim($text));
        }
    }

    /**
     * Guess a short description of the page.
     *
     * Algorithm:
     *
     * This algorithm was suggested on MeatballWiki by
     * Alex Schroeder <kensanata@yahoo.com>.
     *
     * Use the first paragraph in the page which contains at least two
     * sentences.
     *
     * @see http://www.usemod.com/cgi-bin/mb.pl?MeatballWikiSuggestions
     *
     * @return string
     */
    public function getDescription()
    {
        return isset($this->_description) ? $this->_description : '';
    }

    public function asXML()
    {
        $xml = '';
        $basepage = $this->_basepage;

        foreach ($this->_content as $item) {
            if (is_string($item)) {
                $xml .= $item;
            } elseif (is_subclass_of($item, 'Cached_DynamicContent')
            ) {
                $val = $item->expand($basepage, $this);
                $xml .= $val->asXML();
            } else {
                $xml .= $item->asXML();
            }
        }
        return $xml;
    }

    public function printXML()
    {
        $basepage = $this->_basepage;
        // _content might be changed from a plugin (CreateToc)
        for ($i = 0; $i < count($this->_content); $i++) {
            $item = $this->_content[$i];
            if (is_string($item)) {
                print $item;
            } elseif (is_subclass_of($item, 'Cached_DynamicContent')
            ) { // give the content the chance to know about itself or even
                // to change itself
                $val = $item->expand($basepage, $this);
                if ($val) {
                    $val->printXML();
                }
            } else {
                $item->printXML();
            }
        }
    }
}

/**
 * The base class for all dynamic content.
 *
 * Dynamic content is anything that can change even when the original
 * wiki-text from which it was parsed is unchanged.
 */
abstract class Cached_DynamicContent
{
    public function cache(&$cache)
    {
        $cache[] = $this;
    }

    abstract public function expand($basepage, &$markup);

    public function getWikiPageLinks($basepage)
    {
        return array();
    }
}

class XmlRpc_LinkInfo
{
    public function __construct($page, $type, $href, $relation = '')
    {
        $this->page = $page;
        $this->type = $type;
        $this->href = $href;
        $this->relation = $relation;
        //$this->pageref = str_replace("/RPC2.php", "/index.php", $href);
    }
}

abstract class Cached_Link extends Cached_DynamicContent
{
    public $_url;
    public $_relation;

    public function isInlineElement()
    {
        return true;
    }

    /** Get link info (for XML-RPC support)
     *
     * This is here to support the XML-RPC listLinks method.
     * (See http://www.ecyrd.com/JSPWiki/Wiki.jsp?page=WikiRPCInterface)
     */
    public function getLinkInfo($basepage)
    {
        return new XmlRpc_LinkInfo(
            $this->_getName($basepage),
            $this->_getType(),
            $this->_getURL($basepage),
            $this->_getRelation($basepage)
        );
    }

    public function _getURL($basepage)
    {
        return $this->_url;
    }
}

/*
 * Defer interwiki inline links. img src=upload:xx.png
 * LinkImage($url, $alt = false)
 */
class Cached_InlinedImage extends Cached_DynamicContent
{
    public $_url;
    public $_basepage;

    public function isInlineElement()
    {
        return true;
    }

    public function _getURL($basepage)
    {
        return $this->_url;
    }

    // TODO: fix interwiki inline links in case of static dumps
    public function expand($basepage, &$markup)
    {
        global $WikiTheme;
        $this->_basepage = $basepage;
        $label = isset($this->_label) ? $this->_label : false;
        if ($WikiTheme->DUMP_MODE) {
            // In case of static dumps we need to check if we should
            // inline the image or not: external: keep link, internal: copy locally
            return LinkImage($label);
        } else {
            return LinkImage($label);
        }
    }
}

class Cached_WikiLink extends Cached_Link
{
    /**
     * @param string $page
     * @param string $label
     * @param string $anchor
     */
    public function __construct($page, $label = '', $anchor = '')
    {
        $this->_page = $page;
        /* ":DontStoreLink" */
        if (substr($this->_page, 0, 1) == ':') {
            $this->_page = substr($this->_page, 1);
            $this->_nolink = true;
        }
        if ($anchor) {
            $this->_anchor = $anchor;
        }
        if ($label and $label != $page) {
            $this->_label = $label;
        }
        $this->_basepage = false;
    }

    public function _getType()
    {
        return 'internal';
    }

    public function getPagename($basepage)
    {
        $page = new WikiPageName($this->_page, $basepage);
        if ($page->isValid()) {
            return $page->name;
        } else {
            return false;
        }
    }

    public function getWikiPageLinks($basepage)
    {
        if ($basepage == '') {
            return array();
        }
        if (isset($this->_nolink)) {
            return array();
        }
        if ($link = $this->getPagename($basepage)) {
            return array(array('linkto' => $link));
        } else {
            return array();
        }
    }

    public function _getName($basepage)
    {
        return $this->getPagename($basepage);
    }

    public function _getURL($basepage)
    {
        return WikiURL($this->getPagename($basepage));
    }

    public function expand($basepage, &$markup)
    {
        global $WikiTheme;
        $this->_basepage = $basepage;
        $label = isset($this->_label) ? $this->_label : false;
        $anchor = isset($this->_anchor) ? (string)$this->_anchor : '';
        $page = new WikiPageName($this->_page, $basepage, $anchor);
        if ($WikiTheme->DUMP_MODE and !empty($WikiTheme->VALID_LINKS)) {
            if (!in_array($this->_page, $WikiTheme->VALID_LINKS)) {
                return HTML($label ? $label : $page->getName());
            }
        }
        if ($page->isValid()) {
            return WikiLink($page, 'auto', $label);
        } else {
            return HTML($label);
        }
    }

    public function asXML()
    {
        global $WikiTheme;
        $label = isset($this->_label) ? $this->_label : false;
        $anchor = isset($this->_anchor) ? (string)$this->_anchor : '';
        //TODO: need basepage for subpages like /Remove (within CreateTOC)
        $page = new WikiPageName($this->_page, $this->_basepage, $anchor);
        if ($WikiTheme->DUMP_MODE and $WikiTheme->VALID_LINKS) {
            if (!in_array($this->_page, $WikiTheme->VALID_LINKS)) {
                return $label ? $label : $page->getName();
            }
        }
        $link = WikiLink($page, 'auto', $label);
        return $link->asXML();
    }

    public function asString()
    {
        if (isset($this->_label)) {
            return $this->_label;
        }
        return $this->_page;
    }
}

class Cached_WikiLinkIfKnown extends Cached_WikiLink
{
    public function __construct($moniker)
    {
        $this->_page = $moniker;
    }

    public function expand($basepage, &$markup)
    {
        global $WikiTheme;
        if ($WikiTheme->DUMP_MODE and $WikiTheme->VALID_LINKS) {
            if (!in_array($this->_page, $WikiTheme->VALID_LINKS)) {
                return HTML($label ? $label : $page->getName());
            }
        }
        return WikiLink($this->_page, 'if_known');
    }
}

class Cached_SpellCheck extends Cached_WikiLink
{
    public function __construct($word, $suggestions)
    {
        $this->_page = $word;
        $this->suggestions = $suggestions;
    }

    public function expand($basepage, &$markup)
    {
        return HTML::a(
            array('class' => 'spell-wrong',
                'title' => 'SpellCheck: ' . join(', ', $this->suggestions),
                'name' => $this->_page),
            $this->_page
        );
    }
}

class Cached_PhpwikiURL extends Cached_DynamicContent
{
    public $_page;

    public function __construct($url, $label)
    {
        $this->_url = $url;
        if ($label) {
            $this->_label = $label;
        }
    }

    public function isInlineElement()
    {
        return true;
    }

    public function expand($basepage, &$markup)
    {
        global $WikiTheme;
        $label = isset($this->_label) ? $this->_label : false;
        if ($WikiTheme->DUMP_MODE and $WikiTheme->VALID_LINKS) {
            if (!in_array($this->_page, $WikiTheme->VALID_LINKS)) {
                return HTML($label ? $label : $page->getName());
            }
        }
        return LinkPhpwikiURL($this->_url, $label, $basepage);
    }

    public function asXML()
    {
        $label = isset($this->_label) ? $this->_label : false;
        $link = LinkPhpwikiURL($this->_url, $label);
        return $link->asXML();
    }

    public function asString()
    {
        if (isset($this->_label)) {
            return $this->_label;
        }
        return $this->_url;
    }
}

/*
 * Relations (::) are named links to pages.
 * Attributes (:=) are named metadata per page, "named links to numbers with units".
 * We don't want to exhaust the linktable with numbers,
 * since this would create empty pages per each value,
 * so we don't store the attributes as full relationlink.
 * But we do store the attribute name as relation with an empty pagename
 * to denote that this is an attribute,
 * and to enable a fast listRelations mode=attributes
 */
class Cached_SemanticLink extends Cached_WikiLink
{
    public $_attribute;
    public $_attribute_base;
    public $_unit;

    public function __construct($url, $label = false)
    {
        $this->_url = $url;
        if ($label && $label != $url) {
            $this->_label = $label;
        }
        $this->_expandurl($this->_url);
    }

    public function isInlineElement()
    {
        return true;
    }

    public function getPagename($basepage)
    {
        if (!isset($this->_page)) {
            return false;
        }
        $page = new WikiPageName($this->_page, $basepage);
        if ($page->isValid()) {
            return $page->name;
        } else {
            return false;
        }
    }

    /* Add relation to the link table.
     * attributes have the _relation, but not the _page set.
     */
    public function getWikiPageLinks($basepage)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if ($basepage == '') {
            return array();
        }
        if (!isset($this->_page) and isset($this->_attribute)) {
            // An attribute: we store it in the basepage now, to fill the cache for page->save
            // TODO: side-effect free query
            $page = $request->getPage($basepage);
            $page->setAttribute($this->_relation, $this->_attribute);
            $this->_page = $basepage;
            return array(array('linkto' => '', 'relation' => $this->_relation));
        }
        if ($link = $this->getPagename($basepage)) {
            return array(array('linkto' => $link, 'relation' => $this->_relation));
        } else {
            return array();
        }
    }

    public function _expandurl($url)
    {
        $m = array();
        if (!preg_match('/^ ([^:]+) (:[:=]) (.+) $/x', $url, $m)) {
            return HTML::span(array('class' => 'error'), _("BAD semantic relation link"));
        }
        $this->_relation = urldecode($m[1]);
        $is_attribute = ($m[2] == ':=');
        if ($is_attribute) {
            $this->_attribute = urldecode($m[3]);
            // since this stored in the HTML cache, we are extra sensible
            // not to store false empty stuff.
            $units = new Units();
            if (!DISABLE_UNITS and !$units->errcode) {
                $this->_attribute_base = $units->Definition($this->_attribute);
                $this->_unit = $units->baseunit($this->_attribute);
            }
        } else {
            $this->_page = urldecode($m[3]);
        }
        return $m;
    }

    public function _expand($url, $label = false)
    {
        global $WikiTheme;
        $m = $this->_expandurl($url);
        // do not link to the attribute value, but to the attribute
        $is_attribute = ($m[2] == ':=');
        if ($WikiTheme->DUMP_MODE and $WikiTheme->VALID_LINKS) {
            if (isset($this->_page) and !in_array($this->_page, $WikiTheme->VALID_LINKS)) {
                return HTML($label ? $label : ($is_attribute ? $this->_relation : $this->_page));
            }
        }
        if ($is_attribute) {
            $title = isset($this->_attribute_base)
                ? sprintf(_("Attribute %s, base value: %s"), $this->_relation, $this->_attribute_base)
                : sprintf(_("Attribute %s, value: %s"), $this->_relation, $this->_attribute);
        }
        if ($label) {
            return HTML::span(
                HTML::a(
                    array('href' => WikiURL($is_attribute ? $this->_relation : $this->_page),
                        'class' => "wiki " . ($is_attribute ? "attribute" : "relation"),
                        'title' => $is_attribute
                            ? $title
                            : sprintf(_("Relation %s to page %s"), $this->_relation, $this->_page)),
                    $label
                )
            );
        } elseif ($is_attribute) {
            return HTML::span(
                HTML::a(
                    array('href' => WikiURL($this->_relation),
                        'class' => "wiki attribute",
                        'title' => $title),
                    $url
                )
            );
        } else {
            return HTML::span(
                HTML::a(
                    array('href' => WikiURL($this->_relation),
                        'class' => "wiki relation"),
                    $this->_relation
                ),
                HTML::span(array('class' => 'relation-symbol'), $m[2]),
                HTML::a(
                    array('href' => WikiURL($this->_page),
                        'class' => "wiki"),
                    $this->_page
                )
            );
        }
    }

    public function expand($basepage, &$markup)
    {
        $label = isset($this->_label) ? $this->_label : false;
        return $this->_expand($this->_url, $label);
    }

    public function asXML()
    {
        $label = isset($this->_label) ? $this->_label : false;
        $link = $this->_expand($this->_url, $label);
        return $link->asXML();
    }

    public function asString()
    {
        if (isset($this->_label)) {
            return $this->_label;
        }
        return $this->_url;
    }
}

/**
 * Highlight found search engine terms
 */
class Cached_SearchHighlight extends Cached_DynamicContent
{
    public function __construct($word, $engine)
    {
        $this->_word = $word;
        $this->engine = $engine;
    }

    public function expand($basepage, &$markup)
    {
        return HTML::span(
            array('class' => 'search-term',
                'title' => _("Found by ") . $this->engine),
            $this->_word
        );
    }
}

class Cached_ExternalLink extends Cached_Link
{
    public function __construct($url, $label = false)
    {
        $this->_url = $url;
        if ($label && $label != $url) {
            $this->_label = $label;
        }
    }

    public function _getType()
    {
        return 'external';
    }

    public function _getName($basepage)
    {
        $label = isset($this->_label) ? $this->_label : false;
        return ($label and is_string($label)) ? $label : $this->_url;
    }

    public function expand($basepage, &$markup)
    {
        global $request;

        $label = isset($this->_label) ? $this->_label : false;
        $link = LinkURL($this->_url, $label);

        if (GOOGLE_LINKS_NOFOLLOW) {
            // Ignores nofollow when the user who saved the page was authenticated.
            $page = $request->getPage($basepage);
            $current = $page->getCurrentRevision(false);
            if (!$current->get('author_id')) {
                $link->setAttr('rel', 'nofollow');
            }
        }
        return $link;
    }

    public function asString()
    {
        if (isset($this->_label) and is_string($this->_label)) {
            return $this->_label;
        }
        return $this->_url;
    }
}

class Cached_InterwikiLink extends Cached_ExternalLink
{
    public function __construct($link, $label = false)
    {
        $this->_link = $link;
        if ($label) {
            $this->_label = $label;
        }
    }

    public function getPagename($basepage)
    {
        list($moniker, $page) = explode(":", $this->_link, 2);
        $page = new WikiPageName($page, $basepage);
        if ($page->isValid()) {
            return $page->name;
        } else {
            return false;
        }
    }

    public function getWikiPageLinks($basepage)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if ($basepage == '') {
            return false;
        }
        /* ":DontStoreLink" */
        if (substr($this->_link, 0, 1) == ':') {
            return false;
        }
        /* store only links to valid pagenames */
        $dbi = $request->getDbh();
        if ($link = $this->getPagename($basepage) and $dbi->isWikiPage($link)) {
            return array(array('linkto' => $link));
        } else {
            return false; // dont store external links
        }
    }

    public function _getName($basepage)
    {
        $label = isset($this->_label) ? $this->_label : false;
        return ($label and is_string($label)) ? $label : $this->_link;
    }

    /* there may be internal interwiki links also */
    public function _getType()
    {
        return $this->getPagename(false) ? 'internal' : 'external';
    }

    public function _getURL($basepage)
    {
        $link = $this->expand($basepage, $this);
        return $link->getAttr('href');
    }

    public function expand($basepage, &$markup)
    {
        global $WikiTheme;
        $intermap = getInterwikiMap();
        $label = isset($this->_label) ? $this->_label : false;
        //FIXME: check Upload: inlined images
        if ($WikiTheme->DUMP_MODE and !empty($WikiTheme->VALID_LINKS)) {
            if (!in_array($this->_link, $WikiTheme->VALID_LINKS)) {
                return HTML($label ? $label : $this->_link);
            }
        }
        return $intermap->link($this->_link, $label);
    }

    public function asString()
    {
        if (isset($this->_label)) {
            return $this->_label;
        }
        return $this->_link;
    }
}

// Needed to put UserPages to backlinks. Special method to markup userpages with icons
// Thanks to Dan Frankowski for finding this bug.
// Fixed since 1.3.8, prev. versions had no userpages in backlinks
class Cached_UserLink extends Cached_WikiLink
{
    public function expand($basepage, &$markup)
    {
        $label = isset($this->_label) ? $this->_label : false;
        $anchor = isset($this->_anchor) ? (string)$this->_anchor : '';
        $page = new WikiPageName($this->_page, $basepage, $anchor);
        $link = WikiLink($page, 'auto', $label);
        // $link = HTML::a(array('href' => $PageName));
        $link->setContent(PossiblyGlueIconToText('wikiuser', $this->_page));
        $link->setAttr('class', 'wikiuser');
        return $link;
    }
}

/**
 * 1.3.13: Previously stored was only _pi.
 * A fresh generated cache has now ->name and ->args also.
 * main::isActionPage only checks the raw content.
 */
class Cached_PluginInvocation extends Cached_DynamicContent
{
    public function __construct($pi)
    {
        $this->_pi = $pi;
        $loader = $this->_getLoader();
        if (is_array($plugin_cmdline = $loader->parsePI($pi)) and $plugin_cmdline[1]) {
            $this->pi_name = $plugin_cmdline[0]; // plugin, plugin-form, plugin-list
            $this->name = $plugin_cmdline[1]->getName();
            $this->args = $plugin_cmdline[2];
        }
    }

    public function isInlineElement()
    {
        return false;
    }

    public function expand($basepage, &$markup)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $loader = $this->_getLoader();
        return $loader->expandPI($this->_pi, $request, $markup, $basepage);
    }

    public function asString()
    {
        return $this->_pi;
    }

    public function getWikiPageLinks($basepage)
    {
        $loader = $this->_getLoader();

        return $loader->getWikiPageLinks($this->_pi, $basepage);
    }

    public function & _getLoader()
    {
        static $loader = false;

        if (!$loader) {
            include_once 'lib/WikiPlugin.php';
            $loader = new WikiPluginLoader();
        }
        return $loader;
    }
}
