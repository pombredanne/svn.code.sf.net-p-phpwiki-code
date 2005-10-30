<?php
/**
 * Multiple browser support, currently Mozilla (PC, Mac and Linux), 
 * MSIE (PC) and FireFox (PC, Mac and Linux) and some limited Safari support.
 *
 * Download: http://tinymce.moxiecode.com/
 * requires installation of the jscripts subdirectory
 *   tinymce/jscripts/tiny_mce/ into themes/default/tiny_mce/
 *
 * WARNING! Probably incompatible with ENABLE_XHTML_XML
 *
 * @package WysiwygEdit
 * @author Reini Urban
 */

require_once("lib/WysiwygEdit.php");

class WysiwygEdit_tinymce extends WysiwygEdit {

    function Head($name='edit[content]') {
        global $LANG, $WikiTheme;
        $WikiTheme->addMoreHeaders
            (Javascript('', array('src' => DATA_PATH.'/themes/default/tiny_mce/tiny_mce.js',
                                  'language' => 'JavaScript')));
        return Javascript("
tinyMCE.init({
	mode    : 'exact',
	elements: '$name',
        theme   : 'advanced',
        language: '$LANG',
        ask     : false,
	theme_advanced_buttons1 : \"bold,italic,underline,separator,strikethrough,justifyleft,justifycenter,justifyright, justifyfull,bullist,numlist,undo,redo,link,unlink\",
	theme_advanced_buttons2 : \"\",
	theme_advanced_buttons3 : \"\",
	theme_advanced_toolbar_location : \"top\",
	theme_advanced_toolbar_align : \"left\",
	theme_advanced_path_location : \"bottom\",
	extended_valid_elements : \"a[name|href|target|title|onclick],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]\"
});
        //plugins : \"table,contextmenu,paste,searchreplace,iespell,insertdatetime\",
});");
    }

    // to be called after </textarea>
    // name ignored
    function Textarea($textarea,$wikitext,$name='edit[content]') {
        $id = "editareawiki";
        $textarea->SetAttr('id', $name);
        $out = HTML($textarea, HTML::div(array("id"=>$id, 'style'=>'display:none'),
                                         $wikitext),"\n");
        //TODO: maybe some more custom links
        return $out;
    }
}

// re-use these classes for the regexp's.
// just output strings instead of XmlObjects
class Markup_html_br extends Markup_linebreak {
    function markup ($match) {
        return $match;
    }
}

class Markup_html_simple_tag extends Markup_html_emphasis {
    function markup ($match, $body) {
        $tag = substr($match, 1, -1);
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
        return "<\\/" . substr($match, 1);
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