<?php rcs_id('$Id: HtmlElement.php,v 1.11 2002-01-24 21:21:37 dairiki Exp $');
/*
 * Code for writing XML.
 */
require_once("lib/XmlElement.php");
/**
 * An XML element.
 */

class HtmlElement extends XmlElement
{
    function HtmlElement ($tagname /* , $attr_or_content , ...*/) {
	$this->_tag = $tagname;
        $this->_content = array();
        $this->_properties = HTML::getTagProperties($tagname);
        
        if (func_num_args() > 1)
            $this->_init(array_slice(func_get_args(), 1));
        else 
            $this->_attr = array();
    }

    /** Add a "tooltip" to an element.
     *
     * @param $tooltip_text string The tooltip text.
     */
    function addTooltip ($tooltip_text) {
        $this->setAttr('title', $tooltip_text);

        // FIXME: this should be initialized from title by an onLoad() function.
        //        (though, that may not be possible.)
        $qtooltip = str_replace("'", "\\'", $tooltip_text);
        $this->setAttr('onmouseover',
                       sprintf('window.status="%s"; return true;',
                               addslashes($tooltip_text)));
        $this->setAttr('onmouseout', "window.status='';return true;");
    }

    function _emptyTag () {
        return substr($this->_startTag(), 0, -1) . " />";
    }

    function isEmpty () {
        return ($this->_properties & HTMLTAG_EMPTY) != 0;
    }

    function hasInlineContent () {
        return ($this->_properties & HTMLTAG_ACCEPTS_INLINE) != 0;
    }

    function isInlineElement () {
        return ($this->_properties & HTMLTAG_INLINE) != 0;
    }
};

function HTML ($tag /* , ... */) {
    $el = new HtmlElement($tag);
    if (func_num_args() > 1)
        $el->_init(array_slice(func_get_args(), 1));
    return $el;
}

define('NBSP', "\xA0");         // iso-8859-x non-breaking space.

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

    function br (/*...*/) {
        $el = new HtmlElement('br');
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

    function hr (/*...*/) {
        $el = new HtmlElement('hr');
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

    function pre (/*...*/) {
        $el = new HtmlElement('pre');
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

    function sup (/*...*/) {
        $el = new HtmlElement('sup');
        $el->_init(func_get_args());
        return $el;
    }

    function sub (/*...*/) {
        $el = new HtmlElement('sub');
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

    function tfoot (/*...*/) {
        $el = new HtmlElement('tfoot');
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

    function getTagProperties($tag) {
        $props = &$GLOBALS['HTML_TagProperties'];
        return isset($props[$tag]) ? $props[$tag] : 0;
    }
    
    function _setTagProperty($prop_flag, $tags) {
        $props = &$GLOBALS['HTML_TagProperties'];
        if (is_string($tags))
            $tags = preg_split('/\s+/', $tags);
        foreach ($tags as $tag) {
            if (isset($props[$tag]))
                $props[$tag] |= $prop_flag;
            else
                $props[$tag] = $prop_flag;
        }
    }
}

define('HTMLTAG_EMPTY', 1);
define('HTMLTAG_INLINE', 2);
define('HTMLTAG_ACCEPTS_INLINE', 4);


HTML::_setTagProperty(HTMLTAG_EMPTY,
                      'area base basefont br col frame hr img input isindex link meta param');
HTML::_setTagProperty(HTMLTAG_ACCEPTS_INLINE,
                      // %inline elements:
                      'b big i small tt ' // %fontstyle
                      . 's strike u ' // (deprecated)
                      . 'abbr acronym cite code dfn em kbd samp strong var ' //%phrase
                      . 'a img object br script map q sub sup span bdo '//%special
                      . 'button input label select textarea ' //%formctl

                      // %block elements which contain inline content
                      . 'address h1 h2 h3 h4 h5 h6 p pre '
                      // %block elements which contain either block or inline content
                      . 'div fieldset '

                      // other with inline content
                      . 'caption dt label legend '
                      // other with either inline or block
                      . 'dd del ins li td th ');

HTML::_setTagProperty(HTMLTAG_INLINE,
                      // %inline elements:
                      'b big i small tt ' // %fontstyle
                      . 's strike u ' // (deprecated)
                      . 'abbr acronym cite code dfn em kbd samp strong var ' //%phrase
                      . 'a img object br script map q sub sup span bdo '//%special
                      . 'button input label select textarea ' //%formctl
                      );

      
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
