<?php
/**
 * Copyright © 2010 Reini Urban
 * Copyright © 2014 Marc-Etienne Vargenau, Alcatel-Lucent
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

/**
 * Code for writing the HTML subset of XML.
 * @author: Jeff Dairiki
 *
 * Todo: Add support for a JavaScript backend, a php2js compiler.
 * HTML::div(array('onclick' => 'HTML::div(...)'))
 */
if (!class_exists("XmlElement")) {
    require_once(dirname(__FILE__) . "/XmlElement.php");
}
if (class_exists("HtmlElement")) {
    return;
}

/**
 * An XML element.
 */

class HtmlElement extends XmlElement
{
    public $_tag;
    public $_attr;

    public function __construct($tagname /* , $attr_or_content , ...*/)
    {
        $this->_init(func_get_args());
        $this->_properties = HTML::getTagProperties($tagname);
    }

    public function _init($args)
    {
        if (!is_array($args)) {
            $args = func_get_args();
        }

        assert(count($args) >= 1);
        assert(is_string($args[0]));
        $this->_tag = array_shift($args);

        if ($args && is_array($args[0])) {
            $this->_attr = array_shift($args);
        } else {
            $this->_attr = array();
            if ($args && $args[0] === false) {
                array_shift($args);
            }
        }
        $this->setContent($args);
        $this->_properties = HTML::getTagProperties($this->_tag);
    }

    /**
     * This is used by the static factory methods is class HTML.
     *
     * @param array $args
     * @return $this
     */
    protected function _init2($args)
    {
        if ($args) {
            if (is_array($args[0])) {
                $this->_attr = array_shift($args);
            } elseif ($args[0] === false) {
                array_shift($args);
            }
        }

        if (count($args) == 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $this->_content = $args;
        return $this;
    }

    /** Add a "tooltip" to an element.
     *
     * @param string $tooltip_text The tooltip text.
     */
    public function addTooltip($tooltip_text)
    {
        $this->setAttr('title', $tooltip_text);

        // FIXME: this should be initialized from title by an onLoad() function.
        //        (though, that may not be possible.)
        $this->setAttr(
            'onmouseover',
            sprintf(
                'window.status="%s"; return true;',
                addslashes($tooltip_text)
            )
        );
        $this->setAttr('onmouseout', "window.status='';return true;");
    }

    public function emptyTag()
    {
        if (($this->_properties & HTMLTAG_EMPTY) == 0) {
            return $this->startTag() . "</$this->_tag>";
        }

        return substr($this->startTag(), 0, -1) . " />";
    }

    public function hasInlineContent()
    {
        return ($this->_properties & HTMLTAG_ACCEPTS_INLINE) != 0;
    }

    public function isInlineElement()
    {
        return ($this->_properties & HTMLTAG_INLINE) != 0;
    }
}

function HTML(/* $content, ... */)
{
    return new XmlContent(func_get_args());
}

class HTML extends HtmlElement
{
    public static function raw($html_text)
    {
        return new RawXml($html_text);
    }

    public static function getTagProperties($tag)
    {
        $props = &$GLOBALS['HTML_TagProperties'];
        return isset($props[$tag]) ? $props[$tag] : 0;
    }

    public static function _setTagProperty($prop_flag, $tags)
    {
        $props = &$GLOBALS['HTML_TagProperties'];
        if (is_string($tags)) {
            $tags = preg_split('/\s+/', $tags);
        }
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag) {
                if (isset($props[$tag])) {
                    $props[$tag] |= $prop_flag;
                } else {
                    $props[$tag] = $prop_flag;
                }
            }
        }
    }

    // See admin/mkfuncs shell script to generate the following static methods

    public static function link(/*...*/)
    {
        $el = new HtmlElement('link');
        return $el->_init2(func_get_args());
    }

    public static function meta(/*...*/)
    {
        $el = new HtmlElement('meta');
        return $el->_init2(func_get_args());
    }

    public static function style(/*...*/)
    {
        $el = new HtmlElement('style');
        return $el->_init2(func_get_args());
    }

    public static function script(/*...*/)
    {
        $el = new HtmlElement('script');
        return $el->_init2(func_get_args());
    }

    public static function noscript(/*...*/)
    {
        $el = new HtmlElement('noscript');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function a(/*...*/)
    {
        $el = new HtmlElement('a');
        return $el->_init2(func_get_args());
    }

    public static function img(/*...*/)
    {
        $el = new HtmlElement('img');
        return $el->_init2(func_get_args());
    }

    public static function figure(/*...*/)
    {
        $el = new HtmlElement('figure');
        return $el->_init2(func_get_args());
    }

    public static function figcaption(/*...*/)
    {
        $el = new HtmlElement('figcaption');
        return $el->_init2(func_get_args());
    }

    public static function br(/*...*/)
    {
        $el = new HtmlElement('br');
        return $el->_init2(func_get_args());
    }

    public static function span(/*...*/)
    {
        $el = new HtmlElement('span');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function h1(/*...*/)
    {
        $el = new HtmlElement('h1');
        return $el->_init2(func_get_args());
    }

    public static function h2(/*...*/)
    {
        $el = new HtmlElement('h2');
        return $el->_init2(func_get_args());
    }

    public static function h3(/*...*/)
    {
        $el = new HtmlElement('h3');
        return $el->_init2(func_get_args());
    }

    public static function h4(/*...*/)
    {
        $el = new HtmlElement('h4');
        return $el->_init2(func_get_args());
    }

    public static function h5(/*...*/)
    {
        $el = new HtmlElement('h5');
        return $el->_init2(func_get_args());
    }

    public static function h6(/*...*/)
    {
        $el = new HtmlElement('h6');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function hr(/*...*/)
    {
        $el = new HtmlElement('hr');
        return $el->_init2(func_get_args());
    }

    public static function div(/*...*/)
    {
        $el = new HtmlElement('div');
        return $el->_init2(func_get_args());
    }

    public static function p(/*...*/)
    {
        $el = new HtmlElement('p');
        return $el->_init2(func_get_args());
    }

    public static function pre(/*...*/)
    {
        $el = new HtmlElement('pre');
        return $el->_init2(func_get_args());
    }

    public static function blockquote(/*...*/)
    {
        $el = new HtmlElement('blockquote');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function em(/*...*/)
    {
        $el = new HtmlElement('em');
        return $el->_init2(func_get_args());
    }

    public static function strong(/*...*/)
    {
        $el = new HtmlElement('strong');
        return $el->_init2(func_get_args());
    }

    public static function small(/*...*/)
    {
        $el = new HtmlElement('small');
        return $el->_init2(func_get_args());
    }

    public static function abbr(/*...*/)
    {
        $el = new HtmlElement('abbr');
        return $el->_init2(func_get_args());
    }

    public static function acronym(/*...*/)
    {
        $el = new HtmlElement('acronym');
        return $el->_init2(func_get_args());
    }

    public static function cite(/*...*/)
    {
        $el = new HtmlElement('cite');
        return $el->_init2(func_get_args());
    }

    public static function code(/*...*/)
    {
        $el = new HtmlElement('code');
        return $el->_init2(func_get_args());
    }

    public static function dfn(/*...*/)
    {
        $el = new HtmlElement('dfn');
        return $el->_init2(func_get_args());
    }

    public static function kbd(/*...*/)
    {
        $el = new HtmlElement('kbd');
        return $el->_init2(func_get_args());
    }

    public static function samp(/*...*/)
    {
        $el = new HtmlElement('samp');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function tt(/*...*/)
    {
        $el = new HtmlElement('tt');
        return $el->_init2(func_get_args());
    }

    public static function u(/*...*/)
    {
        $el = new HtmlElement('u');
        return $el->_init2(func_get_args());
    }

    public static function sup(/*...*/)
    {
        $el = new HtmlElement('sup');
        return $el->_init2(func_get_args());
    }

    public static function sub(/*...*/)
    {
        $el = new HtmlElement('sub');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function ul(/*...*/)
    {
        $el = new HtmlElement('ul');
        return $el->_init2(func_get_args());
    }

    public static function ol(/*...*/)
    {
        $el = new HtmlElement('ol');
        return $el->_init2(func_get_args());
    }

    public static function dl(/*...*/)
    {
        $el = new HtmlElement('dl');
        return $el->_init2(func_get_args());
    }

    public static function li(/*...*/)
    {
        $el = new HtmlElement('li');
        return $el->_init2(func_get_args());
    }

    public static function dt(/*...*/)
    {
        $el = new HtmlElement('dt');
        return $el->_init2(func_get_args());
    }

    public static function dd(/*...*/)
    {
        $el = new HtmlElement('dd');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function table(/*...*/)
    {
        $el = new HtmlElement('table');
        return $el->_init2(func_get_args());
    }

    public static function caption(/*...*/)
    {
        $el = new HtmlElement('caption');
        return $el->_init2(func_get_args());
    }

    public static function thead(/*...*/)
    {
        $el = new HtmlElement('thead');
        return $el->_init2(func_get_args());
    }

    public static function tbody(/*...*/)
    {
        $el = new HtmlElement('tbody');
        return $el->_init2(func_get_args());
    }

    public static function tfoot(/*...*/)
    {
        $el = new HtmlElement('tfoot');
        return $el->_init2(func_get_args());
    }

    public static function tr(/*...*/)
    {
        $el = new HtmlElement('tr');
        return $el->_init2(func_get_args());
    }

    public static function td(/*...*/)
    {
        $el = new HtmlElement('td');
        return $el->_init2(func_get_args());
    }

    public static function th(/*...*/)
    {
        $el = new HtmlElement('th');
        return $el->_init2(func_get_args());
    }

    public static function colgroup(/*...*/)
    {
        $el = new HtmlElement('colgroup');
        return $el->_init2(func_get_args());
    }

    public static function col(/*...*/)
    {
        $el = new HtmlElement('col');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function form(/*...*/)
    {
        $el = new HtmlElement('form');
        return $el->_init2(func_get_args());
    }

    public static function input(/*...*/)
    {
        $el = new HtmlElement('input');
        return $el->_init2(func_get_args());
    }

    public static function button(/*...*/)
    {
        $el = new HtmlElement('button');
        return $el->_init2(func_get_args());
    }

    public static function option(/*...*/)
    {
        $el = new HtmlElement('option');
        return $el->_init2(func_get_args());
    }

    public static function select(/*...*/)
    {
        $el = new HtmlElement('select');
        return $el->_init2(func_get_args());
    }

    public static function textarea(/*...*/)
    {
        $el = new HtmlElement('textarea');
        return $el->_init2(func_get_args());
    }

    public static function label(/*...*/)
    {
        $el = new HtmlElement('label');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function area(/*...*/)
    {
        $el = new HtmlElement('area');
        return $el->_init2(func_get_args());
    }

    public static function map(/*...*/)
    {
        $el = new HtmlElement('map');
        return $el->_init2(func_get_args());
    }

    public static function iframe(/*...*/)
    {
        $el = new HtmlElement('iframe');
        return $el->_init2(func_get_args());
    }

    public static function nobody(/*...*/)
    {
        $el = new HtmlElement('nobody');
        return $el->_init2(func_get_args());
    }

    public static function object(/*...*/)
    {
        $el = new HtmlElement('object');
        return $el->_init2(func_get_args());
    }

    public static function embed(/*...*/)
    {
        $el = new HtmlElement('embed');
        return $el->_init2(func_get_args());
    }

    public static function param(/*...*/)
    {
        $el = new HtmlElement('param');
        return $el->_init2(func_get_args());
    }

    public static function fieldset(/*...*/)
    {
        $el = new HtmlElement('fieldset');
        return $el->_init2(func_get_args());
    }

    public static function legend(/*...*/)
    {
        $el = new HtmlElement('legend');
        return $el->_init2(func_get_args());
    }

    /****************************************/
    public static function video(/*...*/)
    {
        $el = new HtmlElement('video');
        return $el->_init2(func_get_args());
    }
}

define('HTMLTAG_EMPTY', 1);
define('HTMLTAG_INLINE', 2);
define('HTMLTAG_ACCEPTS_INLINE', 4);

HTML::_setTagProperty(
    HTMLTAG_EMPTY,
    'area base basefont br col embed hr img input isindex link meta param'
);
HTML::_setTagProperty(
    HTMLTAG_ACCEPTS_INLINE,
    // %inline elements:
    'b big i small tt ' // %fontstyle
        . 's strike u ' // (deprecated)
        . 'abbr acronym cite code dfn em kbd samp strong var ' //%phrase
        . 'a img figure object embed br script map q sub sup span bdo ' //%special
        . 'button input label option select textarea label ' //%formctl

        // %block elements which contain inline content
        . 'address h1 h2 h3 h4 h5 h6 p pre '
        // %block elements which contain either block or inline content
        . 'div fieldset '

        // other with inline content
        . 'caption figcaption dt label legend video '
        // other with either inline or block
        . 'dd del ins li td th colgroup'
);

HTML::_setTagProperty(
    HTMLTAG_INLINE,
    // %inline elements:
    'b big i small tt ' // %fontstyle
        . 's strike u ' // (deprecated)
        . 'abbr acronym cite code dfn em kbd samp strong var ' //%phrase
        . 'a img object br script map q sub sup span bdo ' //%special
        . 'button input label option select textarea ' //%formctl
        . 'nobody iframe'
);

/**
 * Generate hidden form input fields.
 *
 * @param array $query_args A hash mapping names to values for the hidden inputs.
 * Values in the hash can themselves be hashes.  The will result in hidden inputs
 * which will reconstruct the nested structure in the resulting query args as
 * processed by PHP.
 *
 * Example:
 *
 * $args = array('x' => '2',
 *               'y' => array('a' => 'aval', 'b' => 'bval'));
 * $inputs = HiddenInputs($args);
 *
 * Will result in:
 *
 *  <input type="hidden" name="x" value = "2" />
 *  <input type="hidden" name="y[a]" value = "aval" />
 *  <input type="hidden" name="y[b]" value = "bval" />
 *
 * @param bool $pfx
 * @param array $exclude
 * @return object An XmlContent object containing the inputs.
 */
function HiddenInputs($query_args, $pfx = false, $exclude = array())
{
    $inputs = HTML();

    foreach ($query_args as $key => $val) {
        if (in_array($key, $exclude)) {
            continue;
        }
        $name = $pfx ? $pfx . "[$key]" : $key;
        if (is_array($val)) {
            $inputs->pushContent(HiddenInputs($val, $name));
        } else {
            $inputs->pushContent(HTML::input(array('type' => 'hidden',
                'name' => $name,
                'value' => $val)));
        }
    }
    return $inputs;
}

/** Generate a <script> tag containing javascript.
 *
 * @param string $js  The javascript.
 * @param array $script_args  (optional) hash of script tags options
 *                             e.g. to provide another version or the defer attr
 * @return HtmlElement A <script> element.
 */
function JavaScript($js, $script_args = array())
{
    $default_script_args = array('type' => 'text/javascript');
    $script_args = $script_args ? array_merge($default_script_args, $script_args)
        : $default_script_args;
    if (empty($js)) {
        return HTML(HTML::script($script_args), "\n");
    } else {
        return HTML(HTML::script(
            $script_args,
            new RawXml("\n<!--//" . "\n" . trim($js) . "\n" . "// -->")
        ), "\n");
    }
}

/** Conditionally display content based of whether javascript is supported.
 *
 * This conditionally (on the client side) displays one of two alternate
 * contents depending on whether the client supports javascript.
 *
 * NOTE:
 * The content you pass as arguments to this function must be block-level.
 * (This is because the <noscript> tag is block-level.)
 *
 * @param mixed $if_content Content to display if the browser supports
 * javascript.
 *
 * @param mixed $else_content Content to display if the browser does
 * not support javascript.
 *
 * @return XmlContent
 */
function IfJavaScript($if_content = false, $else_content = false)
{
    $html = array();
    if ($if_content) {
        $xml = AsXML($if_content);
        $js = sprintf(
            'document.write("%s");',
            addcslashes($xml, "\0..\37!@\\\177..\377")
        );
        $html[] = JavaScript($js);
    }
    if ($else_content) {
        $html[] = HTML::noscript(false, $else_content);
    }
    return HTML($html);
}
