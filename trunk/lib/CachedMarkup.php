<?php rcs_id('$Id: CachedMarkup.php,v 1.4 2003-02-26 00:39:30 dairiki Exp $');
/* Copyright (C) 2002, Geoffrey T. Dairiki <dairiki@dairiki.org>
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
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class CacheableMarkup extends XmlContent {

    function CacheableMarkup($content, $basepage) {
        $this->_basepage = $basepage;
	$this->_buf = '';
	$this->_content = array();
	$this->_append($content);
	if ($this->_buf)
	    $this->_content[] = $this->_buf;
	unset($this->_buf);
    }

    function pack() {
        if (function_exists('gzcompress'))
            return gzcompress(serialize($this), 9);
        return serialize($this);

        // FIXME: probably should implement some sort of "compression"
        //   when no gzcompress is available.
    }

    function unpack($packed) {
        if (!$packed)
            return false;

        if (function_exists('gzcompress')) {
            // ZLIB format has a five bit checksum in it's header.
            // Lets check for sanity.
            if ((ord($packed[0]) * 256 + ord($packed[1])) % 31 == 0) {
                // Looks like ZLIB.
                return unserialize(gzuncompress($packed));
            }
        }
        if (substr($packed,0,2) == "O:") {
            // Looks like a serialized object
            return unserialize($packed);
        }
        trigger_error("Can't unpack bad cached markup", E_USER_WARNING);
        return false;
    }
    
    /** Get names of wikipages linked to.
     *
     * @return array
     * A list of wiki page names (strings).
     */
    function getWikiPageLinks() {
	$links = array();
	foreach ($this->_content as $link) {
	    if (! isa($link, 'Cached_WikiLink'))
		continue;
	    if (($pagename = $link->getPagename($this->_basepage)))
                $links[$pagename] = 1;
	}
	return array_keys($links);
    }

    /** Get link info.
     *
     * This is here to support the XML-RPC listLinks() method.
     *
     * @return array
     * Returns an array of hashes.
     */
    function getLinkInfo() {
	$link = array();
	foreach ($this->_content as $link) {
	    if (! isa($link, 'Cached_Link'))
		continue;
	    $info = $link->getLinkInfo($this->_basepage);
	    $links[$info->href] = $info;
	}
	return array_values($links);
    }
	    
    function _append($item) {
	if (is_array($item)) {
	    foreach ($item as $subitem)
		$this->_append($subitem);
	}
	elseif (!is_object($item)) {
	    $this->_buf .= $this->_quote((string) $item);
	}
	elseif (isa($item, 'Cached_DynamicContent')) {
	    if ($this->_buf) {
		$this->_content[] = $this->_buf;
		$this->_buf = '';
	    }
	    $this->_content[] = $item;
	}
	elseif (isa($item, 'XmlElement')) {
	    if ($item->isEmpty()) {
		$this->_buf .= $item->emptyTag();
	    }
	    else {
		$this->_buf .= $item->startTag();
		foreach ($item->getContent() as $subitem)
		    $this->_append($subitem);
		$this->_buf .= "</$item->_tag>";
	    }
	    if (!$item->isInlineElement())
		$this->_buf .= "\n";
	}
	elseif (isa($item, 'XmlContent')) {
	    foreach ($item->getContent() as $item)
		$this->_append($item);
	}
	elseif (method_exists($item, 'asxml')) {
	    $this->_buf .= $item->asXML();
	}
	elseif (method_exists($item, 'asstring')) {
	    $this->_buf .= $this->_quote($item->asString());
	}
	else {
	    $this->_buf .= sprintf("==Object(%s)==", get_class($item));
	}
    }

    function asXML () {
	$xml = '';
        $basepage = $this->_basepage;
        
	foreach ($this->_content as $item) {
            if (is_string($item)) {
                $xml .= $item;
            }
            elseif (is_subclass_of($item, 'cached_dynamiccontent')) {
                $val = $item->expand($basepage);
                $xml .= $val->asXML();
            }
            else {
                $xml .= $item->asXML();
            }
	}
	return $xml;
    }

    function printXML () {
        $basepage = $this->_basepage;

	foreach ($this->_content as $item) {
            if (is_string($item)) {
                print $item;
            }
            elseif (is_subclass_of($item, 'cached_dynamiccontent')) {
                $val = $item->expand($basepage);
                $val->printXML();
            }
            else {
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
class Cached_DynamicContent {

    function cache(&$cache) {
	$cache[] = $this;
    }

    function expand($basepage) {
        trigger_error("Pure virtual", E_USER_ERROR);
    }
}

class XmlRpc_LinkInfo {
    function XmlRpc_LinkInfo($page, $type, $href) {
	$this->page = $page;
	$this->type = $type;
	$this->href = $href;
    }
}

class Cached_Link extends Cached_DynamicContent {

    function isInlineElement() {
	return true;
    }

    /** Get link info (for XML-RPC support)
     *
     * This is here to support the XML-RPC listLinks method.
     * (See http://www.ecyrd.com/JSPWiki/Wiki.jsp?page=WikiRPCInterface)
     */
    function getLinkInfo($basepage) {
	return new XmlRpc_LinkInfo($this->_getName($basepage),
                                   $this->_getType(),
                                   $this->_getURL($basepage));
    }
    
    function _getURL($basepage) {
	return $this->_url;
    }
}

class Cached_WikiLink extends Cached_Link {

    function Cached_WikiLink ($page, $label = false, $anchor = false) {
	$this->_page = $page;
        if ($anchor)
            $this->_anchor = $anchor;
        if ($label and $label != $page)
            $this->_label = $label;
    }

    function _getType() {
        return 'internal';
    }
    
    function getPagename($basepage) {
	$page = new WikiPageName($this->_page, $basepage);
	return $page->name;
    }

    function _getName($basepage) {
	return $this->getPagename($basepage);
    }

    function _getURL($basepage) {
	return WikiURL($this->getPagename($basepage), false, 'abs_url');
    }

    function expand($basepage) {
	$label = isset($this->_label) ? $this->_label : false;
	$anchor = isset($this->_anchor) ? (string)$this->_anchor : '';
        $page = new WikiPageName($this->_page, $basepage, $anchor);
	return WikiLink($page, 'auto', $label);
    }
}

class Cached_WikiLinkIfKnown extends Cached_WikiLink
{
    function Cached_WikiLinkIfKnown ($moniker) {
	$this->_page = $moniker;
    }

    function expand($basepage) {
        return WikiLink($this->_page, 'if_known');
    }
}    
    
class Cached_PhpwikiURL extends Cached_DynamicContent
{
    function Cached_PhpwikiURL ($url, $label) {
	$this->_url = $url;
        if ($label)
            $this->_label = $label;
    }

    function isInlineElement() {
	return true;
    }

    function expand($basepage) {
        $label = isset($this->_label) ? $this->_label : false;
        return LinkPhpwikiURL($this->_url, $label, $basepage);
    }
}    
    
class Cached_ExternalLink extends Cached_Link {

    function Cached_ExternalLink($url, $label=false) {
	$this->_url = $url;
        if ($label && $label != $url)
            $this->_label = $label;
    }

    function _getType() {
        return 'external';
    }
    
    function _getName($basepage) {
	$label = isset($this->_label) ? $this->_label : false;
	return ($label and is_string($label)) ? $label : $this->_url;
    }

    function expand($basepage) {
	$label = isset($this->_label) ? $this->_label : false;
	return LinkURL($this->_url, $label);
    }
}

class Cached_InterwikiLink extends Cached_ExternalLink {
    
    function Cached_InterwikiLink($link, $label=false) {
	$this->_link = $link;
        if ($label)
            $this->_label = $label;
    }

    function _getName($basepage) {
	$label = isset($this->_label) ? $this->_label : false;
	return ($label and is_string($label)) ? $label : $link;
    }
    
    function _getURL($basepage) {
	$link = $this->expand($basepage);
	return $link->getAttr('href');
    }

    function expand($basepage) {
        include_once('lib/interwiki.php');
	$intermap = InterWikiMap::GetMap($GLOBALS['request']);
	$label = isset($this->_label) ? $this->_label : false;
	return $intermap->link($this->_link, $label);
    }
}



class Cached_PluginInvocation extends Cached_DynamicContent {
    function Cached_PluginInvocation ($pi) {
	$this->_pi = $pi;
    }

    function setTightness($top, $bottom) {
        $this->_tightenable = 0;
        if ($top) $this->_tightenable |= 1;
        if ($bottom) $this->_tightenable |= 2;
    }
    
    function isInlineElement() {
	return false;
    }

    function expand($basepage) {
        static $loader = false;

	if (!$loader) {
            include_once('lib/WikiPlugin.php');
	    $loader = new WikiPluginLoader;
        }

        $xml = HTML::div(array('class' => 'plugin'),
			 $loader->expandPI($this->_pi, $GLOBALS['request'], $basepage));
        
	if (isset($this->_tightenable)) {
	    $xml->setInClass('tightenable');
	    $xml->setInClass('top', ($this->_tightenable & 1) != 0);
	    $xml->setInClass('bottom', ($this->_tightenable & 2) != 0);
	}

	return $xml;
    }
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
