<?php
/**
 * Based upon htmlarea3.php
 * WARNING! Incompatible with ENABLE_XHTML_XML
 *
 * @package WysiwygEdit
 * @author Reini Urban
 */

function Edit_WYSIWYG_Head($name='edit[content]') {
    global $LANG, $WikiTheme;
    $WikiTheme->addMoreHeaders(Javascript('', array('src' => DATA_PATH.'/themes/default/tiny_mce/tiny_mce.js',
                                     'language' => 'JavaScript')));
    //plugins : 'table,contextmenu,paste,searchreplace,iespell,insertdatetime,contextmenu',
    return Javascript("
tinyMCE.init({
	mode    : 'exact',
	elements: '$name',
        theme   : 'advanced',
        language: '$LANG',
        ask : true
});");
}

// to be called after </textarea>
// name ignored
function Edit_WYSIWYG_Textarea($textarea,$wikitext,$name='edit[content]') {
    $textarea->SetAttr('id', $name);
    $out = HTML($textarea, HTML::div(array("id"=>"editareawiki",'style'=>'display:none'),
                                     $wikitext),"\n");
    //TODO: maybe some more custom links
    return $out;
}

require_once("lib/InlineParser.php");

// re-use these classes for the regexp's.
// just output strings instead of XmlObjects
class Markup_html_br extends Markup_linebreak {
    function markup ($match) {
        return $match;
    }
}

class Markup_html_simple_tag extends Markup_html_emphasis {
    function markup ($match, $body) {
        $tag = mb_substr($match, 1, -1);
        switch ($tag) {
        case 'b':
        case 'strong':
            return "*".$body."*";
        case 'big': return "<big>".$body."</big>";
        case 'i':
        case 'em':
            return "_".$body."_";
        }
    }
}

//'<SPAN style="FONT-WEIGHT: bold">text</SPAN>' => '*text*'
class Markup_html_bold extends BalancedMarkup
{
    var $_start_regexp = "<(?:span|SPAN) style=\"FONT-WEIGHT: bold\">";

    function getEndRegexp ($match) {
        return "<\\/" . mb_substr($match, 1);
    }
    function markup ($match, $body) {
        //Todo: convert style formatting to simplier nested <b><i> tags
        return "*".$body."*";
    }
}

class HtmlTransformer extends InlineTransformer
{
    function HtmlTransformer () {
        $this->InlineTransformer(array('escape',
                                       'html_br','html_bold','html_simple_tag',
                                       /*
                                       'html_a','html_span','html_div',
                                       'html_table','html_hr','html_pre',
                                       'html_blockquote',
                                       'html_indent','html_ol','html_li','html_ul','html_img',
                                       */));
    }
}

/**
 * Handler to convert the Wiki Markup to HTML before editing.
 * This will be converted back by Edit_WYSIWYG_ConvertAfter
 *  *text* => '<b>text<b>'
 */
function Edit_WYSIWYG_ConvertBefore($text) {
    return asXML(TransformInline($text, 2.0, false));
}

/**
 * Handler to convert the HTML formatting back to wiki formatting.
 * Derived from InlineParser, but returning wiki text instead of HtmlElement objects.
 * '<b>text<b>' => '<SPAN style="FONT-WEIGHT: bold">text</SPAN>' => '*text*'
 */
function Edit_WYSIWYG_ConvertAfter($text) {
    static $trfm;
    if (empty($trfm)) {
        $trfm = new HtmlTransformer();
    }
    $markup = $trfm->parse($text); // version 2.0
    return $markup;
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
