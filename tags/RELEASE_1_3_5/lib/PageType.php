<?php // -*-php-*-
rcs_id('$Id: PageType.php,v 1.18 2003-02-21 04:18:06 dairiki Exp $');
/*
 Copyright 1999, 2000, 2001, 2002, 2003 $ThePhpWikiProgrammingTeam

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

require_once('lib/CachedMarkup.php');

/** A cacheable formatted wiki page.
 */
class TransformedText extends CacheableMarkup {
    /** Constructor.
     *
     * @param WikiDB_Page $page
     * @param string $text  The packed page revision content.
     * @param hash $meta    The version meta-data.
     * @param string $type_override  For markup of page using a different
     *        pagetype than that specified in its version meta-data.
     */
    function TransformedText($page, $text, $meta, $type_override=false) {
        @$pagetype = $meta['pagetype'];
        if ($type_override)
            $pagetype = $type_override;
	$this->_type = PageType::GetPageType($pagetype);
	$this->CacheableMarkup($this->_type->transform($page, $text, $meta),
                               $page->getName());
    }

    function getType() {
	return $this->_type;
    }
}

/**
 * A page type descriptor.
 *
 * Encapsulate information about page types.
 *
 * Currently the only information encapsulated is how to format
 * the specific page type.  In the future or capabilities may be
 * added, e.g. the abilities to edit different page types (differently.)
 *
 * IMPORTANT NOTE: Since the whole PageType class gets stored (serialized)
 * as of the cached marked-up page, it is important that the PageType classes
 * not have large amounts of class data.  (No class data is even better.)
 */
class PageType {
    /**
     * Get a page type descriptor.
     *
     * This is a static member function.
     *
     * @param string $pagetype  Name of the page type.
     * @return PageType  An object which is a subclass of PageType.
     */
    function GetPageType ($name=false) {
        if (!$name)
            $name = 'wikitext';
        if ($name) {
            $class = "PageType_" . (string)$name;
            if (class_exists($class))
                return new $class;
            trigger_error(sprintf("PageType '%s' unknown", (string)$name),
                          E_USER_WARNING);
        }
        return new PageType_wikitext;
    }

    /**
     * Get the name of this page type.
     *
     * @return string  Page type name.
     */
    function getName() {
	if (!preg_match('/^PageType_(.+)$/i', get_class($this), $m))
	    trigger_error("Bad class name for formatter(?)", E_USER_ERROR);
	return $m[1];
    }

    /**
     * Transform page text.
     *
     * @param WikiDB_Page $page
     * @param string $text
     * @param hash $meta Version meta-data
     * @return XmlContent The transformed page text.
     */
    function transform($page, $text, $meta) {
        $fmt_class = 'PageFormatter_' . $this->getName();
        $formatter = new $fmt_class($page, $meta);
        return $formatter->format($text);
    }
}

class PageType_wikitext extends PageType {}
class PageType_wikiblog extends PageType {}
class PageType_interwikimap extends PageType
{
    // FIXME: move code from interwikimap into here.(?)
}


/** How to transform text.
 */
class PageFormatter {
    /** Constructor.
     *
     * @param WikiDB_Page $page
     * @param hash $meta Version meta-data.
     */
    function PageFormatter($page, $meta) {
        $this->_page = $page;
	$this->_meta = $meta;
	if (!empty($meta['markup']))
	    $this->_markup = $meta['markup'];
	else
	    $this->_markup = 1;
    }

    function _transform($text) {
	include_once('lib/BlockParser.php');
	return TransformText($text, $this->_markup);
    }

    /** Transform the page text.
     *
     * @param string $text  The raw page content (e.g. wiki-text).
     * @return XmlContent   Transformed content.
     */
    function format($text) {
        trigger_error("pure virtual", E_USER_ERROR);
    }
}

class PageFormatter_wikitext extends PageFormatter 
{
    function format($text) {
	return HTML::div(array('class' => 'wikitext'),
			 $this->_transform($text));
    }
}

class PageFormatter_interwikimap extends PageFormatter
{
    function format($text) {
	return HTML::div(array('class' => 'wikitext'),
			 $this->_transform($this->_getHeader($text)),
			 $this->_formatMap(),
			 $this->_transform($this->_getFooter($text)));
    }

    function _getHeader($text) {
	return preg_replace('/<verbatim>.*/s', '', $text);
    }
    function _getFooter($text) {
	return preg_replace('@.*?(</verbatim>|\Z)@s', '', $text, 1);
    }

    function _getMap() {
        global $request;
        // let interwiki.php get the map
        include_once("lib/interwiki.php");
        $map = InterWikiMap::GetMap($request);
        return $map->_map;
    }

    function _formatMap() {
	$map = $this->_getMap();
	if (!$map)
	    return HTML::p("<No map found>"); // Shouldn't happen.

	global $request;
        $dbi = $request->getDbh();

        $mon_attr = array('class' => 'interwiki-moniker');
        $url_attr = array('class' => 'interwiki-url');
        
        $thead = HTML::thead(HTML::tr(HTML::th($mon_attr, _("Moniker")),
				      HTML::th($url_attr, _("InterWiki Address"))));
	foreach ($map as $moniker => $interurl) {
	    $rows[] = HTML::tr(HTML::td($mon_attr, new Cached_WikiLinkIfKnown($moniker)),
			       HTML::td($url_attr, HTML::tt($interurl)));
        }
	
	return HTML::table(array('class' => 'interwiki-map'),
			   $thead,
			   HTML::tbody(false, $rows));
    }
}

class FakePageRevision {
    function FakePageRevision($meta) {
        $this->_meta = $meta;
    }

    function get($key) {
        if (empty($this->_meta[$key]))
            return false;
        return $this->_meta[$key];
    }
}

        
class PageFormatter_wikiblog extends PageFormatter
{
    // Display contents:
    function format($text) {
        include_once('lib/Template.php');
        global $request;
        $tokens['CONTENT'] = $this->_transform($text);
        $tokens['page'] = $this->_page;
        $tokens['rev'] = new FakePageRevision($this->_meta);

        $name = new WikiPageName($this->_page->getName());
        $tokens['BLOG_PARENT'] = $name->getParent();

        $blog_meta = $this->_meta['wikiblog'];
        foreach(array('ctime', 'creator', 'creator_id') as $key)
            $tokens["BLOG_" . strtoupper($key)] = $blog_meta[$key];
        
        return new Template('wikiblog', $request, $tokens);
    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
