<?php rcs_id('$Id');
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
//FIXME:
require_once('lib/transform.php');

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
    // the old transform code does funny things with leading and trailing
    // white space.
    // All this is to ensure leading and trailing whitespace does not
    // get altered...
    $lines = explode("\n", $text);
    $out = '';
    while ($lines && !preg_match('/\S/', $lines[0]))
        $out .= array_shift($lines) . "\n";
    $tail = '';
    while ($lines && !preg_match('/\S/', $lines[count($lines)-1]))
        $tail = array_pop($lines) . "\n$tail";

    $trfm = new InlineTransform;
    $out .= preg_replace('/\n $/', '', AsXML($trfm->do_transform('', $lines)));
    $out .= $tail;
    
    //if ($out != $text) {
    //    echo(" IN <pre>'" . htmlspecialchars($text) . "'</pre><br>\n");
    //    echo("OUT <pre>'" . htmlspecialchars($out) . "'</pre><br>\n");
    //}
    return new RawXml($out);
}


////////////////////////////////////////////////////////////////
//
define("BLOCK_NEVER_TIGHTEN", 0);
define("BLOCK_NOTIGHTEN_AFTER", 1);
define("BLOCK_NOTIGHTEN_BEFORE", 2);
define("BLOCK_NOTIGHTEN_EITHER", 3);
/**
 * FIXME:
 *  Still to do:
 *    (old-style) tables
 *    <dl> style tables
 *    old-style lists.
 */

class BlockParser {
    var $_prefix_re	= '';
    var $_next_prefix	= '';
    var $_eof		= '/(?!.)/sA';
    var $_depth		= 0;    // debugging only?
    
    function BlockParser ($text) {
        $this->_text = $text;
        $this->_pos = 0;
        
        // Expand leading tabs.
        // FIXME: do this better.
        //
        // We want to ensure the only characters matching \s are ' ' and "\n".
        //
        $this->_text = preg_replace('/(?![ \n])\s/', ' ', $this->_text);
        assert(!preg_match('/(?![ \n])\s/', $this->_text));
        if (!preg_match('/\n$/', $this->_text))
            $this->_text .= "\n";
        
        $this->_atBreak = $this->_eatSpace();
    }

    /**
     * Consume blank lines.
     *
     * @return bool True if any blank lines where comsumed.
     */
    function _eatSpace () {
        $pfx = &$this->_prefix_re;
        if (!preg_match("/(?:$pfx)?\s*(\n|$)/A", substr($this->_text, $this->_pos), $m))
            return false;
        $this->_pos += strlen($m[0]);
        if (empty($m[1]))
            return false;       // eof

        if ($this->_next_prefix != $this->_prefix_re) {
            $this->_prefix_re = $this->_next_prefix;
            $this->_eatSpace();
        }
        return true;
        

        /*
        if (!preg_match('/\s*(\n|$)/A', substr($this->_text, $this->_pos), $m))
            return false;
        $this->_pos += strlen($m[0]);
        return !empty($m[1]);
        */
    }
    
    function nextBlock () {

        $this->_lastpos = $this->_pos;
        $this->_debug("prefix: '$this->_prefix_re'", "-", $this->_lastpos);
        
        global $Block_BlockTypes;
        foreach ($Block_BlockTypes as $type) {
            if ($m = $this->_match($type->_re)) {
                $this->_debug(get_class($type) . " ($this->_atBreak)", '>', $this->_lastpos);
                $block = $type;
                $block->_followsBreak = $this->_atBreak;
                        
                if (!$block->_parse($this, $m)) {
                    $this->_pos = $this->_lastpos;
                    $this->_debug(get_class($type) . ": _parse failed", '[');
                    continue;
                }
                
                if ($block->isTerminal())
                    $this->_atBreak = $this->_eatSpace();

                $block->_preceedsBreak = $this->_atBreak;
                $this->_debug(get_class($type) . " ($this->_atBreak)", '<');
                
                $this->_prefix_re = $this->_next_prefix;
                return $block;
            }
        }

        if ($this->_depth == 0 && $this->_pos != strlen($this->_text)) {
            // We should never get here.
            preg_match('/.*/A', substr($this->_text, $this->_pos), $m);// get first line
            trigger_error("Couldn't match block: '$m[0]'", E_USER_NOTICE);
        }
        $this->_debug("no match");
        return false;
    }

    function _debug ($msg, $tab = '=', $where = false) {
        return;
        
        if ($where === false)
            $where = $this->_pos;
        preg_match('/.*/A', substr($this->_text, $where), $m);// get first line
        $msg = str_repeat($tab, $this->_depth + 1) . " $msg: at: '$m[0]'";
        echo htmlspecialchars($msg) . "<br>\n";
    }
    
    function _match ($regexp) {
        $pat = sprintf("/%s%s/Am",
                       $this->_prefix_re,
                       preg_replace('/(?<![[\\\\])\^/',
                                    '^'. $this->_next_prefix,
                                    $regexp));
        
        if (!preg_match($pat, substr($this->_text, $this->_pos), $m))
            return false;
        $this->_pos += strlen($m[0]);
        return $m;
    }

    function getIndent () {
        $indent = substr($this->_text, 0, $this->_lastpos);
        if ($tail = strrchr($indent, "\n"))
            $indent = substr($tail, 1);
        return $indent;
    }
    
        
    
    //function _undoLastMatch () {
    //    $this->_text = $this->_lastMatch . $this->_text;
    //}
    
    function parse ($tighten_mode = BLOCK_NEVER_TIGHTEN) {
        $content = array();
        
        for ($block = $this->nextBlock(); $block; $block = $nextBlock) {
            while ($nextBlock = $this->nextBlock()) {
                // Attempt to merge current with following block.
                if (! $block->merge($nextBlock))
                    break;      // can't merge
            }

            $output = $block->finish($tighten_mode);
            if (is_array($output))
                foreach ($output as $x)
                    $content[] = $x;
            else
                $content[] = $output;
        }
        return $content;
    }

    function parseSubBlock ($initial_prefix,
                            $subsequent_prefix = false,
                            $tighten_mode = BLOCK_NOTIGHTEN_AFTER,
                            $atBreak = 'default') {
        $subblock = new SubBlockParser($this, $initial_prefix, $subsequent_prefix);
        if ($atBreak != 'default')
            $subblock->_atBreak = $atBreak;
        return $subblock->parse($tighten_mode);
    }
}

class SubBlockParser extends BlockParser
{
    function SubBlockParser (&$block, $initial_prefix, $subsequent_prefix = false) {
        $this->_text = &$block->_text;
        $this->_pos = &$block->_pos;
        $this->_atBreak = &$block->_atBreak;

        $this->_depth = $block->_depth + 1;
        
        if ($subsequent_prefix === false)
            $subsequent_prefix = $initial_prefix;

        $this->_prefix_re = $initial_prefix;
        $this->_next_prefix = $block->_next_prefix . $subsequent_prefix;
        
        if ($this->_eatSpace())
            $this->_atBreak = true;
    }
}

    

class Block {
    var $_tag;
    var $_attr = false;
    var $_re;
    var $_followsBreak = false;
    var $_preceedsBreak = false;
    var $_content = array();

        
    function _parse (&$input, $m) {
        $this->_pushContent(TransformInline($m[1]));
        return true;
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
    var $_re = '(?=(\ +)\S)';
    
    function _parse (&$input, $m) {
        $indent = $m[1];
        $this->_depth = strlen($indent);
        $this->_content = $input->parseSubBlock($indent, $indent, BLOCK_NOTIGHTEN_EITHER);
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
    var $_re = '(\ {0,4}([#*])\ *)(?=\S)';

    function _parse (&$input, $m) {
        list (,$prefix,$bullet) = $m;
        $indent = sprintf("\\ {%d}", strlen($prefix));
        $this->_tag = $bullet == '*' ? 'ul' : 'ol';
        $this->_pushContent(HTML::li(false,
                                     $input->parseSubBlock('', $indent)));
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
    var $_re = '(\ {0,4})([^\s!].*)([:|])\s*?\n(\s*)^(?=(\1\ +)\S)';
    //          1-------12--------23----3      4---4    5-----5

    function _parse (&$input, $m) {
        $term = TransformInline(rtrim($m[2]));
        $colon = $m[3];
        $atbreak = !empty($m[4]);
        $indent = $m[5];

        $defn = $input->parseSubBlock($indent, $indent,
                                      BLOCK_NOTIGHTEN_AFTER,
                                      $atbreak);

        if ($colon == '|') {
            // FIXME: css?
            $this->_tag = 'table';
            $this->_attr = array('border' => 1, 'cellpadding' => 4, 'cellspacing' => 1);
            
            $this->_pushContent(HTML::tr(array('valign' => 'top'),
                                         HTML::th(array('align' => 'right'), $term),
                                         HTML::td(false, $defn)));
        }
        else {
            $this->_pushContent(HTML::dt(false, $term),
                                HTML::dd(false, $defn));
        }
        
        return true;
    }
}

class Block_oldlists extends Block_list
{
    //var $_tag = 'ol', 'ul', or 'dl';
    var $_re = '((?:([*#])|;(.*):).*?)(?=\S)';
    //          1---2====2--3==3-----1
    
    function _parse (&$input, $m) {
        if (!preg_match('/[*#;]*$/A', $input->getIndent()))
            return false;

        @list(,$prefix,$bullet,$term) = $m;

        $oldindent = '[*#;](?=[#*]|;.*:.*?\S)';
        $newindent = sprintf('\\ {%d}', strlen($prefix));
        $indent = "(?:$oldindent|$newindent)";

        if ($bullet) {
            $this->_tag = $bullet == '*' ? 'ul' : 'ol';
            $item = HTML::li();
        }
        else {
            $this->_tag = 'dl';
            $item = HTML::dd();
            if (($term = trim($term)) != '')
                $this->_pushContent(HTML::dt(false, TransformInline($term)));
        }
        
        $item->pushContent($input->parseSubBlock('', $indent));
        $this->_pushContent($item);
        return true;
    }
}

class Block_pre extends Block
{
    var $_tag = 'pre';
    var $_re = '<pre>((?:.|\n)*?)(?<!~)<\/pre>\s*?\n?';
}


class Block_plugin extends Block
{
    var $_tag = 'div';
    var $_attr = array('class' => 'plugin');
    var $_re = '(<\?plugin(?:-form)?\s((?:.|\n)*?)(?<!~)\?>)\\s*?\\n?';

    function _parse (&$input, $m) {
        global $request;
        $loader = new WikiPluginLoader;
        $this->_pushContent($loader->expandPI($m[1], $request));
        return true;
    }
}

class Block_hr extends Block
{
    var $_tag = 'hr';
    var $_re = '-{4,}\s*?\n?';

    function _parse (/* &$input, $m */) {
        return true;
    }
}

class Block_heading extends Block
{
    var $_re = '(!{1,3})(.*?\S.*)\n?';
    
    function _parse (&$input, $m) {
        $this->_tag = "h" . (5 - strlen($m[1]));
        $this->_pushContent(TransformInline(trim($m[2])));
        return true;
    }
}

class Block_p extends Block
{
    var $_tag = 'p';
    var $_re = '(\S.*\n?)';

    function _parse (&$input, $m) {
        $this->_text = $m[1];
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
    var $_re = '(?=(>\ ?)\S)';
    
    function _parse (&$input, $m) {
        $indent = "(?:$m[1]|>(?=\s*?\n))";
        $this->_content = $input->parseSubBlock($indent, $indent, BLOCK_NOTIGHTEN_EITHER);
        return true;
    }
}

////////////////////////////////////////////////////////////////
//



$GLOBALS['Block_BlockTypes'] = array(new Block_oldlists,
                                     new Block_list,
                                     new Block_dl,
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

    $parser = new BlockParser($text);
    return $parser->parse();
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
