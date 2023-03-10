<?php
/**
 * Copyright © 2005 Reini Urban
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
 * requires installation into themes/default/htmlarea3/
 * MSIE => 5.5,  Mozilla >= 1.3
 *
 * @package WysiwygEdit
 * @author Reini Urban
 */

require_once 'lib/WysiwygEdit.php';

class WysiwygEdit_htmlarea3 extends WysiwygEdit
{
    public function Head($name = 'edit[content]')
    {
        global $WikiTheme;
        $WikiTheme->addMoreAttr('body', " onload='initEditor()'");
        //Todo: language selection from available lang/*.js files
        return new RawXml('
<script type="text/javascript" src="' . DATA_PATH . '/themes/default/htmlarea3/htmlarea.js"></script>
<script type="text/javascript" src="' . DATA_PATH . '/themes/default/htmlarea3/lang/en.js"></script>
<script type="text/javascript" src="' . DATA_PATH . '/themes/default/htmlarea3/dialog.js"></script>
<style type="text/css">
@import url(' . DATA_PATH . '/themes/default/htmlarea3/htmlarea.css);
</style>
<script type="text/javascript">
_editor_url = "' . DATA_PATH . '/themes/default/htmlarea3/";
var editor = null;
function initEditor() {
  editor = new HTMLArea("' . $name . '");

  // comment the following two lines to see how customization works
  editor.generate();
  return false;

  // BEGIN: code that adds custom buttons
  var cfg = editor.config; // this is the default configuration
  function clickHandler(editor, buttonId) {
    switch (buttonId) {
      case "my-toc":
        editor.insertHTML("<?plugin CreateToc ?>");
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

    public function Textarea($textarea, $wikitext, $name = 'edit[content]')
    {
        $out = HTML($textarea, HTML::div(array("id" => "editareawiki", 'style' => 'display:none'), $wikitext), "\n");
        //TODO: maybe some more custom links
        return $out;
    }
}
