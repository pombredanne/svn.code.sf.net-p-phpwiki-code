<?php
/**
 * Baseclass for WysiwygEdit/tinymce, WysiwygEdit/htmlarea3, WysiwygEdit/htmlarea2
 *
 * ENABLE_WYSIWYG - Support for some WYSIWYG HTML Editor (tinymce or htmlarea3)
 * Not yet enabled, since we cannot convert HTML to Wiki Markup yet.
 * (See HtmlParser.php for the ongoing efforts)
 * We might use a HTML PageType, which is contra wiki, but some people 
 * might prefer HTML markup.
 *
 * TODO: Change from constant to user preference variable 
 *       (checkbox setting or edit click as in gmail),
 *       when HtmlParser is finished.
 * Based upon htmlarea3.php and tinymce.php
 * WARNING! Incompatible with ENABLE_XHTML_XML
 *
 * @package WysiwygEdit
 * @author Reini Urban
 */

require_once("lib/InlineParser.php");

// TODO: move to config-default.ini
if (!defined('USE_HTMLAREA3')) define('USE_HTMLAREA3', false);
if (!defined('USE_HTMLAREA2')) define('USE_HTMLAREA2', false);
if (!defined('USE_TINYMCE'))   define('USE_TINYMCE',   true);

class WysiwygEdit {

    function WysiwygEdit() { }

    function Head($name='edit[content]') {
        trigger_error("virtual", E_USER_ERROR); 
    }

    // to be called after </textarea>
    function Textarea($textarea,$wikitext,$name='edit[content]') {
        trigger_error("virtual", E_USER_ERROR); 
    }

    /**
     * Handler to convert the Wiki Markup to HTML before editing.
     * This will be converted back by WysiwygEdit_ConvertAfter
     *  *text* => '<b>text<b>'
     */
    function ConvertBefore($text) {
        return asXML(TransformInline($text, 2.0, false));
    }
    
    /**
     * Handler to convert the HTML formatting back to wiki formatting.
     * Derived from InlineParser, but returning wiki text instead of HtmlElement objects.
     * '<b>text<b>' => '<SPAN style="FONT-WEIGHT: bold">text</SPAN>' => '*text*'
     */
    function ConvertAfter($text) {
        static $trfm;
        if (empty($trfm)) {
            $trfm = new HtmlTransformer();
        }
        $markup = $trfm->parse($text); // version 2.0
        return $markup;
    }
}

/*
 $Log: not supported by cvs2svn $

*/

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>