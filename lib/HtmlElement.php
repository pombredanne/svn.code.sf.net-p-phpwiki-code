<?php rcs_id('$Id: HtmlElement.php,v 1.1 2002-01-21 01:50:10 dairiki Exp $');
/*
 * Code for writing XML.
 */
require_once("lib/XmlElement.php");
/**
 * An XML element.
 */

class HtmlElement extends XmlElement
{
    function printXML () {
        if (!$this->_content) {
            if (HTML::isEmptyTag($this->getTag())) {
                echo "<" . $this->_startTag() . " />";
                return;
            }
            $this->pushContent('');
        }
        XmlElement::printXML();
    }

    function printHTML () {
        $this->printXML();
    }

    function asHTML () {
        return $this->asXML();
    }
};

class HTML {
    function raw ($html_text) {
        return new RawXML($html_text);
    }


    function a (/*...*/) {
        $el = new HtmlElement('a');
        $el->_init(func_get_args());
        return $el;
    }

    function img (/*...*/) {
        $el = new HtmlElement('img');
        $el->_init(func_get_args());
        return $el;
    }

    function h1 (/*...*/) {
        $el = new HtmlElement('h1');
        $el->_init(func_get_args());
        return $el;
    }

    function p (/*...*/) {
        $el = new HtmlElement('p');
        $el->_init(func_get_args());
        return $el;
    }

    function table (/*...*/) {
        $el = new HtmlElement('table');
        $el->_init(func_get_args());
        return $el;
    }

    function tr (/*...*/) {
        $el = new HtmlElement('tr');
        $el->_init(func_get_args());
        return $el;
    }

    function isEmptyTag($tag) {
        global $HTML_TagProperties;
        if (!isset($HTML_TagProperties[$tag]))
            return false;
        $props = $HTML_TagProperties[$tag];
        return ($props & HTMLTAG_EMPTY) != 0;
    }
}

define('HTMLTAG_EMPTY', 1);

$GLOBALS['HTML_TagProperties']
= array('area' => HTMLTAG_EMPTY,
        'base' => HTMLTAG_EMPTY,
        'basefont' => HTMLTAG_EMPTY,
        'br' => HTMLTAG_EMPTY,
        'col' => HTMLTAG_EMPTY,
        'frame' => HTMLTAG_EMPTY,
        'hr' => HTMLTAG_EMPTY,
        'img' => HTMLTAG_EMPTY,
        'input' => HTMLTAG_EMPTY,
        'isindex' => HTMLTAG_EMPTY,
        'link' => HTMLTAG_EMPTY,
        'meta' => HTMLTAG_EMPTY,
        'param' => HTMLTAG_EMPTY);
        
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
