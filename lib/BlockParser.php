<?php rcs_id('$Id');
require_once('lib/HtmlElement.php');

//FIXME:
require_once('lib/transform.php');

function d ($x) 
{
    echo nl2br(htmlspecialchars("$x\n"));
    flush();
}


    
     
     
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
    
    function parse ($text, $tighten = BLOCK_NEVER_TIGHTEN) {
        $content = array();

        $block = $this->_nextBlock($text);
        $tight = $tighten != BLOCK_NEVER_TIGHTEN;

        while ($block !== false) {
            if (!$block) {
                if (($tighten & BLOCK_NOTIGHTEN_AFTER) != 0)
                    $tight = false;
                $block = $this->_nextBlock($text);
                continue;
            }
                
            while ($nextBlock = $this->_nextBlock($text)) {
                // Attempt to merge current with following block.
                if (! $nextBlock->mergeWithPrecedingBlock($block))
                    break;      // can't merge
                $block = $nextBlock;
            }

            if ($nextBlock === '' && ($tighten & BLOCK_NOTIGHTEN_BEFORE) != 0)
                $tight = false;
            
            $out = $block->finish($tight);
            if (is_array($out))
                array_splice($content, count($content), 0, $out);
            else
                $content[] = $out;

            $block = $nextBlock;
            $tight = $tighten != BLOCK_NEVER_TIGHTEN;
        }
        return $content;
    }

    function _nextBlock (&$text) {
        if (!$text)
            return false;

        if (preg_match('/\s*\n/A', $text, $m)) {
            // A paragraph break: one or more blank lines.
            $text = substr($text, strlen($m[0]));
            return '';          // (empty but !== false).
        }
        
        foreach ($this->_blockTypes as $type) {
            if (($block = $type->Match($text))) {
                return $block;
            }
        }

        // We should never get here.
        list ($line1, $line2) = explode("\n", $text);
        trigger_error("Couldn't match block:\n    $line1\n    $line2",
                      E_USER_ERROR);
    }
}

class Block extends HtmlElement {
    /**
     * (This should be a static member function...)
     */
    function Match (&$text) {
        if (!preg_match($this->_re, $text, $m))
            return false;
        
        $block = $this;
        $block->_parse($m, $text);
        return $block;
    }

    function _parse ($m, &$text) {
        $this->_init_from_match($m);
        $text = substr($text, strlen($m[0]));
    }
    
    function mergeWithPrecedingBlock ($precedingBlock) {
        return false;
    }

    function finish ($tighten) {
        return $this;
    }
}
            
class Block_list extends Block
{
    var $_re = '/\ {0,4}([*#])\ *(?=\S)/A';

    function _parse ($m, &$text) {
        global $BlockParser;
        
        $li_pfx = preg_quote($m[0], '/');
        $c_pfx = sprintf("\\ {%d}", strlen($m[0]));
        $li_re = "/${li_pfx}\S.*(?:\s*\n${c_pfx}.*\S.*)*\n?/A";
        $strip_re = "/^(?:${li_pfx}|${c_pfx})/m";

        $this->_init($m[1] == '*' ? 'ul' : 'ol');

        $tight = BLOCK_NOTIGHTEN_AFTER;
        $length = 0;
        while (preg_match($li_re, $text, $m)) {
            $text = substr($text, strlen($m[0]));
            $body = preg_replace($strip_re, '', $m[0]);
            
            $this->pushContent(HTML::li(false,
                                        $BlockParser->parse(rtrim($body), $tight)));
            if ($pbreak = preg_match("/\s*\n/A", $text, $m2)) {
                $text = substr($text, strlen($m2[0]));
                $tight = BLOCK_NEVER_TIGHTEN;
            }
            else
                $tight = BLOCK_NOTIGHTEN_AFTER;
        }
        assert(!$this->isEmpty());
    }
}

class Block_dl extends Block
{
    var $_re = '/ [^\s!].*:	# term (<dt>)
                  \s*\n		# zero of more blank lines
                  \ +\S		# indented non-blank line
                /Ax';

    var $_dtdd_re = '/ ([^\s!].*):	# term (<dt>)
                       [^\S\n]*\n	# rest of first line.
                       ((?:
                          (?:\s*\n)?	# zero of more blank lines
                          \ +\S.*	# indented non-blank line
                       )+)\n?
                     /Ax';

    function Block_dl () {
        $this->_init('dl');
    }
    
    function _parse ($m, &$text) {
        global $BlockParser;
        
        while(preg_match($this->_dtdd_re, $text, $m)) {
            $text = substr($text, strlen($m[0]));
            $dt = HTML::dt(rtrim($m[1]));
            $body = $this->_unindent($m[2]);
            
            $dd = HTML::dd(false, $BlockParser->parse($body, BLOCK_NOTIGHTEN_AFTER));

            if (preg_match("/\s*\n/A", $text, $m2)) {
                $text = substr($text, strlen($m2[0]));
                // FIXME: use something else for space here?
                $dd->pushContent(HTML::p(array('class' => 'empty')));
            }

            $this->pushContent($dt, $dd);
        }
        assert(!$this->isEmpty());
    }

    function _unindent ($body) {
        assert(preg_match_all("/^ +(?=\S)/m", $body, $m));
        $indent = strlen($m[0][0]);
        foreach ($m[0] as $pfx)
            $indent = min($indent, strlen($pfx));

        $ind_re = sprintf("\\ {%d}", $indent);
        return preg_replace("/^{$ind_re}/m", "", $body);
    }
}


class Block_blockquote extends Block
{
    //var $_depth;
    
    var $_re = '/ (\ +(?=\S)).*	# indented non-blank line
                  (?:
                     \s*\n	# zero or more blank lines
                     \1.*	# lines with same or greater indent
                  )*\n?
                /Ax';

    function Block_blockquote () {
        $this->_init('blockquote');
    }
    
    function _init_from_match ($m) {
        $this->_depth = strlen($m[1]);
        $pfx = preg_quote($m[1], '/');
        $body = preg_replace("/^$pfx/m", "", $m[0]);
        global $BlockParser;
        $this->pushContent($BlockParser->parse($body, BLOCK_NOTIGHTEN_EITHER));
    }

    function mergeWithPrecedingBlock ($precedingBlock) {
        if (!isa($precedingBlock, "Block_blockquote"))
            return false;       // can only merge with another blockquote.
        if ($precedingBlock->_depth <= $this->_depth)
            return false;       // can only merge with deeper block

        $this->unshiftContent($precedingBlock);
        return true;
    }
}

class BlockBlock extends Block
{
    function BlockBlock ($begin_re, $end_re) {
        $this->_re = "/ (
                          $begin_re
                          ( (?:.|\\n)*? )
                          $end_re
                        )
                        [^\\S\\n]*\\n?
                      /Aix";
    }
}
        
class Block_pre extends BlockBlock
{
    function Block_pre () {
        $this->_init('pre');
        $this->BlockBlock("<pre>", "(?<!~)<\/pre>");
    }

    function _init_from_match ($m) {
        $this->pushContent(TransformInline($m[2]));
    }
}

class Block_plugin extends BlockBlock
{
    function Block_plugin () {
        $this->_init('div', array('class' => 'plugin'));
        $this->BlockBlock("<\?plugin(?:-form)?\s", "\?>");
    }


    function _init_from_match ($m) {
        global $request;
        $loader = new WikiPluginLoader;
        $this->pushContent($loader->expandPI($m[1], $request));
    }
}

class Block_hr extends Block
{
    var $_re = "/-{4,}[^\S\n]*\n?/A";

    function Block_hr () {
        $this->_init('hr');
    }
        
    function _init_from_match ($m) {
        //return true;
    }
}

class Block_heading extends Block
{
    var $_re = "/(!{1,3}).*?(\S.*)\n?/A";
    
    function _init_from_match ($m) {
        $tag = "h" . (5 - strlen($m[1]));
        $this->_init($tag);
        $this->pushContent(TransformInline(rtrim($m[2])));
    }
}

class Block_p extends Block
{
    var $_re = "/\S.*\n?/A";
    //var $_text = '';

    function Block_p () {
        $this->_init('p');
    }
    
    function _init_from_match ($m) {
        $this->_text = $m[0];
    }

    function mergeWithPrecedingBlock ($precedingBlock) {
        if (!isa($precedingBlock, 'Block_p'))
            return false;
        $this->_text = $precedingBlock->_text . $this->_text;
        return true;
    }
            
    function finish ($tighten) {
        $content = TransformInline(trim($this->_text));
        if ($tighten)
            return $content;
        else {
            $this->pushContent($content);
            return $this;
        }
    }
}

$GLOBALS['BlockParser'] = new BlockParser(array('Block_dl',
                                                'Block_list',
                                                'Block_blockquote',
                                                'Block_heading',
                                                'Block_hr',
                                                'Block_pre',
                                                'Block_plugin',
                                                'Block_p'));


// FIXME: This is temporary, too...
function NewTransform ($text) {
    global $BlockParser;

    //set_time_limit(2);
    
    // Expand leading tabs.
    // FIXME: do this better.
    $text = preg_replace('/^\ *[^\ \S\n][^\S\n]*/me', "str_repeat(' ', strlen('\\0'))", $text);
    assert(!preg_match('/^\ *\t/', $text));

    return $BlockParser->parse($text);
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
