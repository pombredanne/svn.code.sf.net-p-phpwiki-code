<?php rcs_id('$Id');
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
    $lines = preg_split('/[ \t\r]*\n/', trim($text));

    $trfm = new InlineTransform;
    return $trfm->do_transform('', $lines);
}

/**
 * FIXME:
 *  Still to do:
 *    headings,
 *    hr
 *    tables
 *    simple li's (vs compound li's) & (dd's).
 */
class BlockParser {
    function BlockParser ($block_types) {
        $this->_blockTypes = array();
        foreach ($block_types as $type)
          $this->registerBlockType($type);
    }
    
    function registerBlockType ($class) {
        // FIXME: this is hackish.
        // It's because there seems to be no way to
        // call static members of $class.
        $prototype = new $class (false);
        $this->_blockTypes[] = $prototype;
    }
    
    function parse ($text) {
        $content = array();

        // strip leading blank lines
        $text = preg_replace("/\s*\n/A", "", $text);

        while ($text) {
            $parsed = $this->_parseOne($text);
            assert (!is_array($parsed));
            $content[] = $parsed;

            // strip blank lines
            $text = preg_replace("/\s*\n/A", "", $text);
        }
        return $content;
    }

    function _parseOne (&$text) {
        $block = $this->_grokBlockType($text); // determine block type from first line of text
        assert($block);
        $btext = $block->extractBlock($text);

        while ($nextBlock = $this->_grokBlockType($text)) {
            $next_text = $nextBlock->extractBlock($text);
            
            if (! $nextBlock->matches($btext . $next_text)) {
                // Can't combine blocks.
                $text = $next_text . $text;
                if ($nextBlock->getDepth() < $block->getDepth()) {
                    //if (!isa($block, "Block_blockquote")) {
                    if ($block->_tag != 'blockquote') {
                        // FIXME: move this.
                        $block = new Block_blockquote;
                        assert(preg_match('/(?:\s*\n)?( *)(?=\S)/', $btext, $m));
                        $block->_init($m);
                    }
                }
                break;
            }
            assert ($nextBlock->getDepth() <= $block->getDepth());
            $block = $nextBlock;
            $btext .= $next_text;
        }

        if (0) {
            echo "BLOCK $block->_tag:<pre>\n";
            echo htmlspecialchars($btext);
            echo "\n</pre><br />\n";
        }
        
        return $block->parse($btext);
    }

    function _grokBlockType ($text) {
        foreach ($this->_blockTypes as $type) {
            if (($block = $type->Match($text)))
                return $block;
        }
        return false;
    }

}

        
class Block {
    var $_match_re;
    var $_prefix_re;
    var $_block_re;
    var $_depth = 0;
    var $_tag;
    var $_attr = false;

    /*
    function Block ($match = false) {
        if ($match)
            $this->_init($match);
    }
    */

    function _init ($match) {
        $qprefix = preg_quote($match[1], '/');
        $this->_prefix_re = $qprefix;
        $this->_block_re = "(?:(?:\s*\n)?${qprefix}.*\n?)+";
        $this->_depth = strlen($match[1]);
    }

    /**
     * (This should be a static member function...)
     */
    function Match ($text) {
        if (! preg_match($this->_match_re, $text, $match))
            return false;

        $block = $this;        // Copy self.
        $block->_init($match);
        return $block;
    }
        
    function extractBlock (&$text) {
        $block_re = &$this->_block_re;
        assert(preg_match("/$block_re/Ami", $text, $m));
        $text = substr($text, strlen($m[0]));
        return $m[0];
    }

    function getDepth () {
        return $this->_depth;
    }

    function matches ($text) {
        $block_re = &$this->_block_re;
        return preg_match("/$block_re\$/Ai", $text);
    }
    
    function parse ($text) {
        assert ($this->matches($text));

        // Strip block prefix from $text.
        $prefix = &$this->_prefix_re;
        $text = preg_replace("/^$prefix/m", "", $text);
        

        global $BlockParser;
        return $this->wrap($BlockParser->parse($text));
    }

    // FIXME: rename
    function wrap($content) {
        // DEBUGGING:
        //if (is_array($content)) $content = join('', $content);
        //$content = preg_replace("/(?<!\n)$/", "\n", $content);
        //return "$this->_tag:\n" . preg_replace("/^(?!\$)/m", "  ", $content);
        
        return new HtmlElement($this->_tag, $this->_attr, $content);
    }
    
}

class ListBlock extends Block
{
    var $_match_re = "/(?:\s*\n)?( *[*#] *(?=\S))/A";
    
    /**
     * Get a regexp which matches the line prefix for
     * any <li> of the same type (ul/ol) and depth.
     */
    function makeLiPrefixRegexp ($match) {
        return preg_quote($match[1], '/');
    }
    
    /**
     * Get a regexp which matches the line prefix for
     * any <li> continuation lines.
     */
    function makeContPrefixRegexp ($match) {
        return sprintf(" {%d}", strlen($match[1]));
    }
}

class Block_list extends ListBlock
{
    function _init ($match) {
        $this->_tag = $this->grokListType($match);
        
        $liprefix = $this->makeLiPrefixRegexp($match);
        $cprefix = $this->makeContPrefixRegexp($match);

        preg_match('/^ */', $match[1], $m);
        $this->_depth = strlen($m[0]);
        
        $this->_prefix_re = false; // don't strip any prefix
        $this->_block_re = ( "(?:\s*\n)?"          // leading blank lines.
                             . "(?:"
                             . "${liprefix}.*\n?" // first line
                             . "(?:(?:\s*\n)?${cprefix}.*\n?)*" // continuation lines
                             . ")+" );
        
    }

    function grokListType ($match) {
        return preg_match("/#\s*\$/", $match[0]) ? 'ol' : 'ul';
    }

    function parse ($text) {
        global $ListParser;
        return $this->wrap($ListParser->parse($text));
    }
}


class Block_li extends ListBlock
{
    var $_tag = 'li';

    function _init ($match) {
        $liprefix = $this->makeLiPrefixRegexp($match);
        $cprefix = $this->makeContPrefixRegexp($match);

        $this->_prefix_re = "(?:${cprefix}|${liprefix})";
        $this->_block_re = ( "${liprefix}.*\n?" // first line
                             . "(?:(?:\s*\n)?${cprefix}.*\n?)*" ); // continuation lines
    }
}

class Block_dl extends Block
{
    var $_tag = 'dl';
    var $_match_re = "/(?:\s*\n)?[^\s*#].*:\s*\n +\S/A";
    var $_prefix_re = false;    // no prefix to strip
    var $_block_re = "(?:(?:\s*\n)?[^\s*#].*:(?:\s*\n +\S.*)+\n?)+";

    function _init () {
    }

    function parse ($text) {
        $dt = new Block_dt;
        $dd = new Block_dd;
        
        $content = array();
        while ($block = $dt->Match($text)) {
            $btext = $block->extractBlock($text);
            $content[] = $block->parse($btext);
            
            $block = $dd->Match($text);
            assert($block);
            $btext = $block->extractBlock($text);
            $content[] = $block->parse($btext);
        }
        assert(preg_match("/\s*\$/A", $text));
        return $this->wrap($content);
    }
}

class Block_dt extends Block
{
    var $_tag = 'dt';
    var $_match_re = "/(?:\s*\n)?[^\s*#].*:\s*?\n/A";
    var $_block_re = "(?:\s*\n)?[^\s*#].*:\s*\n";

    function _init () {
    }
    
    function parse ($text) {
        
        assert(preg_match("/(\S.*?)\s*:/A", $text, $m));
        return $this->wrap($m[1]);
    }
}


class Block_dd extends Block
{
    var $_tag = 'dd';
    var $_match_re = "/(?:(?:\s*\n)? +\S.*\n?)+/A";
    var $_block_re = "(?:(?:\s*\n)? +\S.*\n?)+";

    function _init ($match) {
        $indent = $this->_getIndent($match[0]);
        $this->_prefix_re =  sprintf(" {%d}", $indent);
    }

    function _getIndent ($body) {
        assert(preg_match_all("/^ +(?=\S)/m", $body, $m));
        $indent = strlen($m[0][0]);
        foreach ($m[0] as $pfx)
            $indent = min($indent, strlen($pfx));
        return $indent;
    }
}


class Block_blockquote extends Block
{
    var $_tag = 'blockquote';
    var $_match_re = "/(?:\s*\n)?( +(?=\S))/A";
}

class BlockBlock extends Block
{
    var $_prefix_re = false;    // no prefix to strip

    function BlockBlock ($begin_re, $end_re) {
        $this->_begin_re = $begin_re;
        $this->_end_re = $end_re;
        
        $this->_block_re = ( "(?:\s*\n)?"
                             . $begin_re
                             . "(?:.|\n)*?"
                             . $end_re
                             . "\s*?(?=\n|\S|$)" );
        $this->_match_re = "/" . $this->_block_re . "/Ai";
    }

    function _init () {
    }

    function _strip ($text) {
        $beg = $this->_begin_re;
        $end = $this->_end_re;
        
        $text = preg_replace("/.*?${beg}/Asi", "", $text);
        $text = preg_replace("/${end}.*?$/si", "", $text);
        return $text;
    }

    function parse ($text) {
        // FIXME: parse inline markup.
        return $this->wrap(TransformInline($this->_strip($text)));
    }
}
        
    
    
class Block_pre extends BlockBlock
{
    var $_tag = 'pre';

    function Block_pre () {
        $this->BlockBlock("<pre>", "(?<!~)<\/pre>");
    }
}

class Block_plugin extends BlockBlock
{
    var $_tag = 'div';
    
    function Block_plugin () {
        $this->BlockBlock("<\?plugin(?:-form)?\s", "\?>");
        $this->_attr = array('class' => 'plugin');
    }

    function parse ($text) {
        global $request;
        $loader = new WikiPluginLoader;
        return $this->wrap($loader->expandPI($text, $request));
    }
}

class Block_p extends Block
{
    var $_tag = 'p';
    var $_match_re = "/(?=\S)/A";
    var $_prefix_re = false;    // no prefix to strip
    var $_block_re = "\S.*\n?(?:^(?!\<\?)[^\s*#].*\n?)*";
        
    function _init ($match) {
    }

    function parse ($text) {
        // FIXME: parse inline markup.
        return $this->wrap(TransformInline($text));
    }
}

$GLOBALS['BlockParser'] = new BlockParser(array('Block_dl',
                                                'Block_list',
                                                'Block_blockquote',
                                                'Block_pre',
                                                'Block_plugin',
                                                'Block_p'));

$GLOBALS['ListParser'] = new BlockParser(array('Block_li'));


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
