<?php // -*-php-*-
rcs_id('$Id: OldStyleTable.php,v 1.7 2003-02-21 23:00:35 dairiki Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * OldStyleTable: Layout tables using the old table style.
 *
 * Usage:
 * <pre>
 *  <?plugin OldStyleTable
 *  ||  __Name__               |v __Cost__   |v __Notes__
 *  | __First__   | __Last__
 *  |> Jeff       |< Dairiki   |^  Cheap     |< Not worth it
 *  |> Marco      |< Polo      | Cheaper     |< Not available
 *  ?>
 * </pre>
 *
 * Note that multiple <code>|</code>'s lead to spanned columns,
 * and <code>v</code>'s can be used to span rows.  A <code>&gt;</code>
 * generates a right justified column, <code>&lt;</code> a left
 * justified column and <code>^</code> a centered column
 * (which is the default.)
 *
 * @author Geoffrey T. Dairiki
 */

class WikiPlugin_OldStyleTable
extends WikiPlugin
{
    function getName() {
        return _("OldStyleTable");
    }

    function getDescription() {
      return _("Layout tables using the old markup style.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.7 $");
    }

    function getDefaultArguments() {
        return array();
    }

    function run($dbi, $argstr, $request, $basepage) {
        global $Theme;
        include_once('lib/InlineParser.php');

        $lines = preg_split('/\s*?\n\s*/', $argstr);
        $table = HTML::table(array('cellpadding' => 1,
                                   'cellspacing' => 1,
                                   'border' => 1));

        foreach ($lines as $line) {
            if (!$line)
                continue;
            if ($line[0] != '|')
                return $this->error(fmt("Line does not begin with a '|'."));
            $table->pushContent($this->_parse_row($line, $basepage));
        }

        return $table;
    }

    function _parse_row ($line, $basepage) {
        $brkt_link = "\\[ .*? [^]\s] .*? \\]";
        $cell_content  = "(?: [^[] | ".ESCAPE_CHAR."\\[ | $brkt_link )*?";
        
        preg_match_all("/(\\|+) (v*) ([<>^]?) \s* ($cell_content) \s* (?=\\||\$)/x",
                       $line, $matches, PREG_SET_ORDER);

        $row = HTML::tr();

        foreach ($matches as $m) {
            $attr = array();

            if (strlen($m[1]) > 1)
                $attr['colspan'] = strlen($m[1]);
            if (strlen($m[2]) > 0)
                $attr['rowspan'] = strlen($m[2]) + 1;

            if ($m[3] == '^')
                $attr['align'] = 'center';
            else if ($m[3] == '>')
                $attr['align'] = 'right';
            else
                $attr['align'] = 'left';

            // Assume new-style inline markup.
            $content = TransformInline($m[4], 2.0, $basepage);

            $row->pushContent(HTML::td($attr, HTML::raw('&nbsp;'),
                                       $content, HTML::raw('&nbsp;')));
        }
        return $row;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.6  2003/02/21 04:12:06  dairiki
// Minor fixes for new cached markup.
//
// Revision 1.5  2003/01/18 21:48:59  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
//

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>