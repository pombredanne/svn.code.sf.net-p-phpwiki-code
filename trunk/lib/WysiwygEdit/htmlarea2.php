<?php
/**
 * requires installation into themes/default/htmlarea2/
 * Output the javascript function to check for MS Internet Explorer >= 5.5 on Windows
 * and call the real js script then, else just a nil func.
 *   version 2: only for MSIE 5.5 and better
 *   version 3: also Mozilla >= 1.3
 *
 * @package WysiwygEdit
 * @author Reini Urban
 */

require_once("lib/WysiwygEdit.php");

class WysiwygEdit_htmlarea2 extends WysiwygEdit {

    function Head($name='edit[content]') {
        return JavaScript("_editor_url = \"".DATA_PATH."/themes/default/htmlarea2/\";
var win_ie_ver = parseFloat(navigator.appVersion.split(\"MSIE\")[1]);
if (navigator.userAgent.indexOf('Mac')        >= 0) { win_ie_ver = 0; }
if (navigator.userAgent.indexOf('Windows CE') >= 0) { win_ie_ver = 0; }
if (navigator.userAgent.indexOf('Opera')      >= 0) { win_ie_ver = 0; }
if (win_ie_ver >= 5.5) {
  document.write('<scr' + 'ipt src=\"' +_editor_url+ 'editor.js\"');
  document.write(' language=\"Javascript1.2\"></scr' + 'ipt>');
} else {
  document.write('<scr'+'ipt>function editor_generate() { return false; }</scr'+'ipt>'); 
}
 ",
		    array('version' => 'JavaScript1.2',
			  'type' => 'text/javascript'));
    }

    // to be called after </textarea>
    // version 2
    function Textarea($textarea,$wikitext,$name='edit[content]') {
        $out = HTML($textarea);
        // some more custom links 
        //$out->pushContent(HTML::a(array('href'=>"javascript:editor_insertHTML('".$name."',\"<font style='background-color: yellow'>\",'</font>',1)"),_("Highlight selected text")));
        //$out->pushContent(HTML("\n"));
        $out->pushContent(JavaScript("editor_generate('".$name."');",
                                     array('version' => 'JavaScript1.2',
                                           'defer' => 1)));
        return $out;
        //return "\n".'<script language="JavaScript1.2" defer> editor_generate(\'CONTENT\'); </script>'."\n";
    }

    // for testing only
    function Head_IEonly() {
        return HTML(JavaScript("_editor_url = \"".DATA_PATH."/themes/default/htmlarea2/\""),
                    "\n",
                    JavaScript("",
                               array('version' => 'JavaScript1.2',
                                     'type' => 'text/javascript',
                                     'src' => DATA_PATH."/themes/default/htmlarea2/editor.js")));
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
        //TODO: convert style formatting to simplier nested <b><i> tags
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