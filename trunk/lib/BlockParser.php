<?php rcs_id('$Id: BlockParser.php,v 1.13 2002-01-29 05:06:30 dairiki Exp $');
/* Copyright (C) 2002, Geoffrey T. Dairiki <dairiki@dairiki.org>
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
require_once('lib/HtmlElement.php');
require_once('lib/InlineParser.php');

require_once('lib/transform.php');
/*
class InlineTransform
extends WikiTransform {
    function InlineTransform() {
        global $WikiNameRegexp, $AllowedProtocols, $InterWikiLinkRegexp;

        $this->WikiTransform();

        // register functions
        // functions are applied in order of registering

        $this->register(WT_SIMPLE_MARKUP, 'wtm_plugin_link');
 
        $this->register(WT_TOKENIZER, 'wtt_doublebrackets', '\[\[');
        //$this->register(WT_TOKENIZER, 'wtt_footnotes', '^\[\d+\]');
        //$this->register(WT_TOKENIZER, 'wtt_footnoterefs', '\[\d+\]');
        $this->register(WT_TOKENIZER, 'wtt_bracketlinks', '\[.+?\]');
        $this->register(WT_TOKENIZER, 'wtt_urls',
                        "!?\b($AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]");

        if (function_exists('wtt_interwikilinks')) {
            $this->register(WT_TOKENIZER, 'wtt_interwikilinks',
                            pcre_fix_posix_classes("!?(?<![[:alnum:]])") .
                            "$InterWikiLinkRegexp:[^\\s.,;?()]+");
        }
        $this->register(WT_TOKENIZER, 'wtt_bumpylinks', "!?$WikiNameRegexp");

        $this->register(WT_SIMPLE_MARKUP, 'wtm_htmlchars');
        $this->register(WT_SIMPLE_MARKUP, 'wtm_linebreak');
        $this->register(WT_SIMPLE_MARKUP, 'wtm_bold_italics');
    }
};

function TransformInline ($text) {
    // The old transform code does funny things with trailing
    // white space....

    $trfm = new InlineTransform;
    preg_match('/\s*$/', $text, $m);
    $tail = $m[0];
    // This "\n" -> "\r" hackage is to fool the old transform code
    // into continuing italics across lines.
    $in = str_replace("\n", "\r", $text);
    $out = preg_replace('/\s*$/', '', AsXML($trfm->do_transform('', array($in))));
    $out = str_replace("\r", "\n", $out);
    $out .= $tail;

    // DEBUGGING
    if (false && $out != $text) {
        echo(" IN <pre>'" . htmlspecialchars($text) . "'</pre><br>\n");
        echo("OUT <pre>'" . htmlspecialchars($out) . "'</pre><br>\n");
    }
    return new RawXml($out);
}
*/

////////////////////////////////////////////////////////////////
//
//
define("BLOCK_NEVER_TIGHTEN", 0);
define("BLOCK_NOTIGHTEN_AFTER", 1);
define("BLOCK_NOTIGHTEN_BEFORE", 2);
define("BLOCK_NOTIGHTEN_EITHER", 3);

/**
 * FIXME:
 *  Still to do:
 *    (old-style) tables
 */

class BlockParser {
    function parse (&$input, $tighten_mode = BLOCK_NEVER_TIGHTEN) {
        $content = HTML();
        
        for ($block = BlockParser::_nextBlock($input); $block; $block = $nextBlock) {
            while ($nextBlock = BlockParser::_nextBlock($input)) {
                // Attempt to merge current with following block.
                if (! $block->merge($nextBlock))
                    break;      // can't merge
            }

            $content->pushContent($block->finish($tighten_mode));
        }
        return $content;
    }

    function _nextBlock (&$input) {
        global $Block_BlockTypes;
        
        if ($input->atEof())
            return false;
        
        foreach ($Block_BlockTypes as $type) {
            if ($m = $input->match($type->_re)) {
                BlockParser::_debug('>', get_class($type), $input);
                
                $block = $type;
                $block->_followsBreak = $input->atBreak();
                if (!$block->_parse($input, $m)) {
                    BlockParser::_debug('[', "_parse failed", $input);
                    continue;
                }
                $block->_preceedsBreak = $input->eatSpace();
                BlockParser::_debug('<', get_class($type), $input);
                return $block;
            }
        }

        if ($input->getDepth() == 0) {
            // We should never get here.
            //preg_match('/.*/A', substr($this->_text, $this->_pos), $m);// get first line
            trigger_error("Couldn't match block: '".rawurlencode($m[0])."'", E_USER_NOTICE);
        }
        //FIXME:$this->_debug("no match");
        return false;
    }

    function _debug ($tab, $msg, $input) {
        return ;
        
        $tab = str_repeat($tab, $input->getDepth() + 1);
        printXML(HTML::div("$tab $msg: at: '",
                           HTML::tt($input->where()),
                           "'"));
    }
    
}

class BlockParser_Match {
    function BlockParser_Match ($match_data) {
        $this->_m = $match_data;
    }

    function getPrefix () {
        return $this->_m[1];
    }

    function getMatch ($n = 0) {
        $text = $this->_m[$n + 2];
        //if (preg_match('/\n./s', $text)) {
            $prefix = $this->getPrefix();
            $text = str_replace("\n$prefix", "\n", $text);
        //}
        return $text;
    }
}

    
class BlockParser_Input {

    function BlockParser_Input ($text) {
        $this->_text = $text;
        $this->_pos = 0;
        $this->_depth = 0;
        
        // Expand leading tabs.
        // FIXME: do this better.
        //
        // We want to ensure the only characters matching \s are ' ' and "\n".
        //
        $this->_text = preg_replace('/(?![ \n])\s/', ' ', $this->_text);
        assert(!preg_match('/(?![ \n])\s/', $this->_text));
        if (!preg_match('/\n$/', $this->_text))
            $this->_text .= "\n";

        $this->_set_prefix ('');
        $this->_atBreak = false;
        $this->eatSpace();
    }

    function _set_prefix ($prefix, $next_prefix = false) {
        if ($next_prefix === false)
            $next_prefix = $prefix;

        $this->_prefix = $prefix;
        $this->_next_prefix = $next_prefix;

        $this->_regexp_cache = array();

        $blank = "(:?$prefix)?\s*\n";
        $this->_blank_pat = "/$blank/A";
        $this->_eof_pat = "/\\Z|(?!$blank|${prefix}.)/A";
    }

    function atEof () {
        return preg_match($this->_eof_pat, substr($this->_text, $this->_pos));
    }

    function match ($regexp) {
        $cache = &$this->_regexp_cache;
        if (!isset($cache[$regexp])) {
            // Fix up any '^'s in pattern (add our prefix)
            $re = preg_replace('/(?<! [ [ \\\\ ]) \^ /x',
                               '^' . $this->_next_prefix, $regexp);

            // Fix any match  backreferences (like '\1').
            $re = preg_replace('/(?<= [^ \\\\ ] [ \\\\ ] )( \\d+ )/ex', "'\\1' + 2", $re);

            $re = "/(" . $this->_prefix . ")($re)/Am";
            $cache[$regexp] = $re;
        }
        else
            $re = $cache[$regexp];
        
        if (preg_match($re, substr($this->_text, $this->_pos), $m)) {
            return new BlockParser_Match($m);
        }
        return false;
    }

    function accept ($match) {
        $text = $match->_m[0];

        assert(substr($this->_text, $this->_pos, strlen($text)) == $text);
        $this->_pos += strlen($text);

        // FIXME:
        assert(preg_match("/\n$/", $text));
            
        if ($this->_next_prefix != $this->_prefix)
            $this->_set_prefix($this->_next_prefix);

        $this->_atBreak = false;
        $this->eatSpace();
    }
    
    /**
     * Consume blank lines.
     *
     * @return bool True if any blank lines where comsumed.
     */
    function eatSpace () {
        if (preg_match($this->_blank_pat, substr($this->_text, $this->_pos), $m)) {
            $this->_pos += strlen($m[0]);
            if ($this->_next_prefix != $this->_prefix)
                $this->_set_prefix($this->_next_prefix);
            $this->_atBreak = true;

            while (preg_match($this->_blank_pat, substr($this->_text, $this->_pos), $m)) {
                $this->_pos += strlen($m[0]);
            }
        }

        return $this->_atBreak;
    }
    
    function atBreak () {
        return $this->_atBreak;
    }

    function getDepth () {
        return $this->_depth;
    }

    // DEBUGGING
    function where () {
        if (($m = $this->match('.*\n')))
            return sprintf('[%s]%s', $m->getPrefix(), $m->getMatch());
        return '???';
    }
    
    function subBlock ($initial_prefix, $subsequent_prefix = false) {
        if ($subsequent_prefix === false)
            $subsequent_prefix = $initial_prefix;
        
        return new BlockParser_InputSubBlock ($this, $initial_prefix, $subsequent_prefix);
    }
}

class BlockParser_InputSubBlock extends BlockParser_Input
{
    function BlockParser_InputSubBlock (&$block, $initial_prefix, $subsequent_prefix) {
        $this->_text = &$block->_text;
        $this->_pos = &$block->_pos;
        $this->_atBreak = &$block->_atBreak;

        $this->_depth = $block->_depth + 1;

        $this->_set_prefix($block->_prefix . $initial_prefix,
                           $block->_next_prefix . $subsequent_prefix);
    }
}

    

class Block {
    var $_tag;
    var $_attr = false;
    var $_re;
    var $_followsBreak = false;
    var $_preceedsBreak = false;
    var $_content = array();

        
    function _parse (&$input, $match) {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    function _pushContent ($c) {
        if (!is_array($c))
            $c = func_get_args();
        foreach ($c as $x)
            $this->_content[] = $x;
    }

    function isTerminal () {
        return true;
    }
    
    function merge ($followingBlock) {
        return false;
    }

    function finish (/*$tighten*/) {
        return new HtmlElement($this->_tag, $this->_attr, $this->_content);
    }
}


class CompoundBlock extends Block
{
    function isTerminal () {
        return false;
    }
}


class Block_blockquote extends CompoundBlock
{
    var $_tag ='blockquote';
    var $_depth;
    var $_re = '\ +(?=\S)';
    
    function _parse (&$input, $m) {
        $indent = $m->getMatch();
        $this->_depth = strlen($indent);
        $this->_content[] = BlockParser::parse($input->subBlock($indent),
                                               BLOCK_NOTIGHTEN_EITHER);
        return true;
    }

    function merge ($nextBlock) {
        if (get_class($nextBlock) != 'block_blockquote')
            return false;
        assert ($nextBlock->_depth < $this->_depth);
        
        $content = $nextBlock->_content;
        array_unshift($content, $this->finish());
        $this->_content = $content;
        return true;
    }
}

class Block_list extends CompoundBlock
{
    //var $_tag = 'ol' or 'ul';
    var $_re = '\ {0,4}([*+#]|-(?!-)|o(?=\ ))\ *(?=\S)';

    function _parse (&$input, $m) {
        // A list as the first content in a list is not allowed.
        // E.g.:
        //   *  * Item
        // Should markup as <ul><li>* Item</li></ul>,
        // not <ul><li><ul><li>Item</li></ul>/li></ul>.
        //
        if (preg_match('/[-*o+#;]\s*$/', $m->getPrefix()))
            return false;
        
        $prefix = $m->getMatch();
        $leader = preg_quote($prefix, '/');
        $indent = sprintf("\\ {%d}", strlen($prefix));

        $bullet = $m->getMatch(1);
        $this->_tag = $bullet == '#' ? 'ol' : 'ul';
        
        $text = $input->subBlock($leader, $indent);
        $content = BlockParser::parse($text, BLOCK_NOTIGHTEN_AFTER);
        $this->_pushContent(HTML::li(false, $content));
        return true;
    }
    
    function merge ($nextBlock) {
        if (!isa($nextBlock, 'Block_list') || $this->_tag != $nextBlock->_tag)
            return false;

        $this->_pushContent($nextBlock->_content);
        return true;
    }
}

class Block_dl extends Block_list
{
    var $_tag = 'dl';
    var $_re = '(\ {0,4})([^\s!].*):\s*?\n(?=(?:\s*^)+(\1\ +)\S)';
    //          1-------12--------2                   3-----3

    function _parse (&$input, $m) {
        $term = TransformInline(rtrim($m->getMatch(2)));
        $indent = $m->getMatch(3);

        $input->accept($m);
        
        $this->_pushContent(HTML::dt(false, $term),
                            HTML::dd(false,
                                     BlockParser::parse($input->subBlock($indent),
                                                        BLOCK_NOTIGHTEN_AFTER)));
        return true;
    }
}



class Block_table_dl_defn extends XmlContent
{
    var $nrows;
    var $ncols;
    
    function Block_table_dl_defn ($term, $defn) {
        $this->XmlContent();
        if (!is_array($defn))
            $defn = $defn->getContent();

        $this->_ncols = $this->_ComputeNcols($defn);
        
        $this->_nrows = 0;
        foreach ($defn as $item) {
            if ($this->_IsASubtable($item))
                $this->_addSubtable($item);
            else
                $this->_addToRow($item);
        }
        $this->_flushRow();

        $th = HTML::th($term);
        if ($this->_nrows > 1)
            $th->setAttr('rowspan', $this->_nrows);
        $this->_setTerm($th);
    }

    function _addToRow ($item) {
        if (empty($this->_accum)) {
            $this->_accum = HTML::td();
            if ($this->_ncols > 2)
                $this->_accum->setAttr('colspan', $this->_ncols - 1);
        }
        $this->_accum->pushContent($item);
    }

    function _flushRow () {
        if (!empty($this->_accum)) {
            $this->pushContent(HTML::tr($this->_accum));
            $this->_accum = false;
            $this->_nrows++;
        }
    }

    function _addSubtable ($table) {
        $this->_flushRow();
        foreach ($table->getContent() as $subdef) {
            $this->pushContent($subdef);
            $this->_nrows += $subdef->nrows();
        }
    }

    function _setTerm ($th) {
        $first_row = &$this->_content[0];
        if (isa($first_row, 'Block_table_dl_defn'))
            $first_row->_setTerm($th);
        else
            $first_row->unshiftContent($th);
    }
    
    function _ComputeNcols ($defn) {
        $ncols = 2;
        foreach ($defn as $item) {
            if ($this->_IsASubtable($item)) {
                $row = $this->_FirstDefn($item);
                $ncols = max($ncols, $row->ncols() + 1);
            }
        }
        return $ncols;
    }

    function _IsASubtable ($item) {
        return isa($item, 'HtmlElement')
            && $item->getTag() == 'table'
            && $item->getAttr('class') == 'wiki-dl-table';
    }

    function _FirstDefn ($subtable) {
        $defs = $subtable->getContent();
        return $defs[0];
    }

    function ncols () {
        return $this->_ncols;
    }

    function nrows () {
        return $this->_nrows;
    }

    function setWidth ($ncols) {
        assert($ncols >= $this->_ncols);
        if ($ncols <= $this->_ncols)
            return;
        $rows = &$this->_content;
        for ($i = 0; $i < count($rows); $i++) {
            $row = &$rows[$i];
            if (isa($row, 'Block_table_dl_defn'))
                $row->setWidth($ncols - 1);
            else {
                $n = count($row->_content);
                $lastcol = &$row->_content[$n - 1];
                $lastcol->setAttr('colspan', $ncols - 1);
            }
        }
    }
}

class Block_table_dl extends Block_list
{
    var $_tag = 'table';
    var $_attr = array('class' => 'wiki-dl-table',
                       'border' => 2, // FIXME: CSS?
                       'cellspacing' => 0,
                       'cellpadding' => 6);
    

    var $_re = '(\ {0,4})((?![\s!]).*)?[|]\s*?\n(?=(?:\s*^)+(\1\ +)\S)';
    //          1-------12-----------2                      3-----3

    function _parse (&$input, $m) {
        $term = TransformInline(rtrim($m->getMatch(2)));
        $indent = $m->getMatch(3);

        $input->accept($m);
        $defn = BlockParser::parse($input->subBlock($indent),
                                   BLOCK_NOTIGHTEN_AFTER);

        $this->_pushContent(new Block_table_dl_defn($term, $defn));
        return true;
    }
            
    function finish () {
        $defs = &$this->_content;

        $ncols = 0;
        foreach ($defs as $defn)
            $ncols = max($ncols, $defn->ncols());
        foreach ($defs as $key => $defn)
            $defs[$key]->setWidth($ncols);

        return parent::finish();
    }
}

class Block_oldlists extends Block_list
{
    //var $_tag = 'ol', 'ul', or 'dl';
    var $_re = '(?:([*#])|;(.*):).*?(?=\S)';
    //             1----1  2--2
    
    function _parse (&$input, $m) {
        if (!preg_match('/[*#;]*$/A', $m->getPrefix()))
            return false;

        $prefix = $m->getMatch();

        $leader = preg_quote($prefix, '/');

        $oldindent = '[*#;](?=[#*]|;.*:.*?\S)';
        $newindent = sprintf('\\ {%d}', strlen($prefix));
        $indent = "(?:$oldindent|$newindent)";

        $bullet = $m->getMatch(1);
        if ($bullet) {
            $this->_tag = $bullet == '*' ? 'ul' : 'ol';
            $item = HTML::li();
        }
        else {
            $this->_tag = 'dl';
            $term = trim($m->getMatch(2));
            if ($term)
                $this->_pushContent(HTML::dt(false, TransformInline($term)));
            $item = HTML::dd();
        }
        
        $item->pushContent(BlockParser::parse($input->subBlock($leader, $indent),
                                              BLOCK_NOTIGHTEN_AFTER));
        $this->_pushContent($item);
        return true;
    }
}

class Block_pre extends Block
{
    var $_tag = 'pre';
    var $_re = '<(pre|verbatim)>(.*?(?:\s*\n^.*?)*?)(?<!~)<\/\1>\s*?\n';
    //           1------------1 2------------------2

    function _parse (&$input, $m) {
        $input->accept($m);

        $text = $m->getMatch(2);
        $tag = $m->getMatch(1);

        if ($tag == 'pre')
            $text = TransformInline($text);

        $this->_pushContent($text);
        return true;
    }
}

class Block_plugin extends Block
{
    var $_tag = 'div';
    var $_attr = array('class' => 'plugin');
    var $_re = '<\?plugin(?:-form)?.*?(?:\n^.*?)*?(?<!~)\?>\s*?\n';

    function _parse (&$input, $m) {
        global $request;
        $loader = new WikiPluginLoader;
        $input->accept($m);
        $this->_pushContent($loader->expandPI($m->getMatch(), $request));
        return true;
    }
}

class Block_hr extends Block
{
    var $_tag = 'hr';
    var $_re = '-{4,}\s*?\n';

    function _parse (&$input, $m) {
        $input->accept($m);
        return true;
    }
}

class Block_heading extends Block
{
    var $_re = '(!{1,3})(.*)\n';
    
    function _parse (&$input, $m) {
        $input->accept($m);
        $this->_tag = "h" . (5 - strlen($m->getMatch(1)));
        $this->_pushContent(TransformInline(trim($m->getMatch(2))));
        return true;
    }
}

class Block_p extends Block
{
    var $_tag = 'p';
    var $_re = '\S.*\n';

    function _parse (&$input, $m) {
        $this->_text = $m->getMatch();
        $input->accept($m);
        return true;
    }
    
    function merge ($nextBlock) {
        if ($this->_preceedsBreak || get_class($nextBlock) != 'block_p')
            return false;

        $this->_text .= $nextBlock->_text;
        $this->_preceedsBreak = $nextBlock->_preceedsBreak;
        return true;
    }
            
    function finish ($tighten) {
        $this->_pushContent(TransformInline(trim($this->_text)));
        
        if ($this->_followsBreak && ($tighten & BLOCK_NOTIGHTEN_AFTER) != 0)
            $tighten = 0;
        elseif ($this->_preceedsBreak && ($tighten & BLOCK_NOTIGHTEN_BEFORE) != 0)
            $tighten = 0;

        return $tighten ? $this->_content : parent::finish();
    }
}

class Block_email_blockquote extends CompoundBlock
{
    // FIXME: move CSS to CSS.
    var $_tag ='blockquote';
    var $_attr = array('style' => 'border-left-width: medium; border-left-color: #0f0; border-left-style: ridge; padding-left: 1em; margin-left: 0em; margin-right: 0em;');
    var $_depth;
    var $_re = '>\ ?';
    
    function _parse (&$input, $m) {
        $prefix = $m->getMatch();
        $indent = "(?:$prefix|>(?=\s*?\n))";
        $this->_content[] = BlockParser::parse($input->subBlock($indent),
                                               BLOCK_NOTIGHTEN_EITHER);
        return true;
    }
}

////////////////////////////////////////////////////////////////
//



$GLOBALS['Block_BlockTypes'] = array(new Block_oldlists,
                                     new Block_list,
                                     new Block_dl,
                                     new Block_table_dl,
                                     new Block_blockquote,
                                     new Block_heading,
                                     new Block_hr,
                                     new Block_pre,
                                     new Block_email_blockquote,
                                     new Block_plugin,
                                     new Block_p);

// FIXME: This is temporary, too...
function NewTransform ($text) {

    set_time_limit(2);
    
    // Expand leading tabs.
    // FIXME: do this better. also move  it...
    $text = preg_replace('/^\ *[^\ \S\n][^\S\n]*/me', "str_repeat(' ', strlen('\\0'))", $text);
    assert(!preg_match('/^\ *\t/', $text));

    $input = new BlockParser_Input($text);
    return BlockParser::parse($input);
}


// FIXME: bad name
function TransformRevision ($revision) {
    if ($revision->get('markup') == 'new') {
        return NewTransform($revision->getPackedContent());
    }
    else {
        return do_transform($revision->getContent());
    }
}


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
