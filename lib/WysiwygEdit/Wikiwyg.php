<?php
/**
 * Copyright © 2006 Reini Urban, Jean-Nicolas Gereone
 * Copyright © 2007 Sabri Labbenes
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
 * Wikiwyg is compatible with most internet browsers which
 * include: IE 5.5+ (Windows), Firefox 1.0+, Mozilla 1.3+
 * and Netscape 7+.
 *
 * Download: http://openjsan.org/doc/i/in/ingy/Wikiwyg/
 * Suggested installation into themes/default/Wikiwyg/
 *
 * @package WysiwygEdit
 * @author  Reini Urban, based on a patch by Jean-Nicolas GEREONE, STMicroelectronics, 2006
 * Current maintainer: Sabri LABBENE, STMicroelectronics, 2006
 */

require_once 'lib/WysiwygEdit.php';

class WysiwygEdit_Wikiwyg extends WysiwygEdit
{
    public function __construct()
    {
        $this->_transformer_tags = false;
        $this->BasePath = DATA_PATH . '/themes/default/Wikiwyg';
        $this->_htmltextid = "edit-content";
        $this->_wikitextid = "editareawiki";
        $script_url = deduce_script_name();
        if ((DEBUG & _DEBUG_REMOTE) and isset($_GET['start_debug'])) {
            $script_url .= ("?start_debug=" . $_GET['start_debug']);
        }
        $this->_jsdefault = "";
    }

    public function Head($name = 'edit[content]')
    {
        global $WikiTheme;
        /**
         * @var WikiRequest $request
         */
        global $request;

        foreach (array("Wikiwyg.js", "Wikiwyg/Toolbar.js", "Wikiwyg/Preview.js", "Wikiwyg/Wikitext.js",
                     "Wikiwyg/Wysiwyg.js", "Wikiwyg/Phpwiki.js", "Wikiwyg/HTML.js",
                     "Wikiwyg/Toolbar.js") as $js) {
            $WikiTheme->addMoreHeaders(JavaScript('', array('src' => $this->BasePath . '/' . $js,
                'language' => 'JavaScript')));
        }
        $doubleClickToEdit = ($request->getPref('doubleClickEdit') or ENABLE_DOUBLECLICKEDIT)
            ? 'true' : 'false';
        if ($request->getArg('mode') && $request->getArg('mode') == 'wysiwyg') {
            return JavaScript(
                $this->_jsdefault . "
            window.onload = function() {
            var wikiwyg = new Wikiwyg.Phpwiki();
            var config = {
            doubleClickToEdit:  $doubleClickToEdit,
            javascriptLocation: data_path+'/themes/default/Wikiwyg/',
            toolbar: {
            imagesLocation: data_path+'/themes/default/Wikiwyg/images/',
        controlLayout: [
               'save','preview','save_button','|',
               'p','|',
               'h2', 'h3', 'h4','|',
               'bold', 'italic', '|',
                       'sup', 'sub', '|',
                       'toc',
                       'wikitext','|',
               'pre','|',
               'ordered', 'unordered','hr','|',
               'link','|',
                       'table'
               ],
        styleSelector: [
               'label', 'p', 'h2', 'h3', 'h4', 'pre'
                ],
        controlLabels: {
                   save:     '" . _("Apply changes") . "',
               cancel:   '" . _("Exit toolbar") . "',
               h2:       '" . _("Title 1") . "',
               h3:       '" . _("Title 2") . "',
               h4:       '" . _("Title 3") . "',
               verbatim: '" . _("Verbatim") . "',
                       toc:   '" . _("Table of Contents") . "',
                       wikitext:   '" . _("Insert Wikitext section") . "',
                       sup:      '" . _("Sup") . "',
                       sub:      '" . _("Sub") . "',
                       preview:  '" . _("Preview") . "',
                       save_button:'" . _("Save") . "'
                  }
            },
            wysiwyg: {
                iframeId: 'iframe0'
            },
        wikitext: {
          supportCamelCaseLinks: true
        }
            };
            var div = document.getElementById(\"" . $this->_htmltextid . "\");
            wikiwyg.createWikiwygArea(div, config);
            wikiwyg_divs.push(wikiwyg);
            wikiwyg.editMode();}"
            );
        }
        return '';
    }

    public function Textarea($textarea, $wikitext, $name = 'edit[content]')
    {
        global $request;

        $htmltextid = $this->_htmltextid;
        $textarea->SetAttr('id', $htmltextid);
        $iframe0 = new RawXml('<iframe id="iframe0" src="blank.htm" height="0" width="0" frameborder="0"></iframe>');
        if ($request->getArg('mode') and $request->getArg('mode') == 'wysiwyg') {
            $out = HTML(
                HTML::div(
                array('class' => 'hint'),
                _("Warning")._(": ")._("This Wikiwyg editor has only Beta quality!")
            ),
                $textarea,
                $iframe0,
                "\n"
            );
        } else {
            $out = HTML($textarea, $iframe0, "\n");
        }
        return $out;
    }

    /**
     * Handler to convert the Wiki Markup to HTML before editing.
     * This will be converted back by WysiwygEdit_ConvertAfter if required.
     *  *text* => '<b>text<b>'
     *
     * @param string $text
     * @return string
     */
    public function ConvertBefore($text)
    {
        return $text;
    }

    /**
     * No special PHP HTML->Wikitext conversion needed. This is done in js thanksfully.
     * Avoided in editpage.php: PageEditor->getContent
     *
     * @param string $text
     * @return string
     */
    public function ConvertAfter($text)
    {
        return TransformInline($text);
    }
}

class WikiToHtml
{
    public function __construct($wikitext, &$request)
    {
        $this->_wikitext = $wikitext;
        $this->_request =& $request;
        $this->_html = "";
        $this->html_content = "";
    }

    public function send()
    {
        $this->convert();
        echo $this->html_content;
    }

    public function convert()
    {
        require_once 'lib/BlockParser.php';
        $xmlcontent = TransformText($this->_wikitext, $this->_request->getArg('pagename'));
        $this->_html = $xmlcontent->AsXML();

        $this->replace_inside_html();
    }

    public function replace_inside_html()
    {
        $this->clean_links();
        $this->clean_plugin_name();
        $this->replace_known_plugins();
        $this->replace_unknown_plugins();
        // $this->replace_tags();
        $this->clean_plugin();
        $this->html_content = $this->_html;
    }

    // Draft function to replace RichTable
    // by a html table
    // Works only on one plugin for the moment
    public function replace_known_plugins()
    {
        // If match a plugin
        $pattern = '/\&lt\;\?plugin\s+RichTable(.*)\?\&gt\;/Umsi';
        $replace_string = "replace_rich_table";
        $this->_html = preg_replace_callback(
            $pattern,
            $replace_string,
            $this->_html
        );
    }

    // Replace unknown plugins by keyword Wikitext { tag }
    public function replace_unknown_plugins()
    {
        $pattern = '/(\&lt\;\?plugin[^?]*\?\&gt\;)/Usi';
        $replace_string =
            '<p><div style="background-color:#D3D3D3;font-size:smaller;">Wikitext {
 <br> \1 <br>}</div><br></p>';

        $this->_html = preg_replace(
            $pattern,
            $replace_string,
            $this->_html
        );
    }

    // Clean links to keep only <a href="link">name</a>
    public function clean_links()
    {
        // Existing links
        // FIXME: use VIRTUAL_PATH
        $pattern = '/\<a href\=\"index.php\?pagename\=(\w+)\"([^>])*\>/Umsi';
        $replace_string = '<a href="\1">';
        $this->_html = preg_replace(
            $pattern,
            $replace_string,
            $this->_html
        );
        // Non existing links
        $pattern = '/\<a href\=\"index.php\?pagename\=([^"]*)(&amp;action){1}([^>])*\>/Umsi';
        $replace_string = '<a href="\1">';

        $this->_html = preg_replace(
            $pattern,
            $replace_string,
            $this->_html
        );

        // Clean underline
        $pattern = '/\<u\>(.*)\<\/u\>(\<a href="(.*))[?"]{1}.*\>.*\<\/a\>/Umsi';
        $replace_string =
            '<span>\2" style="color:blue;">\1</a></span>';

        $this->_html = preg_replace(
            $pattern,
            $replace_string,
            $this->_html
        );
    }

    // Put unknown tags in Wikitext {}
    public function replace_tags()
    {
        // Replace old table format ( non plugin )
        $pattern = '/(\ {0,4}(?:\S.*)?\|\S+\s*$.*?\<\/p\>)/ms';
        $replace_string =
            '<p><div style="background-color:#D3D3D3;font-size:smaller;">Wikitext {
 <br> \1 <br>}</div><br></p>';

        $this->_html = preg_replace(
            $pattern,
            $replace_string,
            $this->_html
        );
    }

    // Replace \n by <br> only in
    // <?plugin ? > tag to keep formatting
    public function clean_plugin()
    {
        $pattern = '/(\&lt\;\?plugin.*\?\&gt\;)/Umsei';
        $replace_string = 'preg_replace("/\n/Ums","<br>","\1")';

        $this->_html = preg_replace(
            $pattern,
            $replace_string,
            $this->_html
        );
    }

    public function clean_plugin_name()
    {
        // Remove plugin name converted in a link
        $pattern = '/(\&lt\;\?plugin\s)\<span.*\>\<span\>\<a href=.*\>(\w+)\<\/a\><\/span\><\/span>([^?]*\?\&gt\;)/Umsi';
        $replace_string = '\1 \2 \3';
        $this->_html = preg_replace(
            $pattern,
            $replace_string,
            $this->_html
        );
    }
}

// This is called to replace the RichTable plugin by an html table
// $matched contains html <p> tags so
// they are deleted before the conversion.
function replace_rich_table($matched)
{
    /**
      * @var WikiRequest $request
      */
    global $request;

    $plugin = $matched[1];

    $unknown_options = "/colspan|rowspan|width|height/";

    // if the plugin contains one of the options bellow
    // it won't be converted
    if (preg_match($unknown_options, $plugin)) {
        return $matched[0] . "\n";
    } else {
        //Replace unused <p...>
        $pattern = '/\<p.*\>/Umsi';
        $replace_string = "";

        $plugin = preg_replace(
            $pattern,
            $replace_string,
            $plugin
        );

        //replace unused </p> by \n
        $pattern = '/\<\/p\>/Umsi';
        $replace_string = "\n";

        $plugin = preg_replace(
            $pattern,
            $replace_string,
            $plugin
        );

        $plugin = "<?plugin RichTable " . $plugin . " ?>";

        require_once 'lib/BlockParser.php';
        $xmlcontent = TransformText($plugin, $request->getArg('pagename'));
        return $xmlcontent->AsXML();
    }
}
