<?php rcs_id('$Id: InlineParser.php,v 1.1 2002-01-29 05:06:31 dairiki Exp $');
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

//FIXME: intubate ESCAPE_CHAR into BlockParser.php.
define('ESCAPE_CHAR', '~');

class PatternSet
{
    var $_user_data = array();
    var $_regexps = array();

    function PatternSet ($pattern_hash = false) {
        if (is_array($pattern_hash))
            $this->addPattern($pattern_hash);
    }

    function addPattern ($regexp, $user_data = false) {
        if (is_array($regexp)) {
            foreach ($regexp as $key => $val)
                $this->addPattern($key, $val);
        }
        
        $this->_user_data[] = $user_data;
        $re = pcre_fix_posix_classes($regexp);
        $this->_regexps[] = "($re)";
        $this->_re = '(?<!' . ESCAPE_CHAR . ')(?:' . join('|', $this->_regexps) . ')';

        if (preg_match('/(?<!\\\\) \\( (?!\\?)/x', $re))
            trigger_error("Warning: no () groups allowed in PatternSet regexps!: $re",
                          E_USER_WARNING);
    }
    
    function nextMatch ($text, $limit, $prevMatch = false) {
        if ($prevMatch) {
            if ( ($m = $this->nextMatchAtSamePosition($prevMatch)) )
                return $m;
            $skip = strlen($prevMatch->prematch) + 1;
        }
        else
            $skip = 0;
        
        if ($limit < $skip)
            return false;

        $pat = sprintf('/(.{%d,%d}?)%s/Asx', $skip, $limit, $this->_re);
        if (!preg_match($pat, $text, $m))
            return false;
        
        $match = new PatternSet_match;
        $match->postmatch = substr($text, strlen(array_shift($m)));
        $match->prematch = array_shift($m);
        $match->match_ind = 0;
        
        foreach ($this->_user_data as $user_data) {
            if ( ($match->match = array_shift($m)) ) {
                $match->user_data = $user_data;
                return $match;
            }
            $match->match_ind++;
        }

        assert(false);
        return false;
    }

    function nextMatchAtSamePosition ($match) {
        $text = $match->match . $match->postmatch;
        while (++$match->match_ind < count($this->_regexps)) {
            $re = $this->_regexps[$match->match_ind];
            if (preg_match("/$re/Ax", $text, $m)) {
                $match->match = $m[0];
                $match->postmatch = substr($text, strlen($m[0]));
                $match->user_data = $this->_user_data[$match->match_ind];
                return $match;
            }
        }
        return false;
    }
}

class PatternSet_match {
    var $prematch;
    var $match;
    var $postmatch;
    var $user_data;
    var $match_ind;
}


class InlineTransformer
{
    function InlineTransformer () {
        $this->_start_regexps = new PatternSet;

        foreach (array('escaped_escape', 'bracketlink', 'url',
                       'interwiki', 'wikiword', 'linebreak') as $mtype) {
            $class = "Markup_$mtype";
            $markup = new $class;
            $this->_start_regexps->addPattern($markup->getMatchRegexp(), $markup);
        }

        foreach (array('old_emphasis', 'nestled_emphasis', 'html_emphasis') as $mtype) {
            $class = "Markup_$mtype";
            $markup = new $class;
            $this->_start_regexps->addPattern($markup->getStartRegexp(), $markup);
        }
    }
    
    function parse (&$text, $end_re = '$') {
        //static $depth;
        $start_regexps = $this->_start_regexps;
        
        $input = $text;
        $output = new XmlContent;
        
        if (!preg_match("/(.*?)$end_re/Axs", $input, $end_m))
            return false;       // end pattern not found. parse fails.
        $endpos = strlen($end_m[1]);

        $prevMatch = false;
        while ( ($m = $start_regexps->nextMatch($input, $endpos - 1, $prevMatch)) ) {

            $markup = $m->user_data;

            // FIXME: refactor if this nextMatch stuff works.
            if (!isa($markup, 'BalancedMarkup')) {
                // Succesfully matched markup.
                $body = true;
            }
            else {
                $prevMatch = $m;
                $end_regexp = $markup->getEndRegexp($m->match);
                if ( !($body = $this->parse($m->postmatch, $end_regexp)) ) {
                    // Couldn't match balanced expression.
                    // Ignore and look for next matching start regexp.
                    continue;
                }
            }

            // Matched entire pattern. Eat input...
            $output->pushContent(str_replace(ESCAPE_CHAR, '', $m->prematch),
                                 $markup->markup($m->match, $body));
            $input = $m->postmatch;
            $prevMatch = false;

            // Recompute end position.
            if (!preg_match("/(.*?)$end_re/Axs", $input, $end_m))
                return false;       // end pattern not found. parse fails.
            $endpos = strlen($end_m[1]);
        }
        
        // Done.  No start pattern found before endpattern.
        $output->pushContent(str_replace(ESCAPE_CHAR, '', $end_m[1]));
        $text = substr($input, strlen($end_m[0]));
        return $output;
    }
}

class SimpleMarkup
{
    var $_match_regexp;

    function getMatchRegexp () {
        return $this->_match_regexp;
    }
    
    function markup ($match /*, $body */) {
        trigger_error("pure virtual", E_USER_ERROR);
    }
}

class BalancedMarkup
{
    var $_start_regexp;

    function getStartRegexp () {
        return $this->_start_regexp;
    }
    
    function getEndRegexp ($match) {
        return $this->_end_regexp;
    }
    
    function markup ($match, $body) {
        trigger_error("pure virtual", E_USER_ERROR);
    }
}

class Markup_escaped_escape  extends SimpleMarkup
{
    function getMatchRegexp () {
        return ESCAPE_CHAR . ESCAPE_CHAR;
    }
    
    function markup () {
        return ESCAPE_CHAR;
    }
}

class Markup_bracketlink  extends SimpleMarkup
{
    var $_match_regexp = "\\[ .*?\S.*? \\]";
    
    function markup ($match) {
        $link = LinkBracketLink($match);
        assert($link->isInlineElement());
        return $link;
    }
}

class Markup_url extends SimpleMarkup
{
    // FIXME: why no brackets?
    //var $_start_re = "(?<![~[:alnum:]]) (?:$AllowedProtocols) : [^\s<>\[\]\"'()]+ (?<![,.?])";

    function getMatchRegexp () {
        global $AllowedProtocols;
        return "(?<![[:alnum:]]) (?:$AllowedProtocols) : \S+ (?<![ ,.?; \] \) \" ' ])";
    }
    
    function markup ($match) {
        return LinkURL($match);
    }
}


class Markup_interwiki extends SimpleMarkup
{
    function getMatchRegexp () {
        global $InterWikiLinkRegexp;
        return "(?<! [[:alnum:]]) $InterWikiLinkRegexp : \S+ (?<![ ,.?; \] \) \" \' ])";
    }

    function markup ($match) {
        return LinkInterWikiLink($match);
    }
}

class Markup_wikiword extends SimpleMarkup
{
    function getMatchRegexp () {
        global $WikiNameRegexp;
        return " $WikiNameRegexp";
    }
        
    function markup ($match) {
        return LinkWikiWord($match);
    }
}

class Markup_linebreak extends SimpleMarkup
{
    var $_match_regexp = "(?: (?<! %) %%% (?! %) | <br> )";

    function markup () {
        return HTML::br();
    }
}

class Markup_old_emphasis  extends BalancedMarkup
{
    var $_start_regexp = "''|__";

    function getEndRegexp ($match) {
        return $match;
    }
    
    function markup ($match, $body) {
        $tag = $match == "''" ? 'em' : 'strong';
        return new HtmlElement($tag, $body);
    }
}

class Markup_nestled_emphasis extends BalancedMarkup
{
    var $_start_regexp = "(?<! [[:alnum:]] ) [*_=] (?=[[:alnum:]])";

    function getEndRegexp ($match) {
        return "(?<= [[:alnum:]]) \\$match (?![[:alnum:]])";
    }
    
    function markup ($match, $body) {
        if ($match == '*')
            $tag = 'b';
        elseif ($match == '_')
            $tag = 'i';
        else
            $tag = 'tt';

        return new HtmlElement($tag, $body);
    }
}

class Markup_html_emphasis extends BalancedMarkup
{
    var $_start_regexp = "<(?: b|big|i|small|tt|
                               abbr|acronym|cite|code|dfn|kbd|samp|strong|var|
                               sup|sub )>";

    function getEndRegexp ($match) {
        return "<\\/" . substr($match, 1);
    }
    
    function markup ($match, $body) {
        $tag = substr($match, 1, -1);
        return new HtmlElement($tag, $body);
    }
}

// FIXME: Do away with magic phpwiki forms.  (Maybe phpwiki: links too?)
// FIXME: Do away with plugin-links.  They seem not to be used.
//Plugin link


function TransformInline($text) {
    static $trfm;
    if (empty($trfm))
        $trfm = new InlineTransformer;
    return $trfm->parse($text);
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
