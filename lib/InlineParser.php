<?php
/**
 * Copyright © 2002 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright © 2004-2010 Reini Urban
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

/**
 * This is the code which deals with the inline part of the
 * wiki-markup.
 *
 * @package Markup
 * @author Geoffrey T. Dairiki, Reini Urban
 */

/**
 * This is the character used in wiki markup to escape characters with
 * special meaning.
 */
define('ESCAPE_CHAR', '~');

require_once 'lib/CachedMarkup.php';
require_once 'lib/stdlib.php';

function WikiEscape($text)
{
    return str_replace('#', ESCAPE_CHAR . '#', $text);
}

function UnWikiEscape($text)
{
    return preg_replace('/' . ESCAPE_CHAR . '(.)/', '\1', $text);
}

/**
 * Return type from RegexpSet::match and RegexpSet::nextMatch.
 *
 * @see RegexpSet
 */
class RegexpSet_match
{
    /**
     * The text leading up the the next match.
     */
    public $prematch;
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
class RegexpSet
{
    /**
     * @param array $regexps A list of regular expressions.  The
     * regular expressions should not include any sub-pattern groups
     * "(...)".  (Anonymous groups, like "(?:...)", as well as
     * look-ahead and look-behind assertions are okay.)
     */
    public function __construct($regexps)
    {
        assert($regexps);
        $this->_regexps = array_unique($regexps);
    }

    /**
     * Search text for the next matching regexp from the Regexp Set.
     *
     * @param string $text The text to search.
     *
     * @return RegexpSet_match A RegexpSet_match object, or false if no match.
     */
    public function match($text)
    {
        return $this->_match($text, $this->_regexps, '*?');
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
     *
     * @param RegexpSet_match $prevMatch A RegexpSet_match object.
     * $prevMatch should be a match object obtained by a previous
     * match upon the same value of $text.
     *
     * @return RegexpSet_match A RegexpSet_match object, or false if no match.
     */
    public function nextMatch($text, $prevMatch)
    {
        // Try to find match at same position.
        $pos = strlen($prevMatch->prematch);
        $regexps = array_slice($this->_regexps, $prevMatch->regexp_ind + 1);
        if ($regexps) {
            $repeat = sprintf('{%d}', $pos);
            if (($match = $this->_match($text, $regexps, $repeat))) {
                $match->regexp_ind += $prevMatch->regexp_ind + 1;
                return $match;
            }
        }

        // Failed.  Look for match after current position.
        $repeat = sprintf('{%d,}?', $pos + 1);
        return $this->_match($text, $this->_regexps, $repeat);
    }

    // Syntax: http://www.pcre.org/pcre.txt
    //   x - EXTENDED, ignore whitespace
    //   s - DOTALL
    //   A - ANCHORED
    //   S - STUDY
    private function _match($text, $regexps, $repeat)
    {
        $match = new RegexpSet_match();

        // Optimization: if the matches are only "$" and another, then omit "$"
        assert(!empty($repeat));
        assert(!empty($regexps));
        // We could do much better, if we would know the matching markup for the
        // longest regexp match:
        $hugepat = "/ ( . $repeat ) ( (" . join(')|(', $regexps) . ") ) /Asx";
        // Proposed premature optimization 1:
        //$hugepat= "/ ( . $repeat ) ( (" . join(')|(', array_values($matched)) . ") ) /Asx";
        if (!preg_match($hugepat, $text, $m)) {
            return false;
        }
        // Proposed premature optimization 1:
        //$match->regexp_ind = $matched_ind[count($m) - 4];
        $match->regexp_ind = count($m) - 4;

        $match->postmatch = substr($text, strlen($m[0]));
        $match->prematch = $m[1];
        $match->match = $m[2];

        return $match;
    }
}

/**
 * A simple markup rule (i.e. terminal token).
 *
 * These are defined by a regexp.
 *
 * When a match is found for the regexp, the matching text is replaced.
 * The replacement content is obtained by calling the SimpleMarkup::markup method.
 */
abstract class SimpleMarkup
{
    public $_match_regexp;

    /** Get regexp.
     *
     * @return string Regexp which matches this token.
     */
    public function getMatchRegexp()
    {
        return $this->_match_regexp;
    }

    /** Markup matching text.
     *
     * @param string $match The text which matched the regexp
     * (obtained from getMatchRegexp).
     *
     * @return mixed The expansion of the matched text.
     */
    abstract public function markup($match /*, $body */);
}

/**
 * A balanced markup rule.
 *
 * These are defined by a start regexp, and an end regexp.
 */
abstract class BalancedMarkup
{
    /** Get the starting regexp for this rule.
     *
     * @return string The starting regexp.
     */
    abstract public function getStartRegexp();

    /** Get the ending regexp for this rule.
     *
     * @param string $match The text which matched the starting regexp.
     *
     * @return string The ending regexp.
     */
    abstract public function getEndRegexp($match);

    /** Get expansion for matching input.
     *
     * @param string $match The text which matched the starting regexp.
     *
     * @param mixed $body Transformed text found between the starting
     * and ending regexps.
     *
     * @return mixed The expansion of the matched text.
     */
    abstract public function markup($match, $body);
}

class Markup_escape extends SimpleMarkup
{
    public function getMatchRegexp()
    {
        return ESCAPE_CHAR . '(?: [[:alnum:]]+ | .)';
    }

    public function markup($match)
    {
        assert(strlen($match) >= 2);
        return substr($match, 1);
    }
}

/**
 * [image.jpg size=50% border=5], [image.jpg size=50x30]
 * Support for the following attributes: see stdlib.php:LinkImage()
 *   size=<percent>%, size=<width>x<height>
 *   border=n, align=\w+, hspace=n, vspace=n
 *   width=n, height=n
 *   title, lang, id, alt
 */
function isImageLink($link)
{
    if (!$link) {
        return false;
    }
    assert(defined('INLINE_IMAGES'));
    return preg_match("/\\.(" . INLINE_IMAGES . ")$/i", $link)
        or preg_match("/\\.(" . INLINE_IMAGES . ")\s+(size|border|align|hspace|vspace|type|data|width|height|title|lang|id|alt)=/i", $link);
}

function LinkBracketLink($bracketlink)
{

    // $bracketlink will start and end with brackets; in between will
    // be either a page name, a URL or both separated by a pipe.

    $wikicreolesyntax = false;

    if (string_starts_with($bracketlink, "[[") or string_starts_with($bracketlink, "#[[")) {
        $wikicreolesyntax = true;
        $bracketlink = str_replace("[[", "[", $bracketlink);
        $bracketlink = str_replace("]]", "]", $bracketlink);
    }

    // Strip brackets and leading space
    // bug#1904088  Some brackets links on 2 lines cause the parser to crash
    preg_match(
        '/(\#?) \[\s* (?: (.*?) \s* (?<!' . ESCAPE_CHAR . ')(\|) )? \s* (.+?) \s*\]/x',
        str_replace("\n", " ", $bracketlink),
        $matches
    );
    if (count($matches) < 4) {
        return HTML::span(
            array('class' => 'error'),
            _("Invalid [] syntax ignored") . _(": ") . $bracketlink
        );
    }
    list(, $hash, $label, $bar, $rawlink) = $matches;

    if ($wikicreolesyntax and $label) {
        $temp = $label;
        $label = $rawlink;
        $rawlink = $temp;
    }

    // Mediawiki compatibility: allow "Image:" and "File:"
    // as synonyms of "Upload:"
    // Allow "upload:", "image:" and "file:" also
    // Remove spaces before and after ":", if any
    if (string_starts_with($rawlink, "Upload")) {
        $rawlink = preg_replace("/^Upload\\s*:\\s*/", "Upload:", $rawlink);
    } elseif (string_starts_with($rawlink, "upload")) {
        $rawlink = preg_replace("/^upload\\s*:\\s*/", "Upload:", $rawlink);
    } elseif (string_starts_with($rawlink, "Image")) {
        $rawlink = preg_replace("/^Image\\s*:\\s*/", "Upload:", $rawlink);
    } elseif (string_starts_with($rawlink, "image")) {
        $rawlink = preg_replace("/^image\\s*:\\s*/", "Upload:", $rawlink);
    } elseif (string_starts_with($rawlink, "File")) {
        $rawlink = preg_replace("/^File\\s*:\\s*/", "Upload:", $rawlink);
    } elseif (string_starts_with($rawlink, "file")) {
        $rawlink = preg_replace("/^file\\s*:\\s*/", "Upload:", $rawlink);
    }

    $label = UnWikiEscape($label);
    /*
     * Check if the user has typed a explicit URL. This solves the
     * problem where the URLs have a ~ character, which would be stripped away.
     *   "[http:/server/~name/]" will work as expected
     *   "http:/server/~name/"   will NOT work as expected, will remove the ~
     */
    if (string_starts_with($rawlink, "http://")
        or string_starts_with($rawlink, "https://")
    ) {
        $link = $rawlink;
        // Mozilla Browser URI Obfuscation Weakness 2004-06-14
        //   http://www.securityfocus.com/bid/10532/
        //   goodurl+"%2F%20%20%20."+badurl
        if (preg_match("/%2F(%20)+\./i", $rawlink)) {
            $rawlink = preg_replace("/%2F(%20)+\./i", "%2F.", $rawlink);
        }
    } else {
        // Check page name length
        if (!string_starts_with($rawlink, "Upload:")) {
            if (strlen($rawlink) > MAX_PAGENAME_LENGTH) {
                return HTML::span(
                    array('class' => 'error'),
                    _('Page name too long')
                );
            }
        }
        // Page name cannot end with a slash
        if (substr($rawlink, -1) == "/") {
            return HTML::span(
                array('class' => 'error'),
                sprintf(_("Page name “%s” cannot end with a slash."), $rawlink)
            );
        }

        // Check illegal characters in page names: <>[]{}|"
        if (preg_match("/[<\[\{\|\"\}\]>]/", $rawlink, $matches) > 0) {
            return HTML::span(
                array('class' => 'error'),
                sprintf(
                    _("Illegal character “%s” in page name."),
                    $matches[0]
                )
            );
        }
        $link = UnWikiEscape($rawlink);
    }

    /* Relatives links by Joel Schaubert.
     * Recognize [../bla] or [/bla] as relative links, without needing http://
     * Normally /Page links to the subpage /Page.
     */
    if (preg_match('/^\.\.\//', $link)) {
        return new Cached_ExternalLink($link, $label);
    }

    // Handle "[[SandBox|{{image.jpg}}]]" and "[[SandBox|{{image.jpg|alt text}}]]"
    if (string_starts_with($label, "{{")) {
        $imgurl = substr($label, 2, -2); // Remove "{{" and "}}"
        $pipe = strpos($imgurl, '|');
        if ($pipe === false) {
            $label = LinkImage(getUploadDataPath() . $imgurl, $link);
        } else {
            list($img, $alt) = explode("|", $imgurl);
            $label = LinkImage(getUploadDataPath() . $img, $alt);
        }
    } elseif // [label|link]
        // If label looks like a url to an image or object, we want an image link.
        (isImageLink($label)) {
        $imgurl = $label;
        $intermap = getInterwikiMap();
        if (preg_match("/^" . $intermap->getRegexp() . ":/", $label)) {
            $imgurl = $intermap->link($label);
            $imgurl = $imgurl->getAttr('href');
        } elseif (!preg_match("#^(" . ALLOWED_PROTOCOLS . "):#", $imgurl)) {
            // local theme linkname like 'images/next.gif'.
            global $WikiTheme;
            $imgurl = $WikiTheme->getImageURL($imgurl);
        }
        // for objects (non-images) the link is taken as alt tag,
        // which is in return taken as alternative img
        $label = LinkImage($imgurl, $link);
    }

    if ($hash) {
        // It's an anchor, not a link...
        $id = MangleXmlIdentifier($link);
        return HTML::a(array('id' => $id), $bar ? $label : $link);
    }

    if (preg_match("#^(" . ALLOWED_PROTOCOLS . "):#", $link)) {
        // if it's an image, embed it; otherwise, it's a regular link
        if (isImageLink($link) and empty($label)) { // patch #1348996 by Robert Litwiniec
            return LinkImage($link, $label);
        } else {
            return new Cached_ExternalLink($link, $label);
        }
    } elseif (substr($link, 0, 8) == 'phpwiki:') {
        return new Cached_PhpwikiURL($link, $label);
    } /* Semantic relations and attributes.
     * Relation and attribute names must be word chars only, no space.
     * Links and Attributes may contain everything. word, nums, units, space, groupsep, numsep, ...
     */
    elseif (preg_match("/^ (\w+) (:[:=]) (.*) $/x", $link) and !isImageLink($link)) {
        return new Cached_SemanticLink($link, $label);
    } /* Do not store the link */
    elseif (substr($link, 0, 1) == ':') {
        return new Cached_WikiLink($link, $label);
    } /*
     * Inline images in Interwiki urls's:
     * [File:my_image.gif] inlines the image,
     * File:my_image.gif shows a plain inter-wiki link,
     * [what a pic|File:my_image.gif] shows a named inter-wiki link to the gif
     * [File:my_image.gif|what a pic] shows an inlined image linked to the page "what a pic"
     *
     * Note that for simplicity we will accept embedded object tags (non-images)
     * here also, and separate them later in LinkImage()
     */
    elseif (strstr($link, ':')
        and ($intermap = getInterwikiMap())
            and preg_match("/^" . $intermap->getRegexp() . ":/", $link)
    ) {
        // trigger_error("label: $label link: $link", E_USER_WARNING);
        if (empty($label) and isImageLink($link)) {
            // if without label => inlined image [File:xx.gif]
            $imgurl = $intermap->link($link);
            return LinkImage($imgurl->getAttr('href'));
        }
        return new Cached_InterwikiLink($link, $label);
    } else {
        // Split anchor off end of pagename.
        if (preg_match('/\A(.*)(?<!' . ESCAPE_CHAR . ')#(.*?)\Z/', $rawlink, $m)) {
            list(, $rawlink, $anchor) = $m;
            $pagename = UnWikiEscape($rawlink);
            $anchor = UnWikiEscape($anchor);
            if (!$label) {
                $label = $link;
            }
        } else {
            $pagename = $link;
            $anchor = false;
        }
        return new Cached_WikiLink($pagename, $label, $anchor);
    }
}

class Markup_wikicreolebracketlink extends SimpleMarkup
{
    public $_match_regexp = "\\#? \\[\\[ .*? [^]\\s] .*? \\]\\]";

    public function markup($match)
    {
        return LinkBracketLink($match);
    }
}

class Markup_bracketlink extends SimpleMarkup
{
    public $_match_regexp = "\\#? \\[ .*? [^]\\s] .*? \\]";

    public function markup($match)
    {
        return LinkBracketLink($match);
    }
}

class Markup_spellcheck extends SimpleMarkup
{
    public function __construct()
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $this->suggestions = $request->getArg('suggestions');
    }

    public function getMatchRegexp()
    {
        if (empty($this->suggestions)) {
            return "(?# false )";
        }
        $words = array_keys($this->suggestions);
        return "(?<= \W ) (?:" . join('|', $words) . ") (?= \W )";
    }

    public function markup($match)
    {
        if (empty($this->suggestions) or empty($this->suggestions[$match])) {
            return $match;
        }
        return new Cached_SpellCheck(UnWikiEscape($match), $this->suggestions[$match]);
    }
}

class Markup_searchhighlight extends SimpleMarkup
{
    public function __construct()
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $result = $request->_searchhighlight;
        require_once 'lib/TextSearchQuery.php';
        $query = new TextSearchQuery($result['query']);
        $this->hilight_re = $query->getHighlightRegexp();
        $this->engine = $result['engine'];
    }

    public function getMatchRegexp()
    {
        return $this->hilight_re;
    }

    public function markup($match)
    {
        return new Cached_SearchHighlight(UnWikiEscape($match), $this->engine);
    }
}

class Markup_url extends SimpleMarkup
{
    public function getMatchRegexp()
    {
        return "(?<![[:alnum:]]) (?:" . ALLOWED_PROTOCOLS . ") : [^\s<>\"']+ (?<![ ,.?; \] \) ])";
    }

    public function markup($match)
    {
        return new Cached_ExternalLink(UnWikiEscape($match));
    }
}

class Markup_interwiki extends SimpleMarkup
{
    public function getMatchRegexp()
    {
        $map = getInterwikiMap();
        return "(?<! [[:alnum:]])" . $map->getRegexp() . ": [^:=]\S+ (?<![ ,.?;! \] \) \" \' ])";
    }

    public function markup($match)
    {
        return new Cached_InterwikiLink(UnWikiEscape($match));
    }
}

class Markup_semanticlink extends SimpleMarkup
{
    // No units separated by space allowed here
    // For :: (relations) only words, no comma,
    // but for := (attributes) comma and dots are allowed. Units with groupsep.
    // Ending dots or comma are not part of the link.
    public $_match_regexp = "(?: \w+:=\S+(?<![\.,]))|(?: \w+::[\w\.]+(?<!\.))";

    public function markup($match)
    {
        return new Cached_SemanticLink(UnWikiEscape($match));
    }
}

class Markup_wikiword extends SimpleMarkup
{
    public function getMatchRegexp()
    {
        global $WikiNameRegexp;
        if (!trim($WikiNameRegexp)) {
            return " " . WIKI_NAME_REGEXP;
        }
        return " $WikiNameRegexp";
    }

    public function markup($match)
    {
        if (!$match) {
            return false;
        }
        if ($this->_isWikiUserPage($match)) {
            return new Cached_UserLink($match);
        } //$this->_UserLink($match);
        else {
            return new Cached_WikiLink($match);
        }
    }

    // FIXME: there's probably a more useful place to put these two functions
    public function _isWikiUserPage($page)
    {
        global $request;
        $dbi = $request->getDbh();
        $page_handle = $dbi->getPage($page);
        if ($page_handle and $page_handle->get('pref')) {
            return true;
        } else {
            return false;
        }
    }

    public function _UserLink($PageName)
    {
        $link = HTML::a(array('href' => $PageName));
        $link->pushContent(PossiblyGlueIconToText('wikiuser', $PageName));
        $link->setAttr('class', 'wikiuser');
        return $link;
    }
}

class Markup_linebreak extends SimpleMarkup
{
    public $_match_regexp = "(?: (?<! %) %%% (?! %) | \\\\\\\\ | <\s*(?:br|BR)\s*> | <\s*(?:br|BR)\s*\/\s*> )";

    public function markup($match)
    {
        return HTML::br();
    }
}

class Markup_wikicreole_italics extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "\\/\\/";
    }

    public function getEndRegexp($match)
    {
        return "\\/\\/";
    }

    public function markup($match, $body)
    {
        $tag = 'em';
        return new HtmlElement($tag, $body);
    }
}

class Markup_wikicreole_bold extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "\\*\\*";
    }

    public function getEndRegexp($match)
    {
        return "\\*\\*";
    }

    public function markup($match, $body)
    {
        $tag = 'strong';
        return new HtmlElement($tag, $body);
    }
}

class Markup_wikicreole_monospace extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "\\#\\#";
    }

    public function getEndRegexp($match)
    {
        return "\\#\\#";
    }

    public function markup($match, $body)
    {
        return new HtmlElement('span', array('class' => 'tt'), $body);
    }
}

class Markup_wikicreole_underline extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "\\_\\_";
    }

    public function getEndRegexp($match)
    {
        return "\\_\\_";
    }

    public function markup($match, $body)
    {
        $tag = 'u';
        return new HtmlElement($tag, $body);
    }
}

class Markup_wikicreole_superscript extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "\\^\\^";
    }

    public function getEndRegexp($match)
    {
        return "\\^\\^";
    }

    public function markup($match, $body)
    {
        $tag = 'sup';
        return new HtmlElement($tag, $body);
    }
}

class Markup_wikicreole_subscript extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return ",,";
    }

    public function getEndRegexp($match)
    {
        return ",,";
    }

    public function markup($match, $body)
    {
        $tag = 'sub';
        return new HtmlElement($tag, $body);
    }
}

class Markup_old_emphasis extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "''";
    }

    public function getEndRegexp($match)
    {
        return "''";
    }

    public function markup($match, $body)
    {
        $tag = 'em';
        return new HtmlElement($tag, $body);
    }
}

class Markup_nestled_emphasis extends BalancedMarkup
{
    public function getStartRegexp()
    {
        static $start_regexp = false;

        if (!$start_regexp) {
            // The three possible delimiters
            // (none of which can be followed by itself.)
            $i = "_ (?! _)";
            $b = "\\* (?! \\*)";
            $tt = "= (?! =)";

            $any = "(?: ${i}|${b}|${tt})"; // any of the three.

            // Any of [_*=] is okay if preceded by space or one of [-"'/:]
            $start[] = "(?<= \\s|^|[-\"'\\/:]) ${any}";

            // _ or * is okay after = as long as not immediately followed by =
            $start[] = "(?<= =) (?: ${i}|${b}) (?! =)";
            // etc...
            $start[] = "(?<= _) (?: ${b}|${tt}) (?! _)";
            $start[] = "(?<= \\*) (?: ${i}|${tt}) (?! \\*)";

            // any delimiter okay after an opening brace ( [{<(] )
            // as long as it's not immediately followed by the matching closing
            // brace.
            $start[] = "(?<= { ) ${any} (?! } )";
            $start[] = "(?<= < ) ${any} (?! > )";
            $start[] = "(?<= \\( ) ${any} (?! \\) )";

            $start = "(?:" . join('|', $start) . ")";

            // Any of the above must be immediately followed by non-whitespace.
            $start_regexp = $start . "(?= \S)";
        }

        return $start_regexp;
    }

    public function getEndRegexp($match)
    {
        $chr = preg_quote($match);
        return "(?<= \S | ^ ) (?<! $chr) $chr (?! $chr) (?= \s | [-)}>\"'\\/:.,;!? _*=] | $)";
    }

    public function markup($match, $body)
    {
        switch ($match) {
            case '*':
                return new HtmlElement('b', $body);
            case '=':
                return new HtmlElement('span', array('class' => 'tt'), $body);
            case '_':
                return new HtmlElement('i', $body);
        }
        return null;
    }
}

class Markup_html_emphasis extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "<(?: b|big|i|small|tt|em|strong|cite|code|dfn|kbd|samp|s|strike|del|var|sup|sub )>";
    }

    public function getEndRegexp($match)
    {
        return "<\\/" . substr($match, 1);
    }

    public function markup($match, $body)
    {
        $tag = substr($match, 1, -1);
        if (($tag == 'big') || ($tag == 'strike') || ($tag == 'tt')) {
            return new HtmlElement('span', array('class' => $tag), $body);
        }
        return new HtmlElement($tag, $body);
    }
}

class Markup_html_divspan extends BalancedMarkup
{
    public function getStartRegexp()
    {
        return "<(?: div|span )(?: \s[^>]*)?>";
    }
    public function getEndRegexp($match)
    {
        if (substr($match, 1, 4) == 'span') {
            $tag = 'span';
        } else {
            $tag = 'div';
        }
        return "<\\/" . $tag . '>';
    }

    public function markup($match, $body)
    {
        if (substr($match, 1, 4) == 'span') {
            $tag = 'span';
        } else {
            $tag = 'div';
        }
        $rest = substr($match, 1 + strlen($tag), -1);
        if (!empty($rest)) {
            $args = parse_attributes($rest);
        } else {
            $args = array();
        }
        return new HtmlElement($tag, $args, $body);
    }
}

class Markup_html_abbr extends BalancedMarkup
{
    //rurban: abbr|acronym need an optional title tag.
    //sf.net bug #728595

    public function getStartRegexp()
    {
        return  "<(?: abbr|acronym )(?: [^>]*)?>";
    }

    public function getEndRegexp($match)
    {
        if (substr($match, 1, 4) == 'abbr') {
            $tag = 'abbr';
        } else {
            $tag = 'acronym';
        }
        return "<\\/" . $tag . '>';
    }

    public function markup($match, $body)
    {
        // 'acronym' is deprecated in HTML 5, replace by 'abbr'
        $tag = 'abbr';
        $rest = substr($match, 1 + strlen($tag), -1);
        $attrs = parse_attributes($rest);
        // Remove attributes other than title and lang
        $allowedargs = array();
        foreach ($attrs as $key => $value) {
            if (in_array($key, array("title", "lang"))) {
                $allowedargs[$key] = $value;
            }
        }
        return new HtmlElement($tag, $allowedargs, $body);
    }
}

/** ENABLE_MARKUP_COLOR
 *  See http://www.pmwiki.org/wiki/PmWiki/WikiStyles and
 *      http://www.flexwiki.com/default.aspx/FlexWiki/FormattingRules.html
 */
class Markup_color extends BalancedMarkup
{
    // %color=blue% blue text %% and back to normal

    public function getStartRegexp()
    {
        return  "%color=(?: [^%]*)%";
    }

    public function getEndRegexp($match)
    {
        return "%%";
    }

    public function markup($match, $body)
    {
        $color = strtolower(substr($match, 7, -1));

        $morecolors = array('beige' => '#f5f5dc',
            'brown' => '#a52a2a',
            'chocolate' => '#d2691e',
            'cyan' => '#00ffff',
            'gold' => '#ffd700',
            'ivory' => '#fffff0',
            'indigo' => '#4b0082',
            'magenta' => '#ff00ff',
            'orange' => '#ffa500',
            'pink' => '#ffc0cb',
            'salmon' => '#fa8072',
            'snow' => '#fffafa',
            'turquoise' => '#40e0d0',
            'violet' => '#ee82ee',
        );

        if (isset($morecolors[$color])) {
            $color = $morecolors[$color];
        }

        // HTML 4 defines the following 16 colors
        if (in_array($color, array('aqua', 'black', 'blue', 'fuchsia',
            'gray', 'green', 'lime', 'maroon',
            'navy', 'olive', 'purple', 'red',
            'silver', 'teal', 'white', 'yellow'))
            or ((substr($color, 0, 1) == '#')
                and ((strlen($color) == 4) or (strlen($color) == 7))
                    and (strspn(substr($color, 1), '0123456789abcdef') == strlen($color) - 1))
        ) {
            return new HtmlElement('span', array('style' => "color: $color"), $body);
        } else {
            return new HtmlElement(
                'span',
                array('class' => 'error'),
                sprintf(_("unknown color %s ignored"), substr($match, 7, -1))
            );
        }
    }
}

// Wikicreole placeholder
// <<<placeholder>>>
class Markup_placeholder extends SimpleMarkup
{
    public $_match_regexp = '<<<.*?>>>';

    public function markup($match)
    {
        return HTML::span($match);
    }
}

// Single-line HTML comment
// <!-- This is a comment -->
class Markup_html_comment extends SimpleMarkup
{
    public $_match_regexp = '<!--.*?-->';

    public function markup($match)
    {
        return HTML::raw('');
    }
}

// Special version for single-line plugins formatting,
//  like: '<small>< ?plugin PopularNearby ? ></small>'
class Markup_plugin extends SimpleMarkup
{
    public $_match_regexp = '<\?plugin(?:-form)?\s[^\n]+?\?>';

    public function markup($match)
    {
        return new Cached_PluginInvocation($match);
    }
}

// Special version for single-line Wikicreole plugins formatting.
class Markup_plugin_wikicreole extends SimpleMarkup
{
    public $_match_regexp = '<<[^\n]+?>>';

    public function markup($match)
    {
        $pi = str_replace("<<", "<?plugin ", $match);
        $pi = str_replace(">>", " ?>", $pi);
        return new Cached_PluginInvocation($pi);
    }
}

/**
 *  Mediawiki <nowiki>
 *  <nowiki>...</nowiki>
 */
class Markup_nowiki extends SimpleMarkup
{
    public $_match_regexp = '<nowiki>.*?<\/nowiki>';

    public function markup($match)
    {
        // Remove <nowiki> and </nowiki>
        return HTML::raw(substr($match, 8, -9));
    }
}

/**
 *  Wikicreole preformatted
 *  {{{
 *  }}}
 */
class Markup_wikicreole_preformatted extends SimpleMarkup
{
    public $_match_regexp = '\{\{\{.*?\}\}\}';

    public function markup($match)
    {
        // Remove {{{ and }}}
        return new HtmlElement('span', array('class' => 'tt'), substr($match, 3, -3));
    }
}

/** ENABLE_MARKUP_TEMPLATE
 *  Template syntax similar to Mediawiki
 *  {{template}}
 * => < ? plugin Template page=template ? >
 *  {{template|var1=value1|var2=value|...}}
 * => < ? plugin Template page=template var=value ... ? >
 *
 * The {{...}} syntax is also used for:
 *  - Wikicreole images
 *  - videos
 *  - predefined icons
 */
class Markup_template_plugin extends SimpleMarkup
{
    // patch #1732793: allow \n, mult. {{ }} in one line, and single letters
    public $_match_regexp = '\{\{.*?\}\}';

    public function markup($match)
    {
        $page = substr($match, 2, -2);
        $page = trim($page);

        // Check for predefined icons.
        $predefinedicons = array(":)" => "ic_smile.png",
            ":(" => "ic_sad.png",
            ":P" => "ic_tongue.png",
            ":D" => "ic_biggrin.png",
            ";)" => "ic_wink.png",
            "(y)" => "ic_handyes.png",
            "(n)" => "ic_handno.png",
            "(i)" => "ic_info.png",
            "(/)" => "ic_check.png",
            "(x)" => "ic_cross.png",
            "(!)" => "ic_danger.png",
            "(+)" => "ic_plus.png",
            "(-)" => "ic_minus.png",
            "(?)" => "ic_help.png",
            "(on)" => "ic_lighton.png",
            "(off)" => "ic_lightoff.png",
            "(*)" => "ic_yellowstar.png",
            "(*r)" => "ic_redstar.png",
            "(*g)" => "ic_greenstar.png",
            "(*b)" => "ic_bluestar.png",
            "(*y)" => "ic_yellowstar.png",
        );
        foreach ($predefinedicons as $ascii => $icon) {
            if ($page == $ascii) {
                return LinkImage(DATA_PATH . "/themes/default/images/$icon", $page);
            }
        }

        if (strpos($page, "|") === false) {
            $imagename = $page;
            $alt = "";
        } else {
            $imagename = substr($page, 0, strpos($page, "|"));
            $alt = ltrim(strstr($page, "|"), "|");
        }

        // It's not a Mediawiki template, it's a Wikicreole image
        if (is_image($imagename)) {
            if ((strpos($imagename, "http://") === 0) || (strpos($imagename, "https://") === 0)) {
                return LinkImage($imagename, $alt);
            } elseif ($imagename[0] == '/') {
                return LinkImage(DATA_PATH . $imagename, $alt);
            } else {
                return LinkImage(getUploadDataPath() . $imagename, $alt);
            }
        }

        // It's a video
        if (is_video($imagename)) {
            if ((strpos($imagename, 'http://') === 0)
              || (strpos($imagename, 'https://') === 0)) {
                $s = '<' . '?plugin Video url="' . $imagename . '" ?' . '>';
            } else {
                $s = '<' . '?plugin Video file="' . $imagename . '" ?' . '>';
            }
            return new Cached_PluginInvocation($s);
        }

        $page = str_replace("\n", "", $page);

        // The argument value might contain a double quote (")
        // We have to encode that.
        $page = htmlspecialchars($page);

        $vars = '';

        if (preg_match('/^(\S+?)\|(.*)$/', $page, $_m)) {
            $page = $_m[1];
            $vars = '"' . preg_replace('/\|/', '" "', $_m[2]) . '"';
            $vars = preg_replace('/"(\S+)=([^"]*)"/', '\\1="\\2"', $vars);
        }

        // page may contain a version number
        // {{foo?version=5}}
        // in that case, output is "page=foo rev=5"
        if (strstr($page, "?")) {
            $page = str_replace("?version=", "\" rev=\"", $page);
        }

        if ($vars) {
            $s = '<' . '?plugin Template page="' . $page . '" ' . $vars . ' ?' . '>';
        } else {
            $s = '<' . '?plugin Template page="' . $page . '" ?' . '>';
        }
        return new Cached_PluginInvocation($s);
    }
}

class Markup_isonumchars extends SimpleMarkup
{
    public $_match_regexp = '\&\#\d{2,5};';

    public function markup($match)
    {
        return HTML::raw($match);
    }
}

class Markup_isohexchars extends SimpleMarkup
{
    // hexnums, like &#x00A4; <=> &curren;
    public $_match_regexp = '\&\#x[0-9a-fA-F]{2,4};';

    public function markup($match)
    {
        return HTML::raw($match);
    }
}

// FIXME: Do away with magic phpwiki forms.  (Maybe phpwiki: links too?)

class InlineTransformer
{
    public $_regexps = array();
    public $_markup = array();

    public function __construct($markup_types = array())
    {
        global $request;
        // We need to extend the inline parsers by certain actions, like SearchHighlight,
        // SpellCheck and maybe CreateToc.
        if (empty($markup_types)) {
            $non_default = false;
            $markup_types = array('escape', 'wikicreolebracketlink', 'bracketlink', 'url',
                'html_comment', 'placeholder',
                'interwiki', 'semanticlink', 'wikiword', 'linebreak',
                'wikicreole_superscript',
                'wikicreole_subscript',
                'wikicreole_italics', 'wikicreole_bold',
                'wikicreole_monospace',
                'wikicreole_underline',
                'old_emphasis', 'nestled_emphasis',
                'html_emphasis', 'html_abbr', 'plugin', 'plugin_wikicreole',
                'isonumchars', 'isohexchars',
            );
            if (defined('DISABLE_MARKUP_WIKIWORD') and DISABLE_MARKUP_WIKIWORD) {
                $markup_types = array_remove($markup_types, 'wikiword');
            }

            $action = $request->getArg('action');
            if ($action == 'SpellCheck' and $request->getArg('suggestions')) { // insert it after url
                array_splice($markup_types, 2, 1, array('url', 'spellcheck'));
            }
            if (isset($request->_searchhighlight)) { // insert it after url
                array_splice($markup_types, 2, 1, array('url', 'searchhighlight'));
                //$request->setArg('searchhighlight', false);
            }
        } else {
            $non_default = true;
        }
        foreach ($markup_types as $mtype) {
            $class = "Markup_$mtype";
            $this->_addMarkup(new $class());
        }
        $this->_addMarkup(new Markup_nowiki());
        if (defined('ENABLE_MARKUP_DIVSPAN') and ENABLE_MARKUP_DIVSPAN and !$non_default) {
            $this->_addMarkup(new Markup_html_divspan());
        }
        if (defined('ENABLE_MARKUP_COLOR') and ENABLE_MARKUP_COLOR and !$non_default) {
            $this->_addMarkup(new Markup_color());
        }
        // Markup_wikicreole_preformatted must be before Markup_template_plugin
        $this->_addMarkup(new Markup_wikicreole_preformatted());
        if (defined('ENABLE_MARKUP_TEMPLATE') and ENABLE_MARKUP_TEMPLATE and !$non_default) {
            $this->_addMarkup(new Markup_template_plugin());
        }
    }

    public function _addMarkup($markup)
    {
        if (is_a($markup, 'SimpleMarkup')) {
            $regexp = $markup->getMatchRegexp();
        } else {
            $regexp = $markup->getStartRegexp();
        }

        assert(!isset($this->_markup[$regexp]));
        assert(strlen(trim($regexp)) > 0);
        $this->_regexps[] = $regexp;
        $this->_markup[] = $markup;
    }

    public function parse(&$text, $end_regexps = array('$'))
    {
        $regexps = $this->_regexps;

        // $end_re takes precedence: "favor reduce over shift"
        array_unshift($regexps, $end_regexps[0]);
        //array_push($regexps, $end_regexps[0]);
        $regexps = new RegexpSet($regexps);

        $input = $text;
        $output = new XmlContent();

        $match = $regexps->match($input);

        while ($match) {
            if ($match->regexp_ind == 0) {
                // No start pattern found before end pattern.
                // We're all done!
                if (isset($markup) and is_object($markup)
                    and is_a($markup, 'Markup_plugin')
                ) {
                    $current =& $output->_content[count($output->_content) - 1];
                }
                $output->pushContent($match->prematch);
                $text = $match->postmatch;
                return $output;
            }

            $markup = $this->_markup[$match->regexp_ind - 1];
            $body = $this->_parse_markup_body(
                $markup,
                $match->match,
                $match->postmatch,
                $end_regexps
            );
            if (!$body) {
                // Couldn't match balanced expression.
                // Ignore and look for next matching start regexp.
                $match = $regexps->nextMatch($input, $match);
                continue;
            }

            // Matched markup.  Eat input, push output.
            // FIXME: combine adjacent strings.
            if (is_a($markup, 'SimpleMarkup')) {
                $current = $markup->markup($match->match);
            } else {
                $current = $markup->markup($match->match, $body);
            }
            $input = $match->postmatch;
            $output->pushContent($match->prematch, $current);
            $match = $regexps->match($input);
        }

        // No pattern matched, not even the end pattern.
        // Parse fails.
        return false;
    }

    public function _parse_markup_body($markup, $match, &$text, $end_regexps)
    {
        if (is_a($markup, 'SimpleMarkup')) {
            return true; // Done. SimpleMarkup is simple.
        }

        if (!is_object($markup)) {
            return false; // Some error: Should assert
        }
        array_unshift($end_regexps, $markup->getEndRegexp($match));

        // Optimization: if no end pattern in text, we know the
        // parse will fail.  This is an important optimization,
        // e.g. when text is "*lots *of *start *delims *with
        // *no *matching *end *delims".
        $ends_pat = "/(?:" . join(").*(?:", $end_regexps) . ")/xs";
        if (!@preg_match($ends_pat, $text)) { // Add "@" to avoid warning with "{{(*y)}}"
            return false;
        }
        return $this->parse($text, $end_regexps);
    }
}

class LinkTransformer extends InlineTransformer
{
    public function __construct()
    {
        parent::__construct(array('escape', 'wikicreolebracketlink', 'bracketlink', 'url',
            'semanticlink', 'interwiki', 'wikiword',
        ));
    }
}

class NowikiTransformer extends InlineTransformer
{
    public function __construct()
    {
        parent::__construct(array('linebreak',
            'html_emphasis', 'html_abbr', 'plugin', 'plugin_wikicreole',
            'isonumchars', 'isohexchars',
        ));
    }
}

function TransformInline($text, $basepage = false)
{
    /**
      * @var WikiRequest $request
      */
    global $request;

    static $trfm;
    $action = $request->getArg('action');
    if (empty($trfm) or $action == 'SpellCheck') {
        $trfm = new InlineTransformer();
    }

    if ($basepage) {
        return new CacheableMarkup($trfm->parse($text), $basepage);
    }
    return $trfm->parse($text);
}

function TransformLinks($text, $basepage = false)
{
    static $trfm;

    if (empty($trfm)) {
        $trfm = new LinkTransformer();
    }

    if ($basepage) {
        return new CacheableMarkup($trfm->parse($text), $basepage);
    }
    return $trfm->parse($text);
}

/**
 * Transform only html markup and entities.
 */
function TransformInlineNowiki($text, $basepage = false)
{
    static $trfm;

    if (empty($trfm)) {
        $trfm = new NowikiTransformer();
    }
    if ($basepage) {
        return new CacheableMarkup($trfm->parse($text), $basepage);
    }
    return $trfm->parse($text);
}

/**
 * Return an array of links in given text
 */
function getTextLinks($text)
{
    $links = TransformLinks($text);
    $links = $links->_content;
    $wikilinks = array();
    foreach ($links as $link) {
        if (is_a($link, 'Cached_WikiLink')) {
            $wikilinks[] = array('linkto' => $link->_page);
        }
    }
    return $wikilinks;
}
