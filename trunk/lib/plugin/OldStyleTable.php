<?php // -*-php-*-
rcs_id('$Id: OldStyleTable.php,v 1.4 2002-10-31 03:28:31 carstenklapp Exp $');
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

    function getDefaultArguments() {
        return array();
    }

    function run($dbi, $argstr, $request) {
    	global $Theme;

        $lines = preg_split('/\s*?\n\s*/', $argstr);
        $table = HTML::table(array('cellpadding' => 1,
                                   'cellspacing' => 1,
                                   'border' => 1));
        
        foreach ($lines as $line) {
            if (!$line)
                continue;
            if ($line[0] != '|')
                return $this->error(fmt("Line does not begin with a '|'."));
            $table->pushContent($this->_parse_row($line));
        }
        
        return $table;
    }

    function _parse_row ($line) {
        
        preg_match_all('/(\|+)(v*)([<>^]?)\s*(.*?)\s*(?=\||$)/',
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
            $content = TransformInline($m[4]);
            
            $row->pushContent(HTML::td($attr,
                                       HTML::raw('&nbsp;'), $content, HTML::raw('&nbsp;')));
        }
        return $row;
    }
};


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
