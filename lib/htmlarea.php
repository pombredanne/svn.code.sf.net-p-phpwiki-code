<?php
/**
 * Output the javascript function to check for MS Internet Explorer >= 5.5 on Windows
 * and call the real js script then, else just a nil func.
 * version 2: only for MSIE 5.5 and better
 * version 3: also Mozilla >= 1.3
 *
 * @package WysiwygEdit
 * @author Reini Urban
 */

function Edit_WYSIWYG_Head($name='edit[content]') {
    //return Edit_HtmlArea2_Head($name);
    return Edit_HtmlArea3_Head($name);
}
function Edit_WYSIWYG_Textarea($textarea,$wikitext,$name='edit[content]') {
    //return Edit_HtmlArea2_Textarea($textarea,$name);
    return Edit_HtmlArea3_Textarea($textarea,$wikitext,$name);
}

function Edit_HtmlArea2_Head($name='edit[content]') {
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
// for testing only
function Edit_HtmlAreaHead2_IEonly() {
    return HTML(JavaScript("_editor_url = \"".DATA_PATH."/themes/default/htmlarea2/\""),
                "\n",
                JavaScript("",
                           array('version' => 'JavaScript1.2',
                                 'type' => 'text/javascript',
                                 'src' => DATA_PATH."/themes/default/htmlarea2/editor.js")));
}

function Edit_HtmlArea3_Head($name='edit[content]') {
    global $WikiTheme;
    $WikiTheme->addMoreAttr('body'," onload='initEditor()'");
    //Todo: language selection from available lang/*.js files
    return new RawXml('
<script type="text/javascript" src="'.DATA_PATH.'/themes/default/htmlarea3/htmlarea.js"></script>
<script type="text/javascript" src="'.DATA_PATH.'/themes/default/htmlarea3/lang/en.js"></script>
<script type="text/javascript" src="'.DATA_PATH.'/themes/default/htmlarea3/dialog.js"></script> 
<style type="text/css">
@import url('.DATA_PATH.'/themes/default/htmlarea3/htmlarea.css);
</style>
<script type="text/javascript">
_editor_url = "'.DATA_PATH.'/themes/default/htmlarea3/";
var editor = null;
function initEditor() {
  editor = new HTMLArea("'.$name.'");

  // comment the following two lines to see how customization works
  editor.generate();
  return false;
  
  // BEGIN: code that adds custom buttons
  var cfg = editor.config; // this is the default configuration
  function clickHandler(editor, buttonId) {
    switch (buttonId) {
      case "my-toc":
        editor.insertHTML("<h1>Table Of Contents</h1>");
        break;
      case "my-date":
        editor.insertHTML((new Date()).toString());
        break;
      case "my-bold-em":
        editor.execCommand("bold");
        editor.execCommand("italic");
        break;
      case "my-hilite":
        editor.surroundHTML("<span class=\"hilite\">", "</span>");
        break;
    }
  };
  cfg.registerButton("my-toc",  "Insert TOC", _editor_url+"ed_custom.gif", false, clickHandler);
  cfg.registerButton("my-date", "Insert date/time", _editor_url+"ed_custom.gif", false, clickHandler);
  cfg.registerButton("my-bold-em", "Toggle bold/italic", _editor_url+"ed_custom.gif", false, clickHandler);
  cfg.registerButton("my-hilite", "Hilite selection", _editor_url+"ed_custom.gif", false, clickHandler);
  
  cfg.registerButton("my-sample", "Class: sample", _editor_url+"ed_custom.gif", false,
    function(editor) {
      if (HTMLArea.is_ie) {
        editor.insertHTML("<span class=\"sample\">&nbsp;&nbsp;</span>");
        var r = editor._doc.selection.createRange();
        r.move("character", -2);
        r.moveEnd("character", 2);
        r.select();
      } else { // Gecko/W3C compliant
        var n = editor._doc.createElement("span");
        n.className = "sample";
        editor.insertNodeAtSelection(n);
        var sel = editor._iframe.contentWindow.getSelection();
        sel.removeAllRanges();
        var r = editor._doc.createRange();
        r.setStart(n, 0);
        r.setEnd(n, 0);
        sel.addRange(r);
      }
    }
  );
  
  //cfg.pageStyle = "body { background-color: #efd; } .hilite { background-color: yellow; } "+
  //                ".sample { color: green; font-family: monospace; }";
  // add the new button to the toolbar
  //cfg.toolbar.push(["linebreak", "my-toc", "my-date", "my-bold-em", "my-hilite", "my-sample"]); 
  // END: code that adds custom buttons

  editor.generate();
}
function insertHTML() {
  var html = prompt("Enter some HTML code here");
  if (html) {
    editor.insertHTML(html);
  }
}
function highlight() {
  editor.surroundHTML(\'<span style="background-color: yellow">\', \'</span>\');
}
</script>
 ');
}

// to be called after </textarea>
// version 2
function Edit_HtmlArea2_Textarea($textarea,$wikitext,$name='edit[content]') {
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

function Edit_HtmlArea3_Textarea($textarea,$wikitext,$name='edit[content]') {
    $out = HTML($textarea,HTML::div(array("id"=>"editareawiki",'style'=>'display:none'),$wikitext),"\n");
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
 * This will be converted back by Edit_HtmlArea_ConvertAfter
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
