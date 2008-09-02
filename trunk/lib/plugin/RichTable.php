<?php // -*-php-*-
rcs_id('$Id$');
/**
  RichTablePlugin
  A PhpWiki plugin that allows insertion of tables using a richer syntax.
  Src: http://www.it.iitb.ac.in/~sameerds/phpwiki/index.php/RichTablePlugin
  Docs: http://phpwiki.org/RichTablePlugin
*/
/* 
 * Copyright (C) 2003 Sameer D. Sahasrabuddhe
 * Copyright (C) 2005 $ThePhpWikiProgrammingTeam
 * Copyright (C) 2008 Marc-Etienne Vargenau, Alcatel-Lucent
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
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// error_reporting (E_ALL & ~E_NOTICE);

class WikiPlugin_RichTable
extends WikiPlugin
{
    function getName() {
        return _("RichTable");
    }

    function getDescription() {
      return _("Layout tables using a very rich markup style.");
    }

    function getDefaultArguments() {
        return array();
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision$");
    }

    function run($dbi, $argstr, &$request, $basepage) {
    	global $WikiTheme;
        include_once("lib/BlockParser.php");
        // RichTablePlugin markup is new.
        $markup = 2.0; 

        $lines = preg_split('/\n/', $argstr);
        $table = HTML::table();
 
        if ($lines[0][0] == '*') {
            $line = substr(array_shift($lines),1);
            $attrs = $this->_parse_attr($line);
            foreach ($attrs as $key => $value) {
                if (in_array ($key, array("id", "class", "title", "style",
                                          "bgcolor", "frame", "rules", "border",
                                          "cellspacing", "cellpadding",
                                          "summary", "align", "width"))) {
                    $table->setAttr($key, $value);
                }
            }
        }
        
        foreach ($lines as $line){
            if (substr($line,0,1) == "-") {
                if (isset($row)) {
                    if (isset($cell)) {
                        if (isset($content)) {
                            $cell->pushContent(TransformText($content, $markup, $basepage));
                            unset($content);
                        }
                        $row->pushContent($cell);
                        unset($cell);
                    }
                    $table->pushContent($row);
                }	
                $row = HTML::tr();
                $attrs = $this->_parse_attr(substr($line,1));
                foreach ($attrs as $key => $value) {
                    if (in_array ($key, array("id", "class", "title", "style",
                                              "bgcolor", "align", "valign"))) {
                        $row->setAttr($key, $value);
                    }
                }
                continue;
            }
            if (substr($line,0,1) == "|" and isset($row)) {
                if (isset($cell)) {
                    if (isset ($content)) {
                        $cell->pushContent(TransformText($content, $markup, $basepage));
                        unset($content);
                    }
                    $row->pushContent($cell);
                }
                $cell = HTML::td();
                $line = substr($line, 1);
                if ($line[0] == "*" ) {
                    $attrs = $this->_parse_attr(substr($line,1));
                    foreach ($attrs as $key => $value) {
                        if (in_array ($key, array("id", "class", "title", "style",
                                                  "colspan", "rowspan", "width", "height",
                                                  "bgcolor", "align", "valign"))) {
                            $cell->setAttr($key, $value);
                        }
                    }
                    continue;
                } 
            } 
            if (isset($row) and isset($cell)) {
                $line = str_replace("?\>", "?>", $line);
                $line = str_replace("\~", "~", $line);
                if (empty($content)) $content = '';
                $content .= $line . "\n";
            }
        }
        if (isset($row)) {
            if (isset($cell)) {
                if (isset($content))
                    $cell->pushContent(TransformText($content, $markup, $basepage));
                $row->pushContent($cell);
            }
            $table->pushContent($row);
        }
        return $table;
    }

    function _parse_attr($line) {
        // We allow attributes with or without quotes (")
        // border=1, cellpadding="5"
        // style="font-family: sans-serif; border-top:1px solid #dddddd;
        // What will not work is style with comma inside, e. g.
        // style="font-family: Verdana, Arial, Helvetica, sans-serif"
        $attr_chunks = preg_split("/\s*,\s*/", strtolower($line));
        $options = array();
        foreach ($attr_chunks as $attr_pair) {
            if (empty($attr_pair)) continue;
            $key_val = preg_split("/\s*=\s*/", $attr_pair);
            if (!empty($key_val[1]))
                $options[trim($key_val[0])] = trim(str_replace("\"", "", $key_val[1]));
        }
        return $options;
    }
}

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
