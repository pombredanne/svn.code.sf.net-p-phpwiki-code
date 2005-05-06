<?php
rcs_id('$Id: EditToolbar.php,v 1.2 2005-05-06 18:43:41 rurban Exp $');

/**
 * EDIT Toolbar Initialization.
 * The default/themes/toolbar.js is from mediawiki, this php is written from scratch.
 *
 * Features: 
 * - save-preview and formatting buttons from mediawiki
 * - Search&Replace from walterzorn.de
 * - pageinsert popup by Reini Urban (TODO: should be a pulldown, use acdropdown))
 *
 */

class EditToolbar {

    function EditToolbar() {
        global $WikiTheme;

        $this->tokens = array();

        //FIXME: enable Undo button for all other buttons also, not only the search/replace button
        if (JS_SEARCHREPLACE) {
            $this->tokens['JS_SEARCHREPLACE'] = 1;
            $undo_btn = $WikiTheme->getImageURL("ed_undo.png"); 
            $undo_d_btn = $WikiTheme->getImageURL("ed_undo_d.png"); 
            // JS_SEARCHREPLACE from walterzorn.de
            $WikiTheme->addMoreHeaders(Javascript("
var f, sr_undo, replacewin, undo_buffer=new Array(), undo_buffer_index=0;

function define_f() {
   f=document.getElementById('editpage');
   f.editarea=document.getElementById('edit[content]');
   sr_undo=document.getElementById('sr_undo');
   undo_enable(false);
   f.editarea.focus();
}
function undo_enable(bool) {
   if (bool) {
     sr_undo.src='".$undo_btn."';
     sr_undo.alt='"
._("Undo")
."';
     sr_undo.disabled = false;
   } else {
     sr_undo.src='".$undo_d_btn."';
     sr_undo.alt='"
._("Undo disabled")
."';
     sr_undo.disabled = true;
     if(sr_undo.blur) sr_undo.blur();
  }
}
function replace() {
   replacewin = window.open('','','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=yes,copyhistory=no,height=90,width=450');
   replacewin.window.document.write('<html><head><title>"
._("Search & Replace")
."</title><style type=\"text/css\"><'+'!'+'-- body, input {font-family:Tahoma,Arial,Helvetica,sans-serif;font-size:10pt;font-weight:bold;} td {font-size:9pt}  --'+'></style></head><body bgcolor=\"#dddddd\" onload=\"if(document.forms[0].ein.focus) document.forms[0].ein.focus(); return false;\"><form><center><table><tr><td align=\"right\">'+'"
._("Search")
.":</td><td align=\"left\"><input type=\"text\" name=\"ein\" size=\"45\" maxlength=\"500\"></td></tr><tr><td align=\"right\">'+' "
._("Replace with")
.":</td><td align=\"left\"><input type=\"text\" name=\"aus\" size=\"45\" maxlength=\"500\"></td></tr><tr><td colspan=\"2\" align=\"center\"><input type=\"button\" value=\" "
._("OK")
." \" onclick=\"if(self.opener)self.opener.do_replace(); return false;\">&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\""
._("Close")
."\" onclick=\"self.close(); return false;\"></td></tr></table></center></form></body></html>');
   replacewin.window.document.close();
   return false;
}
function do_replace() {
   var txt=undo_buffer[undo_buffer_index]=f.editarea.value, ein=new RegExp(replacewin.document.forms[0].ein.value,'g'), aus=replacewin.document.forms[0].aus.value;
   if (ein==''||ein==null) {
      if (replacewin) replacewin.window.document.forms[0].ein.focus();
      return;
   }
   var z_repl=txt.match(ein)? txt.match(ein).length : 0;
   txt=txt.replace(ein,aus);
   ein=ein.toString().substring(1,ein.toString().length-2);
   result(z_repl, '"
.sprintf(_("Substring \"%s\" found %s times. Replace with with \"%s\"?"), "'+ein+'", "'+z_repl+'", "'+aus+'")
."', txt, '"
.sprintf(_("String \"%s\" not found."), "'+ein+'")
."');
   replacewin.window.focus();
   replacewin.window.document.forms[0].ein.focus();
   return false;
}
function result(zahl,frage,txt,alert_txt) {
   if (zahl>0) {
      if(window.confirm(frage)==true) {
         f.editarea.value=txt;
         undo_save();
         undo_enable(true);
      }
   } else alert(alert_txt);
}
function do_undo() {
   if(undo_buffer_index==0) return;
   else if(undo_buffer_index>0) {
      f.editarea.value=undo_buffer[undo_buffer_index-1];
      undo_buffer[undo_buffer_index]=null;
      undo_buffer_index--;
      if(undo_buffer_index==0) {
         alert('".
_("Operation undone")
."');
         undo_enable(false);
      }
   }
}
//save a snapshot in the undo buffer (unused)
function undo_save() {
   undo_buffer[undo_buffer_index]=f.editarea.value;
   undo_buffer_index++;
   undo_enable(true);
}
"));
            $WikiTheme->addMoreAttr('body', "SearchReplace"," onload='define_f()'");
        } else {
            $WikiTheme->addMoreAttr('body', "editfocus", "document.getElementById('edit[content]').editarea.focus()");
        }
    
        if (ENABLE_EDIT_TOOLBAR) {
            $WikiTheme->addMoreHeaders(JavaScript('',array('src' => $WikiTheme->_findData("toolbar.js"))));
        }
        $this->tokens['EDIT_TOOLBAR'] = $this->_generate();
    }

    function getTokens () {
        return $this->tokens;
    }

    function _generate () {
        global $WikiTheme;

        $toolbar = "document.writeln(\"<div class=\\\"edit-toolbar\\\" id=\\\"toolbar\\\">\");\n";

        if (ENABLE_EDIT_TOOLBAR) {
            $toolarray = array(
                           array(
                                 "image"=>"ed_format_bold.png",
                                 "open"=>"*",
                                 "close"=>"*",
                                 "sample"=>_("Bold text"),
                                 "tip"=>_("Bold text")),
                           array("image"=>"ed_format_italic.png",
                                 "open"=>"_",
                                 "close"=>"_",
                                 "sample"=>_("Italic text"),
                                 "tip"=>_("Italic text")),
                           array("image"=>"ed_pagelink.png",
                                 "open"=>"[",
                                 "close"=>"]",
                                 "sample"=>_("optional label | PageName"),
                                 "tip"=>_("Link to page")),
                           array("image"=>"ed_link.png",
                                 "open"=>"[",
                                 "close"=>"]",
                                 "sample"=>_("optional label | http://www.example.com"),
                                 "tip"=>_("External link (remember http:// prefix)")),
                           array("image"=>"ed_headline.png",
                                 "open"=>"\\n!!! ",
                                 "close"=>"\\n",
                                 "sample"=>_("Headline text"),
                                 "tip"=>_("Level 1 headline")),
                           array("image"=>"ed_image.png",
                                 "open"=>"[ ",
                                 "close"=>" ]",
                                 "sample"=>_("Example.jpg"),
                                 "tip"=>_("Embedded image")),
                           array("image"=>"ed_nowiki.png",
                                 "open"=>"\\n\\<verbatim\\>\\n",
                                 "close"=>"\\n\\</verbatim\\>\\n",
                                 "sample"=>_("Insert non-formatted text here"),
                                 "tip"=>_("Ignore wiki formatting")),
                           array("image"=>"ed_sig.png",
                                 "open" => " --" . $GLOBALS['request']->_user->UserName(),
                                 "close" => "",
                                 "sample"=>"",
                                 "tip"=>_("Your signature")),
                           array("image"=>"ed_hr.png",
                                 "open"=>"\\n----\\n",
                                 "close"=>"",
                                 "sample"=>"",
                                 "tip"=>_("Horizontal line"))
                           );
            $btn = new SubmitImageButton(_("Save"), "edit[save]", 'toolbar', 
                                         $WikiTheme->getImageURL("ed_save.png"));
            $btn->addTooltip(_("Save"));
            $toolbar .= ('document.writeln("'.addslashes($btn->asXml()).'");'."\n");
            $btn = new SubmitImageButton(_("Preview"), "edit[preview]", 'toolbar', 
                                         $WikiTheme->getImageURL("ed_preview.png"));
            $btn->addTooltip(_("Preview"));
            $toolbar .= ('document.writeln("'.addslashes($btn->asXml()).'");'."\n");
    
            foreach ($toolarray as $tool) {
                $image = $WikiTheme->getImageURL($tool["image"]);
                $open  = $tool["open"];
                $close = $tool["close"];
                $sample = addslashes( $tool["sample"] );
                // Note that we use the tip both for the ALT tag and the TITLE tag of the image.
                // Older browsers show a "speedtip" type message only for ALT.
                // Ideally these should be different, realistically they
                // probably don't need to be.
                $tip = addslashes( $tool["tip"] );
                $toolbar .= ("addTagButton('$image','$tip','$open','$close','$sample');\n");
            }
            $toolbar .= ("addInfobox('" 
                         . addslashes( _("Click a button to get an example text") ) 
                         . "');\n");
        }

        if (JS_SEARCHREPLACE) {
            $undo_d_btn = $WikiTheme->getImageURL("ed_undo_d.png"); 
            //$redo_btn = $WikiTheme->getImageURL("ed_redo.png");
            $sr_btn   = $WikiTheme->getImageURL("ed_replace.png");
            $sr_html = HTML(HTML::img(array('class'=>"toolbar",
                                            'id'   =>"sr_undo",
                                            'src'  =>$undo_d_btn,
                                            'title'=>_("Undo Search & Replace"),
                                            'alt'  =>_("Undo Search & Replace"),
                                            'disabled'=>"disabled", 
                                            'value'   =>"Undo",
                                            'onfocus' =>"if(this.blur && undo_buffer_index==0) this.blur()",
                                            'onclick' =>"do_undo()")),
                            HTML::img(array('class'=>"toolbar",
                                            'src'  => $sr_btn,
                                            'alt'  =>_("Search & Replace"),
                                            'title'=>_("Search & Replace"),
                                            'onclick'=>"replace()")));
        } else {
            $sr_html = '';
        }

        //TODO: delegate these calculations to a seperate popup action request
        //   action=pulldown or xmlrpc/soap (see google: WebServiceProxyFactory.createProxyAsync)

        // Button to generate categories, display in extra window as popup and insert
        $sr_html = HTML($sr_html, $this->categoriesPulldown());
        // Button to generate plugins, display in extra window as popup and insert
        $sr_html = HTML($sr_html, $this->pluginPulldown());

        // Button to generate pagenames, display in extra window as popup and insert
        if (TOOLBAR_PAGELINK_PULLDOWN)
            $sr_html = HTML($sr_html, $this->pagesPulldown(TOOLBAR_PAGELINK_PULLDOWN));
        // Button to insert from an template, display pagename in extra window as popup and insert
        if (TOOLBAR_TEMPLATE_PULLDOWN)
            $sr_html = HTML($sr_html, $this->templatePulldown(TOOLBAR_TEMPLATE_PULLDOWN));


        // don't use document.write for replace, otherwise self.opener is not defined.
        $toolbar_end = "document.writeln(\"</div>\");";
        if ($sr_html)
            return HTML(Javascript($toolbar),
                        "\n", $sr_html, "\n",
                        Javascript($toolbar_end));
        else
            return HTML(Javascript($toolbar . $toolbar_end));
    }

    function categoriesPulldown() {
        global $WikiTheme;

        require_once('lib/TextSearchQuery.php');
        $dbi =& $GLOBALS['request']->_dbi;
        $pages = $dbi->titleSearch(new TextSearchQuery(''.
                                                       _("Category").' OR '.
                                                       _("Topic").''));
        if ($pages->count()) {
            $categories = array();
            while ($p = $pages->next()){
                $categories[] = $p->getName();
            }
            $more_buttons = HTML::img(array('class'=>"toolbar",
                                            'src'  => $WikiTheme->getImageURL("ed_category.png"),
                                            'title'=>_("AddCategory"),
                                            'onclick'=>"showPulldown('".
                                            _("Insert Categories (double-click)")
                                            ."',['".join("','",$categories)."'],'"
                                            ._("Insert")."','"
                                            ._("Close")
                                            ."')"));
            return HTML("\n", $more_buttons);
        }
        return '';
    }

    function pluginPulldown() {
        global $WikiTheme;

        $plugin_dir = 'lib/plugin';
        if (defined('PHPWIKI_DIR'))
            $plugin_dir = PHPWIKI_DIR . "/$plugin_dir";
        $pd = new fileSet($plugin_dir, '*.php');
        $plugins = $pd->getFiles();
        unset($pd);
        sort($plugins);

        if (!empty($plugins)) {
            $plugin_js = '';
            foreach ($plugins as $plugin) {
                $pluginName = str_replace(".php", "", $plugin);
                $toinsert = "<?plugin $pluginName ?>"; // args?
                $plugin_js .= ",['$pluginName','$toinsert']";
            }
            $plugin_js = substr($plugin_js, 1);
            $more_buttons = HTML::img(array('class'=>"toolbar",
                                            'src'  => $WikiTheme->getImageURL("ed_plugins.png"),
                                            'title'=>_("AddPlugin"),
                                            'onclick'=>"showPulldown('".
                                            _("Insert Plugin (double-click)")
                                            ."',[".$plugin_js."],'"
                                            ._("Insert")."','"
                                            ._("Close")
                                            ."')"));
            return HTML("\n", $more_buttons);
        }
        return '';
    }

    function pagesPulldown($query, $case_exact=false, $regex='auto') {
        require_once('lib/TextSearchQuery.php');
        $dbi =& $GLOBALS['request']->_dbi;
        $page_iter = $dbi->titleSearch(new TextSearchQuery($query, $case_exact, $regex));
        if ($page_iter->count()) {
            global $WikiTheme;
            $pages = array();
            while ($p = $page_iter->next()){
                $pages[] = $p->getName();
            }
            return HTML("\n", HTML::img(array('class'=>"toolbar",
                                              'src'  => $WikiTheme->getImageURL("ed_pages.png"),
                                              'title'=>_("AddPageLink"),
                                              'onclick'=>"showPulldown('".
                                              _("Insert PageLink (double-click)")
                                              ."',['".join("','",$pages)."'],'"
                                              ._("Insert")."','"
                                              ._("Close")
                                              ."')")));
        }
        return '';
    }

    function templatePulldown($query, $case_exact=false, $regex='auto') {
        require_once('lib/TextSearchQuery.php');
        $dbi =& $GLOBALS['request']->_dbi;
        $page_iter = $dbi->titleSearch(new TextSearchQuery($query, $case_exact, $regex));
        if ($page_iter->count()) {
            global $WikiTheme;
            $pages_js = '';
            while ($p = $page_iter->next()) {
                $rev = $p->getCurrentRevision();
                $toinsert = str_replace("\n",'\n',addslashes($rev->_get_content()));
                $pages_js .= ",['".$p->getName()."','$toinsert']";
            }
            $pages_js = substr($pages_js, 1);
            if (!empty($pages_js))
                return HTML("\n", HTML::img
                            (array('class'=>"toolbar",
                                   'src'  => $WikiTheme->getImageURL("ed_template.png"),
                                   'title'=>_("AddTemplate"),
                                   'onclick'=>"showPulldown('".
                                   _("Insert Template (double-click)")
                                   ."',[".$pages_js."],'"
                                   ._("Insert")."','"
                                   ._("Close")
                                   ."')")));
        }
        return '';
    }

}

/*
 $Log: not supported by cvs2svn $
 Revision 1.1  2005/01/25 15:19:09  rurban
 extract Toolbar code from editpage.php


*/

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>