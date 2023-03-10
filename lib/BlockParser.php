<?php
/**
 * Copyright © 2002 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright © 2004,2005 Reini Urban
 * Copyright © 2008-2010 Marc-Etienne Vargenau, Alcatel-Lucent
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
require_once 'lib/CachedMarkup.php';
require_once 'lib/InlineParser.php';

/**
 * Deal with paragraphs and proper, recursive block indents
 * for the new style markup (version 2)
 *
 * Everything which goes over more than line:
 * automatic lists, UL, OL, DL, table, blockquote, verbatim,
 * p, pre, plugin, ...
 *
 * FIXME:
 *  Still to do:
 *    (old-style) tables
 * FIXME: unify this with the RegexpSet in InlineParser.
 *
 * FIXME: This is very php5 sensitive: It was fixed for 1.3.9,
 *        but is again broken with the 1.3.11
 *        allow_call_time_pass_reference clean fixes
 *
 * @package Markup
 * @author: Geoffrey T. Dairiki
 */

/**
 * Return type from RegexpSet::match and RegexpSet::nextMatch.
 *
 * @see RegexpSet
 */
class AnchoredRegexpSet_match
{
    /**
     * The matched text.
     */
    public $match;

    /**
     * The text following the matched text.
     */
    public $postmatch;

    /**
     * Index of the regular expression which matched.
     */
    public $regexp_ind;
}

/**
 * A set of regular expressions.
 *
 * This class is probably only useful for InlineTransformer.
 */
class AnchoredRegexpSet
{
    /**
     * @param $regexps array A list of regular expressions.  The
     * regular expressions should not include any sub-pattern groups
     * "(...)".  (Anonymous groups, like "(?:...)", as well as
     * look-ahead and look-behind assertions are fine.)
     */
    public function __construct($regexps)
    {
        $this->_regexps = $regexps;
        $this->_re = "/((" . join(")|(", $regexps) . "))/Ax";
    }

    /**
     * Search text for the next matching regexp from the Regexp Set.
     *
     * @param $text string The text to search.
     *
     * @return AnchoredRegexpSet_match|bool An AnchoredRegexpSet_match object, or false if no match.
     */
    public function match($text)
    {
        if (!is_string($text)) {
            return false;
        }
        if (!preg_match($this->_re, $text, $m)) {
            return false;
        }

        $match = new AnchoredRegexpSet_match();
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
     * @param string $text Text to search.
     * @param RegexpSet_match $prevMatch
     *
     * $prevMatch should be a match object obtained by a previous
     * match upon the same value of $text.
     *
     * @return AnchoredRegexpSet_match|bool An AnchoredRegexpSet_match object, or false if no match.
     */
    public function nextMatch($text, $prevMatch)
    {
        // Try to find match at same position.
        $regexps = array_slice($this->_regexps, $prevMatch->regexp_ind + 1);
        if (!$regexps) {
            return false;
        }

        $pat = "/ ( (" . join(')|(', $regexps) . ") ) /Axs";

        if (!preg_match($pat, $text, $m)) {
            return false;
        }

        $match = new AnchoredRegexpSet_match();
        $match->postmatch = substr($text, strlen($m[0]));
        $match->match = $m[1];
        $match->regexp_ind = count($m) - 3 + $prevMatch->regexp_ind + 1;

        return $match;
    }
}

class BlockParser_Input
{
    public function __construct($text)
    {
        // Expand leading tabs.
        // FIXME: do this better.
        //
        // We want to ensure the only characters matching \s are ' ' and "\n".
        //
        $text = preg_replace('/(?![ \n])\s/', ' ', $text);
        assert(!preg_match('/(?![ \n])\s/', $text));

        $this->_lines = preg_split('/[^\S\n]*\n/', $text);
        $this->_pos = 0;

        // Strip leading blank lines.
        while ($this->_lines and !$this->_lines[0]) {
            array_shift($this->_lines);
        }
        $this->_atSpace = false;
    }

    public function skipSpace()
    {
        $nlines = count($this->_lines);
        while (1) {
            if ($this->_pos >= $nlines) {
                $this->_atSpace = false;
                break;
            }
            if ($this->_lines[$this->_pos] != '') {
                break;
            }
            $this->_pos++;
            $this->_atSpace = true;
        }
        return $this->_atSpace;
    }

    public function currentLine()
    {
        if ($this->_pos >= count($this->_lines)) {
            return false;
        }
        return $this->_lines[$this->_pos];
    }

    public function nextLine()
    {
        $this->_atSpace = $this->_lines[$this->_pos++] === '';
        if ($this->_pos >= count($this->_lines)) {
            return false;
        }
        return $this->_lines[$this->_pos];
    }

    public function advance()
    {
        $this->_atSpace = ($this->_lines[$this->_pos] === '');
        $this->_pos++;
    }

    public function getPos()
    {
        return array($this->_pos, $this->_atSpace);
    }

    public function setPos($pos)
    {
        list($this->_pos, $this->_atSpace) = $pos;
    }

    public function getPrefix()
    {
        return '';
    }

    public function getDepth()
    {
        return 0;
    }

    public function where()
    {
        if ($this->_pos < count($this->_lines)) {
            return $this->_lines[$this->_pos];
        } else {
            return "<EOF>";
        }
    }

    public function _debug($tab, $msg)
    {
        //return ;
        $where = $this->where();
        $tab = str_repeat('____', $this->getDepth()) . $tab;
        PrintXML(HTML::div(
            "$tab $msg: at: '",
            HTML::samp($where),
            "'"
        ));
        flush();
    }
}

class BlockParser_InputSubBlock extends BlockParser_Input
{
    /**
     * @param BlockParser_Input $input
     * @param string $prefix_re
     * @param string $initial_prefix
     */
    public function __construct(&$input, $prefix_re, $initial_prefix = '')
    {
        $this->_input = &$input;
        $this->_prefix_pat = "/$prefix_re|\\s*\$/Ax";
        $this->_atSpace = false;

        if (($line = $input->currentLine()) === false) {
            $this->_line = false;
        } elseif ($initial_prefix) {
            assert(substr($line, 0, strlen($initial_prefix)) == $initial_prefix);
            $this->_line = (string)substr($line, strlen($initial_prefix));
            $this->_atBlank = !ltrim($line);
        } elseif (preg_match($this->_prefix_pat, $line, $m)) {
            $this->_line = (string)substr($line, strlen($m[0]));
            $this->_atBlank = !ltrim($line);
        } else {
            $this->_line = false;
        }
    }

    public function skipSpace()
    {
        // In contrast to the case for top-level blocks,
        // for sub-blocks, there never appears to be any trailing space.
        // (The last block in the sub-block should always be of class tight-bottom.)
        while ($this->_line === '') {
            $this->advance();
        }

        if ($this->_line === false) {
            return $this->_atSpace == 'strong_space';
        } else {
            return $this->_atSpace;
        }
    }

    public function currentLine()
    {
        return $this->_line;
    }

    public function nextLine()
    {
        if ($this->_line === '') {
            $this->_atSpace = $this->_atBlank ? 'weak_space' : 'strong_space';
        } else {
            $this->_atSpace = false;
        }

        $line = $this->_input->nextLine();
        if ($line !== false && preg_match($this->_prefix_pat, $line, $m)) {
            $this->_line = (string)substr($line, strlen($m[0]));
            $this->_atBlank = !ltrim($line);
        } else {
            $this->_line = false;
        }

        return $this->_line;
    }

    public function advance()
    {
        $this->nextLine();
    }

    public function getPos()
    {
        return array($this->_line, $this->_atSpace, $this->_input->getPos());
    }

    public function setPos($pos)
    {
        $this->_line = $pos[0];
        $this->_atSpace = $pos[1];
        $this->_input->setPos($pos[2]);
    }

    public function getPrefix()
    {
        assert($this->_line !== false);
        $line = $this->_input->currentLine();
        assert($line !== false && strlen($line) >= strlen($this->_line));
        return substr($line, 0, strlen($line) - strlen($this->_line));
    }

    public function getDepth()
    {
        return $this->_input->getDepth() + 1;
    }

    public function where()
    {
        return $this->_input->where();
    }
}

class Block_HtmlElement extends HtmlElement
{
    public function __construct($tag /*, ... */)
    {
        $this->_init(func_get_args());
    }

    public function setTightness($top, $bottom)
    {
    }
}

class ParsedBlock extends Block_HtmlElement
{
    private $_block_types;
    private $_regexps;
    private $_regexpset;
    private $_atSpace;

    public function __construct(&$input, $tag = 'div', $attr = array())
    {
        parent::__construct($tag, $attr);
        $this->initBlockTypes();
        $this->_parse($input);
    }

    private function _parse(&$input)
    {
        // php5 failed to advance the block. php5 copies objects by ref.
        // nextBlock == block, both are the same objects. So we have to clone it.
        for ($block = $this->getBlock($input);
             $block;
             $block = (is_object($nextBlock) ? clone($nextBlock) : $nextBlock)) {
            while ($nextBlock = $this->getBlock($input)) {
                // Attempt to merge current with following block.
                if (!($merged = $block->merge($nextBlock))) {
                    break; // can't merge
                }
                $block = $merged;
            }
            $this->pushContent($block->finish());
        }
    }

    // FIXME: hackish. This should only be called once.
    private function initBlockTypes()
    {
        // better static or global?
        static $_regexpset, $_block_types;

        if (!is_object($_regexpset)) {
            // nowiki_wikicreole must be before template_plugin
            $Block_types = array('nowiki_wikicreole', 'template_plugin', 'placeholder', 'oldlists', 'list', 'dl',
                'table_dl', 'table_wikicreole', 'table_mediawiki',
                'blockquote', 'heading', 'heading_wikicreole', 'hr', 'pre',
                'email_blockquote', 'wikicreole_indented',
                'plugin', 'plugin_wikicreole', 'p');
            // insert it before p!
            if (defined('ENABLE_MARKUP_DIVSPAN') and ENABLE_MARKUP_DIVSPAN) {
                array_pop($Block_types);
                $Block_types[] = 'divspan';
                $Block_types[] = 'p';
            }
            foreach ($Block_types as $type) {
                $class = "Block_$type";
                $proto = new $class();
                $this->_block_types[] = $proto;
                $this->_regexps[] = $proto->_re;
            }
            $this->_regexpset = new AnchoredRegexpSet($this->_regexps);
            $_regexpset = $this->_regexpset;
            $_block_types = $this->_block_types;
            unset($Block_types);
        } else {
            $this->_regexpset = $_regexpset;
            $this->_block_types = $_block_types;
        }
    }

    private function getBlock(&$input)
    {
        $this->_atSpace = $input->skipSpace();

        $line = $input->currentLine();
        if ($line === false or $line === '') { // allow $line === '0'
            return false;
        }
        $tight_top = !$this->_atSpace;
        $re_set = &$this->_regexpset;
        //FIXME: php5 fails to advance here!
        for ($m = $re_set->match($line); $m; $m = $re_set->nextMatch($line, $m)) {
            $block = clone($this->_block_types[$m->regexp_ind]);
            if (DEBUG & _DEBUG_PARSER) {
                $input->_debug('>', get_class($block));
            }

            if ($block->_match($input, $m)) {
                //$block->_text = $line;
                if (DEBUG & _DEBUG_PARSER) {
                    $input->_debug('<', get_class($block));
                }
                $tight_bottom = !$input->skipSpace();
                $block->_setTightness($tight_top, $tight_bottom);
                return $block;
            }
            if (DEBUG & _DEBUG_PARSER) {
                $input->_debug('[', "_match failed");
            }
        }
        if ($line === false or $line === '') { // allow $line === '0'
            return false;
        }

        trigger_error("Couldn't match block: '$line'");
        return false;
    }
}

class WikiText extends ParsedBlock
{
    public function __construct($text)
    {
        $input = new BlockParser_Input($text);
        parent::__construct($input);
    }
}

class SubBlock extends ParsedBlock
{
    public function __construct(
        &$input,
        $indent_re,
        $initial_indent = false,
        $tag = 'div',
        $attr = array()
    )
    {
        $subinput = new BlockParser_InputSubBlock($input, $indent_re, $initial_indent);
        parent::__construct($subinput, $tag, $attr);
    }
}

/**
 * TightSubBlock is for use in parsing lists item bodies.
 *
 * If the sub-block consists of a single paragraph, it omits
 * the paragraph element.
 *
 * We go to this trouble so that "tight" lists look somewhat reasonable
 * in older (non-CSS) browsers.  (If you don't do this, then, without
 * CSS, you only get "loose" lists.
 */
class TightSubBlock extends SubBlock
{
    public function __construct(
        &$input,
        $indent_re,
        $initial_indent = false,
        $tag = 'div',
        $attr = array()
    )
    {
        parent::__construct($input, $indent_re, $initial_indent, $tag, $attr);

        // If content is a single paragraph, eliminate the paragraph...
        if (count($this->_content) == 1) {
            $elem = $this->_content[0];
            if (is_a($elem, 'XmlElement') and $elem->getTag() == 'p') {
                $this->setContent($elem->getContent());
            }
        }
    }
}

abstract class BlockMarkup
{
    public $_re;
    protected $_element;

    abstract public function _match(&$input, $match);

    public function _setTightness($top, $bot)
    {
    }

    public function merge($followingBlock)
    {
        return false;
    }

    public function finish()
    {
        return $this->_element;
    }
}

class Block_blockquote extends BlockMarkup
{
    public $_depth;
    public $_re = '\ +(?=\S)';
    protected $_element;

    public function _match(&$input, $m)
    {
        $this->_depth = strlen($m->match);
        $indent = sprintf("\\ {%d}", $this->_depth);
        $this->_element = new SubBlock(
            $input,
            $indent,
            $m->match,
            'blockquote'
        );
        return true;
    }

    public function merge($nextBlock)
    {
        if (get_class($nextBlock) == get_class($this)) {
            assert($nextBlock->_depth < $this->_depth);
            $nextBlock->_element->unshiftContent($this->_element);
            if (!empty($this->_tight_top)) {
                $nextBlock->_tight_top = $this->_tight_top;
            }
            return $nextBlock;
        }
        return false;
    }
}

class Block_list extends BlockMarkup
{
    public $_re = '\ {0,4}
                (?: \+
                  | \\#\ (?!\[.*\])
                  | -(?!-)
                  | [o](?=\ )
                  | [*]\ (?!(?=\S)[^*]*(?<=\S)[*](?:\\s|[-)}>"\'\\/:.,;!?_*=]) )
                )\ *(?=\S)';
    public $_content = array();
    public $_tag; //'ol' or 'ul'

    public function _match(&$input, $m)
    {
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
        $this->_content[] = new TightSubBlock($input, $indent, $m->match, 'li');
        return true;
    }

    public function _setTightness($top, $bot)
    {
        $li = &$this->_content[0];
        $li->setTightness($top, $bot);
    }

    public function merge($nextBlock)
    {
        if (is_a($nextBlock, 'Block_list') and $this->_tag == $nextBlock->_tag) {
            array_splice(
                $this->_content,
                count($this->_content),
                0,
                $nextBlock->_content
            );
            return $this;
        }
        return false;
    }

    public function finish()
    {
        return new Block_HtmlElement($this->_tag, false, $this->_content);
    }
}

class Block_dl extends Block_list
{
    public $_tag = 'dl';
    private $_tight_defn;

    public function __construct()
    {
        $this->_re = '\ {0,4}\S.*(?<!' . ESCAPE_CHAR . '):\s*$';
    }

    public function _match(&$input, $m)
    {
        if (!($p = $this->_do_match($input, $m))) {
            return false;
        }
        list($term, $defn, $loose) = $p;

        $this->_content[] = new Block_HtmlElement('dt', false, $term);
        $this->_content[] = $defn;
        $this->_tight_defn = !$loose;
        return true;
    }

    public function _setTightness($top, $bot)
    {
        $dt = &$this->_content[0];
        $dd = &$this->_content[1];

        $dt->setTightness($top, $this->_tight_defn);
        $dd->setTightness($this->_tight_defn, $bot);
    }

    public function _do_match(&$input, $m)
    {
        $pos = $input->getPos();

        $firstIndent = strspn($m->match, ' ');
        $pat = sprintf('/\ {%d,%d}(?=\s*\S)/A', $firstIndent + 1, $firstIndent + 5);

        $input->advance();
        $loose = $input->skipSpace();
        $line = $input->currentLine();

        if (!$line || !preg_match($pat, $line, $mm)) {
            $input->setPos($pos);
            return false; // No body found.
        }

        $indent = strlen($mm[0]);
        $term = TransformInline(rtrim(substr(trim($m->match), 0, -1)));
        $defn = new TightSubBlock($input, sprintf("\\ {%d}", $indent), false, 'dd');
        return array($term, $defn, $loose);
    }
}

class Block_table_dl_defn extends XmlContent
{
    public $nrows;
    public $ncols;
    private $_accum;
    private $_tight_top;
    private $_tight_bot;

    public function __construct($term, $defn)
    {
        parent::__construct();
        if (!is_array($defn)) {
            $defn = $defn->getContent();
        }

        $this->_next_tight_top = false; // value irrelevant - gets fixed later
        $this->_ncols = $this->ComputeNcols($defn);
        $this->_nrows = 0;

        foreach ($defn as $item) {
            if ($this->IsASubtable($item)) {
                $this->addSubtable($item);
            } else {
                $this->addToRow($item);
            }
        }
        $this->flushRow();

        $th = HTML::th($term);
        if ($this->_nrows > 1) {
            $th->setAttr('rowspan', $this->_nrows);
        }
        $this->_setTerm($th);
    }

    public function setTightness($tight_top, $tight_bot)
    {
        $this->_tight_top = $tight_top;
        $this->_tight_bot = $tight_bot;
    }

    private function addToRow($item)
    {
        if (empty($this->_accum)) {
            $this->_accum = HTML::td();
            if ($this->_ncols > 2) {
                $this->_accum->setAttr('colspan', $this->_ncols - 1);
            }
        }
        $this->_accum->pushContent($item);
    }

    private function flushRow($tight_bottom = false)
    {
        if (!empty($this->_accum)) {
            $row = new Block_HtmlElement('tr', false, $this->_accum);

            $row->setTightness($this->_next_tight_top, $tight_bottom);
            $this->_next_tight_top = $tight_bottom;

            $this->pushContent($row);
            $this->_accum = false;
            $this->_nrows++;
        }
    }

    private function addSubtable($table)
    {
        if (!($table_rows = $table->getContent())) {
            return;
        }

        $this->flushRow($table_rows[0]->_tight_top);

        foreach ($table_rows as $subdef) {
            $this->pushContent($subdef);
            $this->_nrows += $subdef->nrows();
            $this->_next_tight_top = $subdef->_tight_bot;
        }
    }

    private function _setTerm($th)
    {
        $first_row = &$this->_content[0];
        if (is_a($first_row, 'Block_table_dl_defn')) {
            $first_row->_setTerm($th);
        } else {
            $first_row->unshiftContent($th);
        }
    }

    private function ComputeNcols($defn)
    {
        $ncols = 2;
        foreach ($defn as $item) {
            if ($this->IsASubtable($item)) {
                $row = $this->FirstDefn($item);
                $ncols = max($ncols, $row->ncols() + 1);
            }
        }
        return $ncols;
    }

    private function IsASubtable($item)
    {
        return is_a($item, 'HtmlElement')
            && $item->getTag() == 'table'
            && $item->getAttr('class') == 'wiki-dl-table';
    }

    private function FirstDefn($subtable)
    {
        $defs = $subtable->getContent();
        return $defs[0];
    }

    public function ncols()
    {
        return $this->_ncols;
    }

    public function nrows()
    {
        return $this->_nrows;
    }

    public function & firstTR()
    {
        $first = &$this->_content[0];
        if (is_a($first, 'Block_table_dl_defn')) {
            return $first->firstTR();
        }
        return $first;
    }

    public function & lastTR()
    {
        $last = &$this->_content[$this->_nrows - 1];
        if (is_a($last, 'Block_table_dl_defn')) {
            return $last->lastTR();
        }
        return $last;
    }

    public function setWidth($ncols)
    {
        assert($ncols >= $this->_ncols);
        if ($ncols <= $this->_ncols) {
            return;
        }
        $rows = &$this->_content;
        for ($i = 0; $i < count($rows); $i++) {
            $row = &$rows[$i];
            if (is_a($row, 'Block_table_dl_defn')) {
                $row->setWidth($ncols - 1);
            } else {
                $n = count($row->_content);
                $lastcol = &$row->_content[$n - 1];
                if (!empty($lastcol)) {
                    $lastcol->setAttr('colspan', $ncols - 1);
                }
            }
        }
    }
}

class Block_table_dl extends Block_dl
{
    public $_tag = 'dl-table'; // phony.

    public function __construct()
    {
        $this->_re = '\ {0,4} (?:\S.*)? (?<!' . ESCAPE_CHAR . ') \| \s* $';
    }

    public function _match(&$input, $m)
    {
        if (!($p = $this->_do_match($input, $m))) {
            return false;
        }
        list($term, $defn, $loose) = $p;

        $this->_content[] = new Block_table_dl_defn($term, $defn);
        return true;
    }

    public function _setTightness($top, $bot)
    {
        $this->_content[0]->setTightness($top, $bot);
    }

    public function finish()
    {
        $defs = &$this->_content;

        $ncols = 0;
        foreach ($defs as $defn) {
            $ncols = max($ncols, $defn->ncols());
        }

        foreach ($defs as $key => $defn) {
            $defs[$key]->setWidth($ncols);
        }

        return HTML::table(array('class' => 'wiki-dl-table'), $defs);
    }
}

class Block_oldlists extends Block_list
{
    //public $_tag = 'ol', 'ul', or 'dl';
    public $_re = '(?: [*]\ (?!(?=\S)[^*]*(?<=\S)[*](?:\\s|[-)}>"\'\\/:.,;!?_*=]))
                  | [#]\ (?! \[ .*? \] )
                  | ; .*? :
                ) .*? (?=\S)';

    public function _match(&$input, $m)
    {
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
            $itemtag = 'li';
        } elseif ($bullet == '#') {
            $this->_tag = 'ol';
            $itemtag = 'li';
        } else {
            $this->_tag = 'dl';
            list($term, ) = explode(':', substr($prefix, 1), 2);
            $term = trim($term);
            if ($term) {
                $this->_content[] = new Block_HtmlElement(
                    'dt',
                    false,
                    TransformInline($term)
                );
            }
            $itemtag = 'dd';
        }

        $this->_content[] = new TightSubBlock($input, $indent, $m->match, $itemtag);
        return true;
    }

    public function _setTightness($top, $bot)
    {
        if (count($this->_content) == 1) {
            $li = &$this->_content[0];
            $li->setTightness($top, $bot);
        } else {
            $dt = &$this->_content[0];
            $dd = &$this->_content[1];
            $dt->setTightness($top, false);
            $dd->setTightness(false, $bot);
        }
    }
}

class Block_pre extends BlockMarkup
{
    public $_re = '<(?:pre|verbatim|nowiki|noinclude|includeonly)>';

    public function _match(&$input, $m)
    {
        $endtag = '</' . substr($m->match, 1);
        $text = array();
        $pos = $input->getPos();

        $line = $m->postmatch;
        while (ltrim($line) != $endtag) {
            $text[] = $line;
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
        }
        $input->advance();

        if ($m->match == '<includeonly>') {
            $this->_element = new Block_HtmlElement('div', false, '');
            return true;
        }

        if ($m->match == '<nowiki>') {
            $text = join("<br>\n", $text);
        } else {
            $text = join("\n", $text);
        }

        if ($m->match == '<noinclude>') {
            $text = TransformText($text);
            $this->_element = new Block_HtmlElement('div', false, $text);
        } elseif ($m->match == '<nowiki>') {
            $text = TransformInlineNowiki($text);
            $this->_element = new Block_HtmlElement('p', false, $text);
        } else {
            $this->_element = new Block_HtmlElement('pre', false, $text);
        }
        return true;
    }
}

// Wikicreole placeholder
// <<<placeholder>>>
class Block_placeholder extends BlockMarkup
{
    public $_re = '<<<';

    public function _match(&$input, $m)
    {
        $endtag = '>>>';
        $text = array();
        $pos = $input->getPos();

        $line = $m->postmatch;
        while (ltrim($line) != $endtag) {
            $text[] = $line;
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
        }
        $input->advance();

        $text = join("\n", $text);
        $text = '<<<' . $text . '>>>';
        $this->_element = new Block_HtmlElement('div', false, $text);
        return true;
    }
}

class Block_nowiki_wikicreole extends BlockMarkup
{
    public $_re = '{{{';

    public function _match(&$input, $m)
    {
        $endtag = '}}}';
        $text = array();
        $pos = $input->getPos();

        $line = $m->postmatch;
        while (ltrim($line) != $endtag) {
            $text[] = $line;
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
        }
        $input->advance();

        $text = join("\n", $text);
        $this->_element = new Block_HtmlElement('pre', false, $text);
        return true;
    }
}

class Block_plugin extends Block_pre
{
    public $_re = '<\?plugin(?:-form)?(?!\S)';

    // FIXME:
    /* <?plugin Backlinks
     *       page=ThisPage ?>
    /* <?plugin ListPages pages=<!plugin-list Backlinks!>
     *                    exclude=<!plugin-list TitleSearch s=T*!> ?>
     *
     * should all work.
     */
    public function _match(&$input, $m)
    {
        $pos = $input->getPos();
        $pi = $m->match . $m->postmatch;
        while (!preg_match('/(?<!' . ESCAPE_CHAR . ')\?>\s*$/', $pi)) {
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
            $pi .= "\n$line";
        }
        $input->advance();

        $this->_element = new Cached_PluginInvocation($pi);
        return true;
    }
}

class Block_plugin_wikicreole extends Block_pre
{
    // public $_re = '<<(?!\S)';
    public $_re = '<<';

    public function _match(&$input, $m)
    {
        $pos = $input->getPos();
        $pi = $m->postmatch;
        if ($pi[0] == '<') {
            return false;
        }
        $pi = "<?plugin " . $pi;
        while (!preg_match('/(?<!' . ESCAPE_CHAR . ')>>\s*$/', $pi)) {
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
            $pi .= "\n$line";
        }
        $input->advance();

        $pi = str_replace(">>", "?>", $pi);

        $this->_element = new Cached_PluginInvocation($pi);
        return true;
    }
}

class Block_table_wikicreole extends Block_pre
{
    public $_re = '\s*\|';

    public function _match(&$input, $m)
    {
        $pos = $input->getPos();
        $pi = "|" . $m->postmatch;

        $intable = true;
        while ($intable) {
            if ((($line = $input->nextLine()) === false) && !$intable) {
                $input->setPos($pos);
                return false;
            }
            if (!$line) {
                $intable = false;
                $trimline = $line;
            } else {
                $trimline = trim($line);
                if ($trimline[0] != "|") {
                    $intable = false;
                }
            }
            $pi .= "\n$trimline";
        }

        $pi = '<' . '?plugin WikicreoleTable ' . $pi . '?' . '>';

        $this->_element = new Cached_PluginInvocation($pi);
        return true;
    }
}

/**
 *  Table syntax similar to Mediawiki
 *  {|
 * => <?plugin MediawikiTable
 *  |}
 * => ?>
 */
class Block_table_mediawiki extends Block_pre
{
    public $_re = '{\|';

    public function _match(&$input, $m)
    {
        $pos = $input->getPos();
        $pi = $m->postmatch;
        while (!preg_match('/(?<!' . ESCAPE_CHAR . ')\|}\s*$/', $pi)) {
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
            $pi .= "\n$line";
        }
        $input->advance();

        $pi = str_replace("\|}", "", $pi);
        $pi = '<' . '?plugin MediawikiTable ' . $pi . '?' . '>';
        $this->_element = new Cached_PluginInvocation($pi);
        return true;
    }
}

/**
 *  Template syntax similar to Mediawiki
 *  {{template}}
 * => < ? plugin Template page=template ? >
 *  {{template|var1=value1|var2=value|...}}
 * => < ? plugin Template page=template var=value ... ? >
 *
 * The {{...}} syntax is also used for:
 *  - Wikicreole images
 *  - videos
 */
class Block_template_plugin extends Block_pre
{
    public $_re = '{{';

    public function _match(&$input, $m)
    {
        // If we find "}}", this is an inline template.
        if (strpos($m->postmatch, "}}") !== false) {
            return false;
        }
        $pos = $input->getPos();
        $pi = $m->postmatch;
        if ($pi[0] == '{') {
            return false;
        }
        while (!preg_match('/(?<!' . ESCAPE_CHAR . ')}}\s*$/', $pi)) {
            if (($line = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
            $pi .= "\n$line";
        }
        $input->advance();

        $pi = trim($pi);
        $pi = trim($pi, "}");

        if (strpos($pi, "|") === false) {
            $imagename = $pi;
            $alt = "";
        } else {
            $imagename = substr($pi, 0, strpos($pi, "|"));
            $alt = ltrim(strstr($pi, "|"), "|");
        }

        // It's not a Mediawiki template, it's a Wikicreole image
        if (is_image($imagename)) {
            $this->_element = LinkImage(getUploadDataPath() . $imagename, $alt);
            return true;
        }

        // It's a video
        if (is_video($imagename)) {
            if ((strpos($imagename, 'http://') === 0)
              || (strpos($imagename, 'https://') === 0)) {
                $pi = '<' . '?plugin Video url="' . $pi . '" ?>';
            } else {
                $pi = '<' . '?plugin Video file="' . $pi . '" ?>';
            }
            $this->_element = new Cached_PluginInvocation($pi);
            return true;
        }

        $pi = str_replace("\n", "", $pi);

        // The argument value might contain a double quote (")
        // We have to encode that.
        $pi = htmlspecialchars($pi);

        $vars = '';

        if (preg_match('/^(\S+?)\|(.*)$/', $pi, $_m)) {
            $pi = $_m[1];
            $vars = '"' . preg_replace('/\|/', '" "', $_m[2]) . '"';
            $vars = preg_replace('/"(\S+)=([^"]*)"/', '\\1="\\2"', $vars);
        }

        // pi may contain a version number
        // {{foo?version=5}}
        // in that case, output is "page=foo rev=5"
        if (strstr($pi, "?")) {
            $pi = str_replace("?version=", "\" rev=\"", $pi);
        }

        if ($vars) {
            $pi = '<' . '?plugin Template page="' . $pi . '" ' . $vars . ' ?>';
        } else {
            $pi = '<' . '?plugin Template page="' . $pi . '" ?>';
        }
        $this->_element = new Cached_PluginInvocation($pi);
        return true;
    }
}

class Block_email_blockquote extends BlockMarkup
{
    public $_attr = array('class' => 'mail-style-quote');
    public $_re = '>\ ?';

    public function _match(&$input, $m)
    {
        //$indent = str_replace(' ', '\\ ', $m->match) . '|>$';
        $indent = $this->_re;
        $this->_element = new SubBlock($input, $indent, $m->match, 'blockquote', $this->_attr);
        return true;
    }
}

class Block_wikicreole_indented extends BlockMarkup
{
    public $_attr = array('style' => 'margin-left:2em');
    public $_re = ':\ ?';

    public function _match(&$input, $m)
    {
        $indent = $this->_re;
        $this->_element = new SubBlock(
            $input,
            $indent,
            $m->match,
            'div',
            $this->_attr
        );
        return true;
    }
}

class Block_hr extends BlockMarkup
{
    public $_re = '-{4,}\s*$';

    public function _match(&$input, $m)
    {
        $input->advance();
        $this->_element = new Block_HtmlElement('hr');
        return true;
    }
}

class Block_heading extends BlockMarkup
{
    public $_re = '!{1,3}';

    public function _match(&$input, $m)
    {
        $tag = "h" . (5 - strlen($m->match));
        $text = TransformInline(trim($m->postmatch));
        $input->advance();

        $this->_element = new Block_HtmlElement($tag, false, $text);

        return true;
    }
}

class Block_heading_wikicreole extends BlockMarkup
{
    public $_re = '={2,6}';

    public function _match(&$input, $m)
    {
        $tag = "h" . strlen($m->match);
        // Remove spaces
        $header = trim($m->postmatch);
        // Remove '='s at the end so that Mediawiki syntax is recognized
        $header = trim($header, "=");
        $text = TransformInline(trim($header));
        $input->advance();

        $this->_element = new Block_HtmlElement($tag, false, $text);

        return true;
    }
}

class Block_p extends BlockMarkup
{
    public $_tag = 'p';
    public $_re = '\S.*';
    public $_text = '';
    private $_tight_bot;
    private $_tight_top;

    public function _match(&$input, $m)
    {
        $this->_text = $m->match;
        $input->advance();
        return true;
    }

    public function _setTightness($top, $bot)
    {
        $this->_tight_top = $top;
        $this->_tight_bot = $bot;
    }

    public function merge($nextBlock)
    {
        $class = get_class($nextBlock);
        if (strtolower($class) == 'block_p' and $this->_tight_bot) {
            $this->_text .= "\n" . $nextBlock->_text;
            $this->_tight_bot = $nextBlock->_tight_bot;
            return $this;
        }
        return false;
    }

    public function finish()
    {
        $content = TransformInline(trim($this->_text));
        $p = new Block_HtmlElement('p', false, $content);
        $p->setTightness($this->_tight_top, $this->_tight_bot);
        return $p;
    }
}

class Block_divspan extends BlockMarkup
{
    public $_re = '<(?im)(?: div|span)(?:[^>]*)?>';

    public function _match(&$input, $m)
    {
        if (substr($m->match, 1, 4) == 'span') {
            $tag = 'span';
        } else {
            $tag = 'div';
        }
        // without last >
        $argstr = substr(trim(substr($m->match, strlen($tag) + 1)), 0, -1);
        $pos = $input->getPos();
        $pi = $content = $m->postmatch;
        while (!preg_match('/^(.*)\<\/' . $tag . '\>(.*)$/i', $pi, $me)) {
            if ($pi != $content) {
                $content .= "\n$pi";
            }
            if (($pi = $input->nextLine()) === false) {
                $input->setPos($pos);
                return false;
            }
        }
        if ($pi != $content) {
            $content .= $me[1];
        } // prematch
        else {
            $content = $me[1];
        }
        $input->advance();
        if (strstr($content, "\n")) {
            $content = TransformText($content);
        } else {
            $content = TransformInline($content);
        }
        if (!$argstr) {
            $args = false;
        } else {
            $args = array();
            while (preg_match("/(\w+)=(.+)/", $argstr, $m)) {
                $k = $m[1];
                $v = $m[2];
                if (preg_match("/^\"(.+?)\"(.*)$/", $v, $m)) {
                    $v = $m[1];
                    $argstr = $m[2];
                } else {
                    preg_match("/^(\s+)(.*)$/", $v, $m);
                    $v = $m[1];
                    $argstr = $m[2];
                }
                if (trim($k) and trim($v)) {
                    $args[$k] = $v;
                }
            }
        }
        $this->_element = new Block_HtmlElement($tag, $args, $content);
        return true;
    }
}

////////////////////////////////////////////////////////////////
//

/**
 * Transform the text of a page, and return a parse tree.
 */
function TransformTextPre($text)
{
    if (is_a($text, 'WikiDB_PageRevision')) {
        $rev = $text;
        $text = $rev->getPackedContent();
    }
    // Expand leading tabs.
    $text = expand_tabs($text);
    return new WikiText($text);
}

/**
 * Transform the text of a page, and return an XmlContent,
 * suitable for printXml()-ing.
 */
function TransformText($text, $basepage = false)
{
    $output = TransformTextPre($text);
    if ($basepage) {
        // This is for immediate consumption.
        // We must bind the contents to a base pagename so that
        // relative page links can be properly linkified...
        return new CacheableMarkup($output->getContent(), $basepage);
    }
    return new XmlContent($output->getContent());
}
