<?php rcs_id('$Id: HtmlElement.php,v 1.3 2002-01-21 16:51:12 carstenklapp Exp $');
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
};

class HTML {
    function raw ($html_text) {
        return new RawXML($html_text);
    }


    function link (/*...*/) {
        $el = new HtmlElement('link');
        $el->_init(func_get_args());
        return $el;
    }

    function style (/*...*/) {
        $el = new HtmlElement('style');
        $el->_init(func_get_args());
        return $el;
    }

    function script (/*...*/) {
        $el = new HtmlElement('script');
        $el->_init(func_get_args());
        return $el;
    }

    function noscript (/*...*/) {
        $el = new HtmlElement('noscript');
        $el->_init(func_get_args());
        return $el;
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

    function h1 (/*...*/) {
        $el = new HtmlElement('h1');
        $el->_init(func_get_args());
        return $el;
    }

    function h2 (/*...*/) {
        $el = new HtmlElement('h2');
        $el->_init(func_get_args());
        return $el;
    }

    function h3 (/*...*/) {
        $el = new HtmlElement('h3');
        $el->_init(func_get_args());
        return $el;
    }

    function h4 (/*...*/) {
        $el = new HtmlElement('h4');
        $el->_init(func_get_args());
        return $el;
    }

    function h5 (/*...*/) {
        $el = new HtmlElement('h5');
        $el->_init(func_get_args());
        return $el;
    }

    function h6 (/*...*/) {
        $el = new HtmlElement('h6');
        $el->_init(func_get_args());
        return $el;
    }

    function div (/*...*/) {
        $el = new HtmlElement('div');
        $el->_init(func_get_args());
        return $el;
    }

    function p (/*...*/) {
        $el = new HtmlElement('p');
        $el->_init(func_get_args());
        return $el;
    }

    function blockquote (/*...*/) {
        $el = new HtmlElement('blockquote');
        $el->_init(func_get_args());
        return $el;
    }

    function span (/*...*/) {
        $el = new HtmlElement('span');
        $el->_init(func_get_args());
        return $el;
    }

    function em (/*...*/) {
        $el = new HtmlElement('em');
        $el->_init(func_get_args());
        return $el;
    }

    function strong (/*...*/) {
        $el = new HtmlElement('strong');
        $el->_init(func_get_args());
        return $el;
    }
    
    function small (/*...*/) {
        $el = new HtmlElement('small');
        $el->_init(func_get_args());
        return $el;
    }
    
    function tt (/*...*/) {
        $el = new HtmlElement('tt');
        $el->_init(func_get_args());
        return $el;
    }

    function u (/*...*/) {
        $el = new HtmlElement('u');
        $el->_init(func_get_args());
        return $el;
    }

    function ul (/*...*/) {
        $el = new HtmlElement('ul');
        $el->_init(func_get_args());
        return $el;
    }

    function ol (/*...*/) {
        $el = new HtmlElement('ol');
        $el->_init(func_get_args());
        return $el;
    }

    function dl (/*...*/) {
        $el = new HtmlElement('dl');
        $el->_init(func_get_args());
        return $el;
    }

    function li (/*...*/) {
        $el = new HtmlElement('li');
        $el->_init(func_get_args());
        return $el;
    }

    function dt (/*...*/) {
        $el = new HtmlElement('dt');
        $el->_init(func_get_args());
        return $el;
    }

    function dd (/*...*/) {
        $el = new HtmlElement('dd');
        $el->_init(func_get_args());
        return $el;
    }

    function table (/*...*/) {
        $el = new HtmlElement('table');
        $el->_init(func_get_args());
        return $el;
    }

    function caption (/*...*/) {
        $el = new HtmlElement('caption');
        $el->_init(func_get_args());
        return $el;
    }

    function thead (/*...*/) {
        $el = new HtmlElement('thead');
        $el->_init(func_get_args());
        return $el;
    }

    function tbody (/*...*/) {
        $el = new HtmlElement('tbody');
        $el->_init(func_get_args());
        return $el;
    }

    function tr (/*...*/) {
        $el = new HtmlElement('tr');
        $el->_init(func_get_args());
        return $el;
    }

    function td (/*...*/) {
        $el = new HtmlElement('td');
        $el->_init(func_get_args());
        return $el;
    }

    function th (/*...*/) {
        $el = new HtmlElement('th');
        $el->_init(func_get_args());
        return $el;
    }

    function form (/*...*/) {
        $el = new HtmlElement('form');
        $el->_init(func_get_args());
        return $el;
    }

    function input (/*...*/) {
        $el = new HtmlElement('input');
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
