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
    return new RawXml(rtrim(AsXML($trfm->do_transform('', $lines))));
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
        //$text = preg_replace("/\s*\n/A", "", $text);

        $block = $this->_nextBlock($text);
        while ($block) {
            while ($nextBlock = $this->_nextBlock($text)) {
                if (!isa($block, "Block_blockquote"))
                    break;
                if (!isa($nextBlock, "Block_blockquote"))
                    break;
                if ($nextBlock->getDepth() >= $block->getDepth())
                    break;

                // We have a deeper block quote immediated preceding
                // a shallower block-quote.  Merge the two...
                $nextBlock->unshiftContent($block);
                $block = $nextBlock;
            }

            $content[] = $block;
            $block = $nextBlock;
        }
        return $content;
    }
    
    function _nextBlock (&$text) {
        if (preg_match('/\s*\n/A', $text, $m))
            $text = substr($text, strlen($m[0]));
        
        foreach ($this->_blockTypes as $type) {
            if (($block = $type->Match($text)))
                return $block;
        }

        return false;
    }

}

        
class Block {
    /**
     * (This should be a static member function...)
     */
    function Match (&$text) {
        return $this->_match($text);
    }
}



class TightListItem extends HtmlElement
{
    function TightListItem ($tag, $content) {
        $this->HtmlElement($tag);
        $this->pushTightContent($content);
    }
        
    function pushTightContent ($content) {
        if (!is_array($content))
            $content = array($content);
        foreach ($content as $c) {
            if (isa($c, "HtmlElement") && $c->getTag() == 'p')
                $c = $c->getContent();
            $this->pushContent($c);
        }
    }
}

        
                
            
class Block_list extends Block
{
    var $_match_re = "/([*#])\s*(?=\S)/A";

    function _match (&$text) {
        global $BlockParser;
        
        if (!(preg_match($this->_match_re, $text, $m)))
            return false;
        $li_pfx = preg_quote($m[0], '/');
        $c_pfx = sprintf("\\ {%d}", strlen($m[0]));
        $li_re = "/${li_pfx}\S.*(?:\s*\n${c_pfx}.*)*(?:\s*\n)?/A";
        $strip_re = "/^(?:${li_pfx}|${c_pfx})/m";

        $list = new HtmlElement($m[1] == '*' ? 'ul' : 'ol');

        $was_loose = false;
        $have_item = preg_match($li_re, $text, $m);
        assert($have_item);
        while ($have_item) {
            $text = substr($text, strlen($m[0]));
            $body = preg_replace($strip_re, '', $m[0]);
            
            $have_item = preg_match($li_re, $text, $m);
            $is_loose = preg_match($have_item
                                   ? "/\n\s*\n/"
                                   : "/\n\s*\n(?!\s*\$)/",
                                   $body);
            $tight = !($is_loose ||$was_loose);
            $was_loose = $is_loose;
            
            $li_content = $BlockParser->parse(rtrim($body));
            if ($tight)
                $list->pushContent(new TightListItem('li', $li_content));
            else
                $list->pushContent(new HtmlElement('li', false, $li_content));
        }
        assert($list->getContent());

        return $list;
    }
}

class Block_dl extends Block
{
    var $_re = "/([^\s!].*):\s*\n((?:\ +\S.*(?:\s*\n)?)+)/A";

    function _match (&$text) {
        
        $have_item = preg_match($this->_re, $text, $m);
        if (!$have_item)
            return false;

        global $BlockParser;
        $was_loose = false;
        $list = HTML::dl();

        while ($have_item) {
            $text = substr($text, strlen($m[0]));
            $list->pushContent(HTML::dt(rtrim($m[1])));
            $body = $this->_unindent($m[2]);

            $have_item = preg_match($this->_re, $text, $m);
            $is_loose = preg_match($have_item
                                   ? "/\n\s*\n/"
                                   : "/\n\s*\n(?!\s*\$)/",
                                   $body);
            $tight = !($is_loose ||$was_loose);
            $was_loose = $is_loose;

            $dd_content = $BlockParser->parse(rtrim($body));
            if ($tight)
                $list->pushContent(new TightListItem('dd', $dd_content));
            else
                $list->pushContent(new HtmlElement('dd', false, $dd_content));
        }
        return $list->getContent() ? $list : false;
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
    var $_depth;
    
    var $_re = "/(\ +(?=\S)).*(?:\s*\n\\1.*)*\n?/A";

    function _match (&$text) {
        if (! preg_match($this->_re, $text, $m))
            return false;
        $text = substr($text, strlen($m[0]));

        $this->_depth = strlen($m[1]);
        $pfx = preg_quote($m[1], '/');
        $body = preg_replace("/^$pfx/m", "", $m[0]);
        global $BlockParser;
        return HTML::blockquote(false, $BlockParser->parse($body));
    }

    function getDepth () {
        return $this->_depth;
    }
}

class BlockBlock extends Block
{
    function BlockBlock ($begin_re, $end_re) {
        $this->_re = ( "/"
                       . "($begin_re"
                       . "((?:.|\n)*?)"
                       . "$end_re)"
                       . "\s*?\n?"
                       . "/Ai" );
    }
}
        
class Block_pre extends BlockBlock
{
    function Block_pre () {
        $this->BlockBlock("<pre>", "(?<!~)<\/pre>");
    }

    function _match (&$text) {
        if (!preg_match($this->_re, $text, $m))
            return false;
        $text = substr($text, strlen($m[0]));

        return HTML::pre(false, TransformInline($m[2]));
    }

}

class Block_plugin extends BlockBlock
{
    function Block_plugin () {
        $this->BlockBlock("<\?plugin(?:-form)?\s", "\?>");
    }


    function _match (&$text) {
        if (!preg_match($this->_re, $text, $m))
            return false;
        $text = substr($text, strlen($m[0]));

        global $request;
        $loader = new WikiPluginLoader;

        return HTML::div(array('class' => 'plugin'),
                         $loader->expandPI($m[1], $request));
    }
}

class Block_hr extends Block
{
    var $_re = "/-{4,}\s*?\n?/A";
    
    function _match (&$text) {
        if (!preg_match($this->_re, $text, $m))
            return false;
        $text = substr($text, strlen($m[0]));
        return HTML::hr();
    }
}

class Block_heading extends Block
{
    var $_re = "/(!{1,3}).*?(\S.*)\n?/A";
    
    function _match (&$text) {
        if (!preg_match($this->_re, $text, $m))
            return false;
        $text = substr($text, strlen($m[0]));

        $tag = "h" . (5 - strlen($m[1]));
        return new HtmlElement($tag, false, rtrim($m[2]));
    }
}

    
class Block_p extends Block
{
    var $_re = "/(\S.*(?:\n(?!\s|[*#!]|\<\?|----|<pre>).+)*)\n?/A";
        
    function _match (&$text) {
        if (!preg_match($this->_re, $text, $m))
            return false;
        $text = substr($text, strlen($m[0]));
        
        return HTML::p(false, TransformInline($m[1]));
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


function NewTransform ($text) {
    global $BlockParser;

    // Expand leading tabs.
    // FIXME: do this better.
    $text = preg_replace('/^\ *[^\ \S\n][^\S\n]*/me', "str_repeat(' ', strlen('\\0'))", $text);
    assert(!preg_match('/^\ *\t/', $text));

    return $BlockParser->parse($text);
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
