<?php rcs_id('$Id: BlockParser.php,v 1.19 2002-02-01 06:13:48 dairiki Exp $');
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

////////////////////////////////////////////////////////////////
//
//

/**
 * FIXME:
 *  Still to do:
 *    (old-style) tables
 */

// FIXME: unify this with the RegexpSet in InlinePArser.

/**
 * Return type from RegexpSet::match and RegexpSet::nextMatch.
 *
 * @see RegexpSet
 */
class AnchoredRegexpSet_match {
    /**
     * The matched text.
     */
    var $match;

    /**
     * The text following the matched text.
     */
    var $postmatch;

    /**
     * Index of the regular expression which matched.
     */
    var $regexp_ind;
}

/**
 * A set of regular expressions.
 *
 * This class is probably only useful for InlineTransformer.
 */
class AnchoredRegexpSet
{
    /** Constructor
     *
     * @param $regexps array A list of regular expressions.  The
     * regular expressions should not include any sub-pattern groups
     * "(...)".  (Anonymous groups, like "(?:...)", as well as
     * look-ahead and look-behind assertions are fine.)
     */
    function AnchoredRegexpSet ($regexps) {
        $this->_regexps = $regexps;
        $this->_re = "/((" . join(")|(", $regexps) . "))/Ax";
    }

    /**
     * Search text for the next matching regexp from the Regexp Set.
     *
     * @param $text string The text to search.
     *
     * @return object  A RegexpSet_match object, or false if no match.
     */
    function match ($text) {
        if (! preg_match($this->_re, $text, $m)) {
            return false;
        }
        
        $match = new AnchoredRegexpSet_match;
        $match->postmatch = substr($text, strlen($m[0]));
        $match->match = $m[1];
        $match->regexp_ind = count($m) - 3;
        return $match;
    }

    /**
     * Search for next matching regexp.
     *
     * Here, 'next' has two meanings:
     *
     * Match the next regexp(s) in the set, at the same position as the last match.
     *
     * If that fails, match the whole RegexpSet, starting after the position of the
     * previous match.
     *
     * @param $text string Text to search.
     *
     * @param $prevMatch A RegexpSet_match object
     *
     * $prevMatch should be a match object obtained by a previous
     * match upon the same value of $text.
     *
     * @return object  A RegexpSet_match object, or false if no match.
     */
    function nextMatch ($text, $prevMatch) {
        // Try to find match at same position.
        $regexps = array_slice($this->_regexps, $prevMatch->regexp_ind + 1);
        if (!$regexps) {
            return false;
        }

        $pat= "/ ( (" . join(')|(', $regexps) . ") ) /Axs";

        if (! preg_match($pat, $text, $m)) {
            return false;
        }
        
        $match = new AnchoredRegexpSet_match;
        $match->postmatch = substr($text, strlen($m[0]));
        $match->match = $m[1];
        $match->regexp_ind = count($m) - 3 + $prevMatch->regexp_ind + 1;;
        return $match;
    }
}


    
class BlockParser_Input {

    function BlockParser_Input ($text) {
        
        // Expand leading tabs.
        // FIXME: do this better.
        //
        // We want to ensure the only characters matching \s are ' ' and "\n".
        //
        $text = preg_replace('/(?![ \n])\s/', ' ', $text);
        assert(!preg_match('/(?![ \n])\s/', $text));

        $this->_lines = preg_split('/[^\S\n]*\n/', $text);
        $this->_pos = 0;

        $this->_initBlockTypes();
    }

    function currentLine () {
        if ($this->_pos >= count($this->_lines))
            return false;
        return $this->_lines[$this->_pos];
    }
        
    function nextLine () {
        $this->_pos++;
        if ($this->_pos >= count($this->_lines))
            return false;
        return $this->_lines[$this->_pos];
    }

    function advance () {
        $this->_pos++;
    }
    
    function getPos () {
        return $this->_pos;
    }

    function setPos ($pos) {
        $this->_pos = $pos;
    }

    function getPrefix () {
        return '';
    }

    function getDepth () {
        return 0;
    }

    function where () {
        if ($this->_pos < count($this->_lines))
            return $this->_lines[$this->_pos];
        else
            return "<EOF>";
    }

    // FIXME: hackish
    function _initBlockTypes () {
        foreach (array('oldlists', 'list', 'dl', 'table_dl',
                       'blockquote', 'heading', 'hr', 'pre', 'email_blockquote',
                       'plugin', 'p')
                 as $type) {
            $class = "Block_$type";
            $proto = new $class;
            $this->_block_types[] = $proto;
            $this->_regexps[] = $proto->_re;
        }
        $this->_regexpset = new AnchoredRegexpSet($this->_regexps);
    }
    
    function getBlock() {
        $atSpace = false;
        
        $line = $this->currentLine();
        if ($line === '') {
            $atSpace = $this->getPos();
            while ( ($line = $this->nextLine()) === '' )
                ;
        }
        if ($line === false) {
            if ($atSpace)
                $this->setPos($atSpace);
            return false;
        }

        $re_set = &$this->_regexpset;
        for ($m = $re_set->match($line); $m; $m = $re_set->nextMatch($line, $m)) {
            $block = $this->_block_types[$m->regexp_ind];
            //$this->_debug('>', get_class($block));
            
            if ($block->_match($this, $m)) {
                //$this->_debug('<', get_class($block));
                $block->_follows_space = (bool) $atSpace;
                return $block;
            }
            //$this->_debug('[', "_match failed");
        }


        //if ($input->getDepth() == 0) {
        // We should never get here.
        //preg_match('/.*/A', substr($this->_text, $this->_pos), $m);// get first line
        trigger_error("Couldn't match block: '$line'", E_USER_NOTICE);
        //}
        
        return false;
    }

    
    function _debug ($tab, $msg) {
        //return ;
        $where = $this->where();
        $tab = str_repeat('____', $this->getDepth() ) . $tab;
        printXML(HTML::div("$tab $msg: at: '",
                           HTML::tt($where),
                           "'"));
    }
}

class BlockParser_InputSubBlock extends BlockParser_Input
{
    function BlockParser_InputSubBlock (&$input, $prefix_re, $initial_prefix = false) {
        $this->_input = &$input;
        $this->_prefix_pat = "/$prefix_re|\\s*\$/Ax";
        $this->_prefix = $initial_prefix;

        // FIXME: hackish
        $this->_block_types = &$input->_block_types;
        $this->_regexpset = &$input->_regexpset;
    }

    function currentLine () {
        if (($line = $this->_input->currentLine()) === false)
            return false;
        if ($this->_prefix === false) {
            if (!preg_match($this->_prefix_pat, $line, $m))
                return false;
            $this->_prefix = $m[0];
        }
        return (string) substr($line, strlen($this->_prefix));
    }
        
    function nextLine () {
        if (($line = $this->_input->nextLine()) === false) {
            $this->_prefix = false;
            return false;
        }
        if (!preg_match($this->_prefix_pat, $line, $m)) {
            $this->_prefix = false;
            return false;
        }
        $this->_prefix = $m[0];
        return (string) substr($line, strlen($this->_prefix));
    }

    function advance () {
        $this->_prefix = false;
        $this->_input->advance();
    }
    
    function getPos () {
        return array($this->_prefix, $this->_input->getPos());
    }

    function setPos ($pos) {
        $this->_prefix = $pos[0];
        $this->_input->setPos($pos[1]);
    }
    
    function getPrefix () {
        assert ($this->_prefix !== false);
        return $this->_input->getPrefix() . $this->_prefix;
    }

    function getDepth () {
        return $this->_input->getDepth() + 1;
    }

    function where () {
        return $this->_input->where();
    }
}
    

class BlockMarkup extends XmlContent {
    var $_tag;
    var $_attr = false;
    var $_re;

        
    function _match (&$input, $match) {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    function merge ($followingBlock) {
        return false;
    }

    function finish (/*$tighten*/) {
        return new HtmlElement($this->_tag, $this->_attr, $this->getContent());
    }
}

class Block extends BlockMarkup {
    function Block ($text = false) {
        $this->XmlContent();
        if ($text)
            $this->_parse(new BlockParser_Input($text));
    }
    
    function _parse (&$input) {
        for ($block = $input->getBlock(); $block; $block = $nextBlock) {
            while ($nextBlock = $input->getBlock()) {
                // Attempt to merge current with following block.
                if (! ($merged = $block->merge($nextBlock)) ) {
                    break;      // can't merge
                }
                $block = $merged;
            }
            $this->pushContent($block->finish());
        }
    }
}

class SubBlock extends Block {
    function SubBlock (&$input, $indent_re, $initial_indent = false) {
        $this->Block();
        $this->_parse(new BlockParser_InputSubBlock($input,
                                                    $indent_re,
                                                    $initial_indent));
    }
}

    
class Block_blockquote extends Block
{
    var $_tag ='blockquote';
    var $_depth;

    var $_re = '\ +(?=\S)';
    
    function _match (&$input, $m) {
        $this->_depth = strlen($m->match);
        $indent = sprintf("\\ {%d}", $this->_depth);
        $this->pushContent(new SubBlock($input, $indent, $m->match));
        return true;
    }

    function merge ($nextBlock) {
        if (get_class($nextBlock) == 'block_blockquote') {
            assert ($nextBlock->_depth < $this->_depth);
            $nextBlock->unshiftContent($this->finish());
            return $nextBlock;
        }
        return false;
    }
}

class Block_list extends Block
{
    //var $_tag = 'ol' or 'ul';
    var $_re = '\ {0,4}
                (?: [+#] | -(?!-) | [o](?=\ )
                  | [*] (?! \S[^*]*(?<=\S)[*](?!\S) )
                )\ *(?=\S)';

    function _match (&$input, $m) {
        // A list as the first content in a list is not allowed.
        // E.g.:
        //   *  * Item
        // Should markup as <ul><li>* Item</li></ul>,
        // not <ul><li><ul><li>Item</li></ul>/li></ul>.
        //
        if (preg_match('/[*#+-o]/', $input->getPrefix())) {
            return false;
        }
        
        $prefix = $m->match;
        $indent = sprintf("\\ {%d}", strlen($prefix));

        $bullet = trim($m->match);
        $this->_tag = $bullet == '#' ? 'ol' : 'ul';
        
        $this->pushContent(HTML::li(new SubBlock($input, $indent, $m->match)));
        return true;
    }
    
    function merge ($nextBlock) {
        if (isa($nextBlock, 'Block_list') && $this->_tag == $nextBlock->_tag) {
            $this->pushContent($nextBlock->getContent());
            return $this;
        }
        return false;
    }
}

class Block_dl extends Block_list
{
    var $_tag = 'dl';
    var $_re = '\ {0,4}\S.*:\s*$';

    function _match (&$input, $m) {
        if (!($p = $this->_do_match($input, $m)))
            return false;
        list ($term, $defn) = $p;
        
        $this->pushContent(HTML::dt($term), HTML::dd($defn));
        return true;
    }

    function _do_match (&$input, $m) {
        $pos = $input->getPos();

        while ( ($line = $input->nextLine()) !== false ) {
            if (preg_match('/\ *(?=\S)/A', $line, $mm)) {
                $indent = strlen($mm[0]);
                break;
            }
        }
        $input->setPos($pos);
        
        if (!isset($indent))
            return false;       // No body found.

        $input->advance();      // Skip the first line.

        $term = TransformInline(rtrim(substr(trim($m->match),0,-1)));
        $defn = new SubBlock($input, sprintf("\\ {%d}", $indent));
        return array($term, $defn);
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

class Block_table_dl extends Block_dl
{
    var $_tag = 'table';
    var $_attr = array('class' => 'wiki-dl-table',
                       'border' => 2, // FIXME: CSS?
                       'cellspacing' => 0,
                       'cellpadding' => 6);
    

    var $_re = '\ {0,4} (?:\S.*)? \| \s* $';

    function _match (&$input, $m) {
        if (!($p = $this->_do_match($input, $m)))
            return false;
        list ($term, $defn) = $p;

        $this->pushContent(new Block_table_dl_defn($term, $defn));
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
    var $_re = '(?: [*] (?! \S[^*]* (?<=\S) [*](?!\S) )
                  | [#]
                  | ; .* :
                ) .*? (?=\S)';

    
    function _match (&$input, $m) {
        // FIXME:
        if (!preg_match('/[*#;]*$/A', $input->getPrefix())) {
            return false;
        }
        

        $prefix = $m->match;
        $oldindent = '[*#;](?=[#*]|;.*:.*\S)';
        $newindent = sprintf('\\ {%d}', strlen($prefix));
        $indent = "(?:$oldindent|$newindent)";

        $bullet = $prefix[0];
        if ($bullet == '*') {
            $this->_tag = 'ul';
            $item = HTML::li();
        }
        elseif ($bullet == '#') {
            $this->_tag = 'ol';
            $item = HTML::li();
        }
        else {
            $this->_tag = 'dl';
            list ($term,) = explode(':', substr($prefix, 1), 2);
            $term = trim($term);
            if ($term)
                $this->pushContent(HTML::dt(false, TransformInline($term)));
            $item = HTML::dd();
        }

        $item->pushContent(new SubBlock($input, $indent, $m->match));
        $this->pushContent($item);
        return true;
    }
}

class Block_pre extends BlockMarkup
{
    var $_tag = 'pre';
    var $_re = '<(?:pre|verbatim)>';

    function _match (&$input, $m) {
        $endtag = '</' . substr($m->match, 1);

        if (($text = $this->_getBlock($input, $endtag)) === false)
            return false;
        
        if (ltrim($m->postmatch))
            array_unshift($text, $m->postmatch);
        $text = join("\n", $text);
        
        // FIXME: no <img>, <big>, <small>, <sup>, or <sub>'s allowed
        // in a <pre>.
        if ($m->match == '<pre>')
            $text = TransformInline($text);

        $this->pushContent($text);
        return true;
    }

    function _getBlock (&$input, $end_tag) {
        $pos = $input->getPos();
        $text = array();

        while ( ($line = $input->nextLine()) !== false ) {
            if (rtrim($line) == $end_tag) {
                $input->advance();
                return $text;
            }
            $text[] = $line;
        }
        $input->setPos($pos);
        return false;
    }
}


class Block_plugin extends Block_pre
{
    var $_tag = 'div';
    var $_attr = array('class' => 'plugin');
    var $_re = '<\?plugin(?:-form)?(?!\S)';

    // FIXME:
    /* <?plugin Backlinks
     *       page=ThisPage ?>
     *
     * should work. */
    function _match (&$input, $m) {
        $pos = $input->getPos();
        $pi = $m->match . $m->postmatch;
        while (!preg_match('/(?<!~)\?>\s*$/', $pi)) {
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
            $pi .= "\n$line";
        }
        $input->advance();
            
        global $request;
        $loader = new WikiPluginLoader;
        $this->pushContent($loader->expandPI($pi, $request));
        return true;
    }
}

class Block_email_blockquote extends Block
{
    // FIXME: move CSS to CSS.
    var $_tag ='blockquote';
    var $_attr = array('style' => 'border-left-width: medium; border-left-color: #0f0; border-left-style: ridge; padding-left: 1em; margin-left: 0em; margin-right: 0em;');
    var $_depth;
    var $_re = '>\ ?';
    
    function _match (&$input, $m) {
        $indent = str_replace(' ', '\\ ', $m->match);
        $this->pushContent(new SubBlock($input, $indent, $m->match));
        return true;
    }
}

class Block_hr extends BlockMarkup
{
    var $_tag = 'hr';
    var $_re = '-{4,}\s*$';

    function _match (&$input, $m) {
        $input->advance();
        return true;
    }
}

class Block_heading extends BlockMarkup
{
    var $_re = '!{1,3}';
    
    function _match (&$input, $m) {
        $this->_tag = "h" . (5 - strlen($m->match));
        $this->pushContent(TransformInline(trim($m->postmatch)));
        $input->advance();
        return true;
    }
}

class Block_p extends BlockMarkup
{
    var $_tag = 'p';
    var $_re = '\S.*';

    function _match (&$input, $m) {
        $this->_text = $m->match;
        $input->advance();
        return true;
    }
    
    function merge ($nextBlock) {
        $class = get_class($nextBlock);
        if ($class == 'block_p' && !$nextBlock->_follows_space) {
            $this->_text .= $nextBlock->_text;
            return $this;
        }
        return false;
    }
            
    function finish () {
        $this->pushContent(TransformInline(trim($this->_text)));
        return parent::finish();
    }
}

////////////////////////////////////////////////////////////////
//




// FIXME: This is temporary, too...
function NewTransform ($text) {

    set_time_limit(5);
    
    // Expand leading tabs.
    // FIXME: do this better. also move  it...
    $text = preg_replace('/^\ *[^\ \S\n][^\S\n]*/me', "str_repeat(' ', strlen('\\0'))", $text);
    assert(!preg_match('/^\ *\t/', $text));

    $output = new Block($text);
    return $output;
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
