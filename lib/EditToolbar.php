<?php
rcs_id('$Id: EditToolbar.php,v 1.15 2008-04-02 18:07:05 vargenau Exp $');

/**
 * EDIT Toolbar Initialization.
 * The default/themes/toolbar.js is from mediawiki, this php is written from scratch.
 *
 * Features: 
 * - save-preview and formatting buttons from mediawiki
 * - Search&Replace from walterzorn.de
 * - pageinsert popup by Reini Urban (TODO: should be a pulldown, use acdropdown))
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
	    $js = Javascript("
uri_undo_btn   = '".$undo_btn."'
msg_undo_alt   = '"._("Undo")."'
uri_undo_d_btn = '".$undo_d_btn."'
msg_undo_d_alt = '"._("Undo disabled")."'
msg_do_undo    = '"._("Operation undone")."'
msg_replfound  = '"._("Substring \"\\1\" found \\2 times. Replace with \"\\3\"?")."'
msg_replnot    = '"._("String \"%s\" not found.")."'
msg_repl_title     = '"._("Search & Replace")."'
msg_repl_search    = '"._("Search for")."'
msg_repl_replace_with = '"._("Replace with")."'
msg_repl_ok        = '"._("OK")."'
msg_repl_close     = '"._("Close")."'
");
            if (empty($WikiTheme->_headers_printed)) {
		$WikiTheme->addMoreHeaders($js);
		$WikiTheme->addMoreAttr('body', "SearchReplace"," onload='define_f()'");
	    } else { // from an actionpage: WikiBlog, AddComment, WikiForum
		printXML($js);
	    }
        } else {
            $WikiTheme->addMoreAttr('body', "editfocus", "document.getElementById('edit-content]').editarea.focus()");
        }
    
        if (ENABLE_EDIT_TOOLBAR) {
	    $js = JavaScript('',array('src' => $WikiTheme->_findData("toolbar.js")));
            if (empty($WikiTheme->_headers_printed))
		$WikiTheme->addMoreHeaders($js);
	    else { // from an actionpage: WikiBlog, AddComment, WikiForum
		printXML($js);
		printXML(JavaScript('define_f()'));
	    }
        }

        require_once("lib/WikiPluginCached.php");
        $cache = WikiPluginCached::newCache();
        $dbi = $GLOBALS['request']->getDbh();
        // regenerate if number of pages changes (categories, pages, templates)
        $key = $dbi->numPages();
        $key .= '+categories+plugin';
        if (TOOLBAR_PAGELINK_PULLDOWN) {
            $key .= "+pages";
        }
        if (TOOLBAR_TEMPLATE_PULLDOWN) {
            $key .= "+templates_" . $dbi->getTimestamp();
        }
        $id = $cache->generateId($key);
        $content = $cache->get($id, 'toolbarcache');

        if (!empty($content)) {
            $this->tokens['EDIT_TOOLBAR'] =& $content;
        } else {
            $content = $this->_generate();
            // regenerate buttons every 3600 seconds
            $cache->save($id, $content, '+3600', 'toolbarcache'); 
            $this->tokens['EDIT_TOOLBAR'] =& $content;
        }
    }

    function getTokens () {
        return $this->tokens;
    }

    function _generate () {
        global $WikiTheme, $request;

        $toolbar = "document.writeln(\"<div class=\\\"edit-toolbar\\\" id=\\\"toolbar\\\">\");\n";
        $accessKeyPrefix = $WikiTheme->tooltipAccessKeyPrefix();

        if (ENABLE_EDIT_TOOLBAR) {
            $username = $request->_user->UserName();
            if (DISABLE_MARKUP_WIKIWORD or (!isWikiWord($username))) {
                $username = '['.$username.']';
            }
	    $signature = " --".$username." ".CTime();
            $toolarray = array(
                           array(
                                 "image"=>"ed_format_bold.png",
                                 "open"=>"<strong>",
                                 "close"=>"</strong>",
                                 "sample"=>_("Bold text"),
                                 "title"=>_("Bold text [alt-b]")),
                           array("image"=>"ed_format_italic.png",
                                 "open"=>"<em>",
                                 "close"=>"</em>",
                                 "sample"=>_("Italic text"),
                                 "title"=>_("Italic text [alt-i]")),
                           array("image"=>"ed_format_strike.png",
                                 "open"=>"<s>",
                                 "close"=>"</s>",
                                 "sample"=>_("Strike-through text"),
                                 "title"=>_("Strike")),
                           array("image"=>"ed_format_color.png",
                                 "open"=>"%color=green% ",
                                 "close"=>" %%",
                                 "sample"=>_("Color text"),
                                 "title"=>_("Color")),
                           array("image"=>"ed_pagelink.png",
                                 "open"=>"[",
                                 "close"=>"]",
                                 "sample"=>_("optional label | PageName"),
                                 "title"=>_("Link to page")),
                           array("image"=>"ed_link.png",
                                 "open"=>"[",
                                 "close"=>"]",
                                 "sample"=>_("optional label | http://www.example.com"),
                                 "title"=>_("External link (remember http:// prefix)")),
                           array("image"=>"ed_headline.png",
                                 "open"=>"\\n!!! ",
                                 "close"=>"\\n",
                                 "sample"=>_("Headline text"),
                                 "title"=>_("Level 1 headline")),
                           array("image"=>"ed_image.png",
                                 "open"=>"[ ",
                                 "close"=>" ]",
                                 "sample"=>_("Example.jpg"),
                                 "title"=>_("Embedded image")),
                           array("image"=>"ed_nowiki.png",
                                 "open"=>"\\<verbatim\\>\\n",
                                 "close"=>"\\n\\</verbatim\\>",
                                 "sample"=>_("Insert non-formatted text here"),
                                 "title"=>_("Ignore wiki formatting")),
                           array("image"=>"ed_sig.png",
                                 "open" => $signature,
                                 "close" => "",
                                 "sample"=>"",
                                 "title"=>_("Your signature")),
                           array("image"=>"ed_hr.png",
                                 "open"=>"\\n----\\n",
                                 "close"=>"",
                                 "sample"=>"",
                                 "title"=>_("Horizontal line")),
                           array("image"=>"ed_table.png",
                                 "open"=>"\\n{| class=\"bordered\"\\n|-\\n| Cell A1 || Cell B1 || Cell C1\\n|-\\n| Cell A2 || Cell B2 || Cell C2\\n|-\\n| Cell A3 || Cell B3 || Cell C3\\n|}\\n",
                                 "close"=>"",
                                 "sample"=>"",
                                 "title"=>_("Sample table")),
                           array("image"=>"ed_enumlist.png",
                                 "open"=>"\\n# Item 1\\n# Item 2\\n# Item 3\\n",
                                 "close"=>"",
                                 "sample"=>"",
                                 "title"=>_("Enumeration")),
                           array("image"=>"ed_list.png",
                                 "open"=>"\\n* Item 1\\n* Item 2\\n* Item 3\\n",
                                 "close"=>"",
                                 "sample"=>"",
                                 "title"=>_("List")),
                           array("image"=>"ed_toc.png",
                                 "open"=>"<?plugin CreateToc with_toclink||=1 headers||=1,2,3 liststyle||=ol?>\\n",
                                 "close"=>"",
                                 "sample"=>"",
                                 "title"=>_("Table of Contents")),
                           array("image"=>"ed_redirect.png",
                                 "open"=>"<?plugin RedirectTo page=\"",
                                 "close"=>"\" ?>",
                                 "sample"=>_("Page Name"),
                                 "title"=>_("Redirect")),
                           array("image"=>"ed_templateplugin.png",
                                 "open"=>"{{",
                                 "close"=>"}}",
                                 "sample"=>_("template name"),
                                 "title"=>_("Template"))
                           );
            $btn = new SubmitImageButton(_("Save"), "edit[save]", 'toolbar', 
                                         $WikiTheme->getImageURL("ed_save.png"));
            $btn->addTooltip(_("Save"));
	    $btn->setAccesskey("s");
            $toolbar .= ('document.writeln("'.addslashes($btn->asXml()).'");'."\n");
	    // preview not supported yet on Wikiblog
            if (empty($WikiTheme->_headers_printed)) {
		$btn = new SubmitImageButton(_("Preview"), "edit[preview]", 'toolbar', 
					     $WikiTheme->getImageURL("ed_preview.png"));
		$btn->addTooltip(_("Preview"));
		$btn->setAccesskey("p");
		$toolbar .= ('document.writeln("'.addslashes($btn->asXml()).'");'."\n");
	    }
    
            foreach ($toolarray as $tool) {
            	global $WikiTheme;
                $image = $WikiTheme->getImageURL($tool["image"]);
                $open  = $tool["open"];
                $close = $tool["close"];
                $sample = addslashes( $tool["sample"] );
                // Note that we use the title both for the ALT tag and the TITLE tag of the image.
                // Older browsers show a "speedtip" type message only for ALT.
                // Ideally these should be different, realistically they
                // probably don't need to be.
                $tool = $WikiTheme->fixAccesskey($tool);
                $title = addslashes( $tool["title"] );
                $toolbar .= ("addTagButton('$image','$title','$open','$close','$sample');\n");
            }
            $toolbar .= ("addInfobox('" 
                         . addslashes( _("Click a button to get an example text") ) 
                         . "');\n");
        }

        if (JS_SEARCHREPLACE) {
            $undo_d_btn = $WikiTheme->getImageURL("ed_undo_d.png"); 
            //$redo_btn = $WikiTheme->getImageURL("ed_redo.png");
            $sr_btn   = $WikiTheme->getImageURL("ed_replace.png");
            //TODO: generalize the UNDO button and fix it for Search & Replace
            $sr_html = HTML(HTML::img
                            (array('class'=>"toolbar",
                                   'id'   =>"sr_undo",
                                   'src'  =>$undo_d_btn,
                                   'title'=>_("Undo Search & Replace"),
                                   'alt'  =>_("Undo Search & Replace"),
                                   //'disabled'=>"disabled",   //non-XHTML conform
                                   //'onfocus' =>"if(this.blur && undo_buffer_index==0) this.blur()",
                                   'onclick' =>"do_undo()")),
                            HTML::img
                            (array('class'=>"toolbar",
                                   'src'  => $sr_btn,
                                   'alt'  =>_("Search & Replace"),
                                   'title'=>_("Search & Replace")." [$accessKeyPrefix-h]",
                                   'accesskey' => 'h',
                                   'onclick'=>"replace()")));
        } else {
            $sr_html = '';
        }

        //TODO: Delegate this to run-time with showing an hidden input at the right, and do 
	// a seperate moacdropdown and xmlrpc:titleSearch.

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

    //result is cached
    function categoriesPulldown() {
        global $WikiTheme;

        require_once('lib/TextSearchQuery.php');
        $dbi =& $GLOBALS['request']->_dbi;
        // KEYWORDS formerly known as $KeywordLinkRegexp
        $pages = $dbi->titleSearch(new TextSearchQuery(KEYWORDS, true));
        if ($pages) {
            $categories = array();
            while ($p = $pages->next()) {
		$page = $p->getName();
		if (DISABLE_MARKUP_WIKIWORD or (!isWikiWord($page)))
		    $categories[] = "['$page', '%5B".$page."%5D']";
		else
		    $categories[] = "['$page', '$page']";
            }
            if (!$categories) return '';
	    // Ensure this to be inserted at the very end. Hence we added the id to the function.
            $more_buttons = HTML::img(array('class'=> "toolbar",
					    'id' => 'tb-categories',
                                            'src'  => $WikiTheme->getImageURL("ed_category.png"),
                                            'title'=>_("AddCategory"),
                                            'alt'=>"AddCategory", // to detect this at js
                                            'onclick'=>"showPulldown('".
                                            _("Insert Categories (double-click)")
                                            ."',[".join(",",$categories)."],'"
                                            ._("Insert")."','"
                                            ._("Close")."','tb-categories')"));
            return HTML("\n", $more_buttons);
        }
        return '';
    }

    // result is cached. Esp. the args are expensive
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
            require_once("lib/WikiPlugin.php");
            $w = new WikiPluginLoader;
            foreach ($plugins as $plugin) {
                $pluginName = str_replace(".php", "", $plugin);
                $p = $w->getPlugin($pluginName, false); // second arg?
                // trap php files which aren't WikiPlugin~s
                if (strtolower(substr(get_parent_class($p), 0, 10)) == 'wikiplugin') {
                    $plugin_args = '';
                    $desc = $p->getArgumentsDescription();
                    $src = array("\n",'"',"'",'|','[',']','\\');
                    $replace = array('%0A','%22','%27','%7C','%5B','%5D','%5C');
                    $desc = str_replace("<br />",' ',$desc->asXML());
                    if ($desc)
                        $plugin_args = ' '.str_replace($src, $replace, $desc);
                    $toinsert = "%0A<?plugin ".$pluginName.$plugin_args."?>"; // args?
                    $plugin_js .= ",['$pluginName','$toinsert']";
                }
            }
            $plugin_js = substr($plugin_js, 1);
            $more_buttons = HTML::img(array('class'=>"toolbar",
					    'id' => 'tb-plugins',
                                            'src'  => $WikiTheme->getImageURL("ed_plugins.png"),
                                            'title'=>_("AddPlugin"),
                                            'alt'=>_("AddPlugin"),
                                            'onclick'=>"showPulldown('".
                                            _("Insert Plugin (double-click)")
                                            ."',[".$plugin_js."],'"
                                            ._("Insert")."','"
                                            ._("Close")."','tb-plugins')"));
            return HTML("\n", $more_buttons);
        }
        return '';
    }

    // result is cached. Esp. the args are expensive
    function pagesPulldown($query, $case_exact=false, $regex='auto') {
        require_once('lib/TextSearchQuery.php');
        $dbi =& $GLOBALS['request']->_dbi;
        $page_iter = $dbi->titleSearch(new TextSearchQuery($query, $case_exact, $regex));
        if ($page_iter->count()) {
            global $WikiTheme;
            $pages = array();
            while ($p = $page_iter->next()) {
		$page = $p->getName();
		if (DISABLE_MARKUP_WIKIWORD or (!isWikiWord($page)))
		    $pages[] = "['$page', '%5B".$page."%5D']";
		else
		    $pages[] = "['$page', '$page']";
            }
            return HTML("\n", HTML::img(array('class'=>"toolbar",
					      'id' => 'tb-pages',
                                              'src'  => $WikiTheme->getImageURL("ed_pages.png"),
                                              'title'=>_("AddPageLink"),
                                              'alt'=>_("AddPageLink"),
                                              'onclick'=>"showPulldown('".
                                              _("Insert PageLink (double-click)")
                                              ."',[".join(",",$pages)."],'"
                                              ._("Insert")."','"
                                              ._("Close")."','tb-pages')")));
        }
        return '';
    }

    // result is cached. Esp. the args are expensive
    // FIXME!
    function templatePulldown($query, $case_exact=false, $regex='auto') {
        global $request;
        require_once('lib/TextSearchQuery.php');
        $dbi =& $request->_dbi;
        $page_iter = $dbi->titleSearch(new TextSearchQuery($query, $case_exact, $regex));
        $count = 0;
        if ($page_iter->count()) {
            global $WikiTheme;
            $pages_js = '';
            while ($p = $page_iter->next()) {
                $rev = $p->getCurrentRevision();
                $toinsert = str_replace(array("\n",'"'), array('_nl','_quot'), $rev->_get_content());
                //$toinsert = str_replace("\n",'\n',addslashes($rev->_get_content()));
                $pages_js .= ",['".$p->getName()."','_nl$toinsert']";
            }
            $pages_js = substr($pages_js, 1);
            if (!empty($pages_js))
                return HTML("\n", HTML::img
                            (array('class'=>"toolbar",
				   'id' => 'tb-templates',
                                   'src'  => $WikiTheme->getImageURL("ed_template.png"),
                                   'title'=>_("AddTemplate"),
                                   'alt'=>_("AddTemplate"),
                                   'onclick'=>"showPulldown('".
                                   _("Insert Template (double-click)")
                                   ."',[".$pages_js."],'"
                                   ._("Insert")."','"
                                   ._("Close")."','tb-templates')")));
        }
        return '';
    }

}

/*
$Log: not supported by cvs2svn $
Revision 1.14  2008/02/19 19:07:23  vargenau
More icons for toolbar

Revision 1.13  2007/07/14 12:03:12  rurban
just aesthetics

Revision 1.12  2007/06/02 18:23:36  rurban
Added accesskeys

Revision 1.11  2007/02/17 14:16:21  rurban
move define_f after toolbar.js

Revision 1.10  2007/01/07 18:42:19  rurban
Improve id: edit: to edit-. Move search&replace js from body (defined in EditToolbar) to the toolbar.js. Support actionpages. Add tb-name argument to showPulldown

Revision 1.9  2007/01/02 13:18:26  rurban
fix id to edit:content

Revision 1.8  2006/12/22 00:17:06  rurban
add time to signature

Revision 1.7  2006/09/06 05:45:26  rurban
use html tags for emphasis. workaround ^* problem

Revision 1.6  2006/08/30 05:25:40  rurban
Handle inserting DISABLE_MARKUP_WIKIWORD and non wikiword links.

Revision 1.5  2005/10/29 14:16:17  rurban
fix typo

Revision 1.4  2005/09/29 23:07:58  rurban
cache toolbar

Revision 1.3  2005/09/26 06:25:50  rurban
EditToolbar enhancements by Thomas Harding: add plugins args, properly quote control chars. added plugin method getArgumentsDescription to override the default description string

Revision 1.3  2005/09/22 13:40:00 tharding
add modules arguments
 
Revision 1.2  2005/05/06 18:43:41  rurban
add AddTemplate EditToolbar icon

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
