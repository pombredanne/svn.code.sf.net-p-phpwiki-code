<? rcs_id('$Id: wiki_renderlib.php3,v 1.1.2.3 2000-07-29 00:36:45 dairiki Exp $');
/*
 * Various magic characters used as temporary markers during rendering.
 *
 * Note that the characters "\x81" through "\x9f" are used as markers
 * by QuoteTransformer.
 *
 * Also note that all of these characters are control (non-printing)
 * characters in ISO-8859-?.
 */
define('TOKEN_MARKER', "\x01");
define('REPLACE_MARKER', "\x02");
define('MATCHSEP_MARKER', "\x03");

/**
 *
 */
class WikiTokenizer
{
  var $tokens = array();
  var $ntokens = 0;

  // Create a new token, return its string representation.
  function tokenize ($value) {
    $this->tokens[$this->ntokens] = $value;
    return TOKEN_MARKER . $this->ntokens++ . TOKEN_MARKER;
  }

  // Recursively expand all tokens.
  function untokenize ($line) {
    $split = explode(TOKEN_MARKER, $line);
    $n = sizeof($split);
    for ($i = 1; $i < $n; $i += 2)
	$split[$i] = $this->untokenize($this->tokens[$split[$i]]);
    return implode('', $split);
  }
}

  
class WikiRenderer
{
  var $transforms = array();
  var $line = 0;
  
  function registerTransform ($t, $extra_skip = 0) {
    if ($t->pre_transform)
      {
	if ($t->post_transform)
	    $extra_skip += $t->post_transform->ntransforms();
	$this->registerTransform($t->pre_transform, $extra_skip + 1);

	$extra_skip = 0;
      }

    $t->skip_after_hit = $t->final ? 100000 : ($t->repeat ? 0 : 1);
    $t->skip_after_miss = 1 + $extra_skip;
    $t->repl = REPLACE_MARKER . $t->rep . REPLACE_MARKER;
    
    $this->transforms[] = $t;

    if ($t->post_transform)
	$this->registerTransform($t->post_transform);
  }

  function registerTransforms ($list) {
    for (reset($list); $t = current($list); next($list))
	$this->registerTransform($t);
  }
  
  function render_line ($line) {
    $toks = new WikiTokenizer;

    //Remove control characters (except for tabs and \n), and trailing WS.
    $line = preg_replace('/[\x00-\x08\x0b-\x1f\x7f-\x9f]/', '', chop($line));

    $i = 0;
    while ($t = $this->transforms[$i])
      {
	$split = explode(REPLACE_MARKER,
			 preg_replace($t->pat, $t->repl, $line));

	if (($n = sizeof($split)) < 2)
	    $i += $t->skip_after_miss;
	else
	  {
	    for ($j = 1; $j < $n; $j += 2)
	      {
		$val = $t->transform($split[$j], $this);
		if ($t->tokenize)
		    $val = $toks->tokenize($val);
		$split[$j] = $val;
	      }
	    $line = implode('', $split);
	    $i += $t->skip_after_hit;
	  }
      }
    return $toks->untokenize($line);
  }

  function render_lines ($lines) {
    $n = sizeof($lines);
    for ($this->line = 0; $this->line < $n; $this->line++)
	$html .= $this->render_line($lines[$this->line]) . "\n";
    return $html;
  }
}
  
// This replaces SetHTMLOutputMode().  (& class Stack)
class HTMLElementStack
{
  var $depth = 0;
  var $tag = array();

  function pop () {
    $tag = $this->tag[$this->depth--];
    return preg_replace('/^<\s*(\w+).*/', '</\\1>', $tag); // return end tag.
  }
  function push ($tag) {
    return $this->tag[++$this->depth] = $tag; // return start tag.
  }
  function top () {
    return $this->tag[$this->depth];
  }
  
  /**
   * Ensure that the HTML tag at LEVEL is closed.
   *
   * LEVEL specifieds the nesting depth, counting from 0.
   *
   * Eg. "echo $elstack->end(0);" will close all open element.
   *
   * Returns HTML containing the correct end tags.
   */
  function end ($level = 0) {
    if ($this->depth < $level)
	die("assertion error");
    while ($this->depth > $level)
	$html .= $this->pop();
    return $html;
  }

  /**
   * Ensure that we are within an open TAG at LEVEL.
   *
   * TAG should be a complete tag like "<ol>" or "<table border=2>".
   * LEVEL specifieds the nesting depth, counting from 0.
   *
   * Returns HTML containing the correct end/start tags.
   */
  function set ($tag, $level = 0) {
    if ($this->depth > $level)
      {
	while ($this->depth > $level + 1)
	    $html .= $this->pop();
	if ($tag != $this->top())
	    $html .= $this->pop();
      }
    while ($this->depth < $level + 1)
	$html .= "\n" . $this->push($tag);
    return $html;
  }
}

class WikiTransform
{
  // When transform is applied to a line, all /$pat/s are first replaced
  // by $t->transform($rep, $renderer).
  var $pat;
  var $rep = '\\0';

  // If true, this transform (if matched) terminates the rendering
  var $final = false;

  // If true, transform is applied repeatedly, as long as it continues
  //   to match at least once in the line.
  var $repeat = false;

  // If true, replacements are converted to tokens.
  // This more or less "hides" them from subsequent WikiTransform's
  var $tokenize = true;
  
  /*
   * Pre_transform can be used to specify a WikiTransform which should
   * be applied before this transform is applied.
   * If $this->pre_transform does not match the line, then transform
   * $this (and $this->post_transform, if any) are skipped.
   */
  var $pre_transform;

  /*
   * Post_transform can be used to specify a WikiTransform which should
   * be applied after this transform is applied.
   */
  var $post_transform;

  function WikiTransform($pat, $rep = '\\0', $tokenize = true) {
    $this->pat = $pat;
    $this->rep = $rep;
    $this->tokenize = $tokenize;
  }
  
  function transform ($match, &$renderer) {
    return $match;
  }

  // Count the total number of transforms including this one,
  // as well as any transforms in the pre_transform or post_transform
  // trees.
  function ntransforms () {
    $count = 1;
    if ($this->pre_transform)
	$count += $this->pre_transform->ntransforms();
    if ($this->post_transform)
	$count += $this->post_transform->ntransforms();
    return $count;
  }
}

class WikiLayout extends WikiTransform
{
  var $final = true;
  var $tokenize = false;
  
  function WikiLayout ($pat, $tag) {
    $this->pat = $pat;
    $this->_tag = $tag;
  }
  
  function transform ($match, &$r) {
    return $r->element->set( is_array($this->_tag)
			     ? $this->_tag[$match]
			     : $this->_tag );
  }
}
  
/**
 * A WikiReplacer is a WikiTransform which replaces certain fixed strings
 * by other fixed strings.
 */
class WikiReplacer extends WikiTransform
{
  var $pat = '/(?=a)(?!a)/x';	// default: never matches.
  var $table = array();

  function WikiReplacer ($tokenize = true, $reps = false) {
    $this->tokenize = $tokenize;
    if ($reps)
      {
	$this->table = $reps;
	$this->_update_pat();
      }
  }
  
  function add($orig, $rep = "") {
    if (is_array($orig))
      {
	while (list ($orig, $rep) = each($orig))
	    $this->table[$orig] = $rep;
      }
    else
	$this->table[$orig] = $rep;
    $this->_update_pat();
  }

  function _update_pat() {
    reset($this->table);
    while (list($key, $val) = each($this->table))
	$origs[] = preg_quote($key);
    rsort($origs);		// longest first.
    $this->pat = '/' . implode('|', $origs) . '/';
  }
	
  function transform ($match, &$r) {
    return $this->table[$match];
  }
}

/**
 * A QuoteTransformer is used to handle (possible nested) quote like
 * markup: ''stuff like __this__.''
 */
class QuoteTransformer extends WikiTransform
{
  var $pat = '/(?=a)(?!a)/';	// default: never matches.
  var $rep = '\\1\\2';
  var $repeat = true;

  var $pre_transform = new WikiReplacer(false); /* no tokenize */
  var $post_transform = new WikiReplacer;

  var $marker = "\x81";
  var $markers = "";

  function QuoteTransformer ($delims = false) {
    if ($delims)
      {
	reset($delims);
	while (list ($delim, $tags) = each($delims))
	  {
	    list ($open, $close) = $tags;
	    $this->add($delim, $open, $close);
	  }
      }
  }

  function transform ($match, &$r) {
    $mark = substr($match,0,1);
    $text = substr($match,1);
    return $this->open[$mark] . $text . $this->close[$mark];
  }
  
  function add ($delim, $open, $close) {
    if (($mark = $this->marker) == "\0x9f")
	die("Too many quote types");
    $this->marker = chr(ord($mark)+1);
    
    $markers = ($this->markers .= $mark);
    $this->pat = "/([$markers])([^$markers]*)\\1/x";
    
    $this->open[$mark] = $open;
    $this->close[$mark] = $close;
    $this->pre_transform->add($delim, $mark);
    //HACK: favor double delims to single ones.
    //  this parses '''''' as ''' ''' rather than ''''' '.
    $this->pre_transform->add($delim . $delim, $mark . $mark);
    $this->post_transform->add($mark, $delim);
  }
}

////////////////////////////////////////////////////////////////
//Some more useful WikiTransforms

// Causes all HTML elements on the element stack to be finished.
class HTMLFlushTransformer extends WikiLayout
{
  function HTMLFlushTransformer($pat) {
    $this->pat = $pat;
  }
  function transform ($match, &$r) {
    return $r->element->end();
  }
}

// Linkify embedded links ("[1]")
// FIXME: combine with URLTransformer?
class EmbeddedLinkTransformer extends WikiTransform
{
  var $pat = '/\[ \s* (\d+) \s* \]/x';
  var $rep = '\\1';

  function transform ($match, &$r) {
      $html = "[$match]";
      if ( ($url = $r->refs[$match]) )
	{
	  $inline = preg_match("/\\.png$/i", $url);
	  $html = LinkExternal($html, $url, $inline);
	}
      return $html;
  }
}

// Linkify URLS: ("http://foo.com/bar.html")
class URLTransformer extends WikiTransform
{
  //var $pat = '/ \b' . SAFE_URL_REGEXP . '(?<=[^,.?:;]) /x';

  function URLTransformer($pat, $url = '\\0', $text = '') {
    $this->pat = $pat;
    $this->rep = $url . MATCHSEP_MARKER . $text;
  }
  function transform ($match, &$r) {
    list ($url, $text)  = explode(MATCHSEP_MARKER, $match);

    if (!$text)
	$text = $url;
    return LinkExternal($text, $url);
  }
}

// Linkify WikiWords
class WikiLinkTransformer extends WikiTransform
{
  //var $pat = '/ \b (?:[A-Z][a-z]+){2,} \b /x';

  function WikiLinkTransformer ($pat, $link = '\\0') {
    $this->pat = $pat;
    $this->rep = $link;
  }
  function transform ($match, &$r) {
    $r->wikilinks[$match] = $match;
    return LinkWikiWord($match);
  }
}

// Special placeholders:
class PlaceholderTransformer extends WikiTransform
{
  var $pat = "/%%(Search|FullSearch|MostPopular|ZipSnapshot|ZipDump|AdminLogin)%%/";
  var $rep = '\\1';

  function transform ($what, &$r) {
    SafeSetToken('Render', $what);
    
    // FIXME: this is hokey (search term initialization)
    global $value;
    SafeSetToken('content', $value);

    return Template('MISC');
  }
}


// Lists
class ListTransformer extends WikiLayout
{
  //var $pat = '/^(\t+)(.*):\t/';
  //var $rep = '\\1<dt>\\2</dt>';
  //var $_tag = '<dl>';

  function ListTransformer ($pat, $tag, $level, $tail = '<li>') {
    $this->pat = $pat;
    $this->rep = $tag . MATCHSEP_MARKER . $level . MATCHSEP_MARKER . $tail;
  }
  function transform ($match, &$r) {
    list ($tag, $level, $tail) = explode(MATCHSEP_MARKER, $match);
    if ($tag[0] != '<')
	$tag = $tag[0] == '*' ? '<ul>' : '<ol>';
    return $r->element->set($tag, strlen($level)) . $tail;
  }
}

////////////////////////////////////////////////////////////////

/**
 * A WikiPageRenderer is used to transform a wiki page.
 */
class WikiPageRenderer extends WikiRenderer
{
  var $element = new HTMLElementStack;
  var $wikilinks = array();
  var $refs;

  function WikiPageRenderer () {
    global $page_transform;
    ksort($page_transform);
    $this->registerTransforms($page_transform);
  }
  
  function render_page ($page) {
    $start = utime();
    
    $this->refs = $page->refs();

    $html = $this->render_lines($page->content()) . $this->element->end();

    Debug(sprintf('Rendering took %f seconds', utime() - $start));

    return $html;
  }
}


////////////////////////////////////////////////////////////////
//
// Traditional Wiki markup:
//

// Blank lines force new <p>. 
$page_transform['!00Blank'] = new HTMLFlushTransformer('/^\s*$/');
// Quoted left bracket "[[".
$page_transform['05Quote[['] = new WikiTransform('/\[{2}/', '[');
// Linkify embedded links ("[1]")
$page_transform['10EmbeddedLink'] = new EmbeddedLinkTransformer;
// Linkify URLS: ("http://foo.com/bar.html")
$page_transform['20URL'] = new URLTransformer(
    '/ \b' . SAFE_URL_REGEXP . '(?<=[^,.?:;]) /x');
// Linkify WikiWords
$page_transform['30WikiWord']
    = new WikiLinkTransformer('/ \b (?:[A-Z][a-z]+){2,} \b /x');
// Horizontal Rules:
$page_transform['50HR'] = new WikiTransform('/^-{4,}/', '<hr>');

// meta chars:
$page_transform['50MetaChar'] = new WikiReplacer(true,
						 array('<' => '&lt;',
						       '>' =>  '&gt;',
						       '&' => '&amp;'));

// Emphasis:
$page_transform['60Quotes'] = new QuoteTransformer(
    array("''" => array("<em>", "</em>"),
	  "'''" => array("<strong>", "</strong>"),
	  "'''''" => array("<strong><em>", "</em></strong>")));

// Definition Lists:
$page_transform['^80DefnLists'] = new ListTransformer(
    '/^\t(\t*)(.*):\t/', '<dl>', '\\1', '<dt>\\2</dt><dd>');
// Ordered and unordered lists:
$page_transform['^80Lists'] = new ListTransformer(
    '/^\t(\t*)([*#]|\d+)/', '\\2', '\\1', '<li>');
    
// <pre> text
$page_transform['^85Pre'] = new WikiLayout('/^(?=\s)/', '<pre>');
// Default layout (ensure <p>)
$page_transform['^^99Default'] = new WikiLayout('/^/', '<p>');


// Special placeholders:
$page_transform['25Placeholders'] = new PlaceholderTransformer;


////////////////////////////////////////////////////////////////
//
// PhpWiki extensions:  (These should probably be in a separate file.)

// Raw HTML
$page_transform['!00RawHTML'] = new HTMLFlushTransformer('/^\|(?![|{}])/');

// Bracketed URLS:
$page_transform['10BracketedURL'] = new URLTransformer(
    ( '/ \[ \s*'
      . '(?: (' . PAGENAME_REGEXP . ') \s* \| \s* )?'
      . '(' . SAFE_URL_REGEXP . ')'
      . '\s* \] /x'),
    '\\2', '\\1');

// Bracketed Wiki Links
$page_transform['11BracketedWikiLink'] = new WikiLinkTransformer(
    '/ \[\s* (' . PAGENAME_REGEXP . ') \s*\] /x',
    '\\1');

// Quoted !WikiWords
$page_transform['29QuotedWikiWord']
    = new WikiTransform('/!((?:[A-Z][a-z]+){2,})\b/', '\\1');

// Line breaks
$page_transform['50MetaChar']->add('%%%', '<br>');

// Alternate boldification.
$page_transform['60Quotes']->add("__", "<strong>", "</strong>");

// Alternate list syntax.
$page_transform['^80NewLists'] = new ListTransformer(
    '/^([*#]*)([*#])/', '\\2', '\\1', '<li>');

// !!!Headings
$page_transform['^80Headings'] = new WikiLayout(
    '/^!{1,3}/',
    array('!!!' => '<h1>', '!!' => '<h2>', '!' => '<h3>'));

/////////////////////////////////////////////////////////
// Example extension:
// Tables:
class TableTransformer extends WikiTransform
{
  var $pre_transform
      = new WikiTransform('/^[|][|{}]*.*$/', "\n<tr>\\0</tr>", false);

  var $post_transform = new WikiLayout('/^/',
				       '<table cellspacing=1 cellpadding=2 border=3>');

  var $pat = ": ([|][|{}]) \s* (.*?) \s* (?= [|][|{}] | </tr>$ ) :x";
  var $rep = '\\1' . MATCHSEP_MARKER . '\\2';
  var $tokenize = false;	// Could be either?


  var $table = array( '||' => 'center',
		      '|}' => 'right',
		      '|{' => 'left' );

  function transform ($match, &$r) {
    list ($align, $text) = explode(MATCHSEP_MARKER, $match);
    return sprintf('<td align="%s">%s</td>', $this->table[$align], $text);
  }
}

$page_transform['^80Tables'] = new TableTransformer;
// Fixup pat in Raw HTML transformer.
if ($page_transform['!00RawHTML'])
  $page_transform['!00RawHTML']->pat = '/^\|(?![|{}])/';


?>
