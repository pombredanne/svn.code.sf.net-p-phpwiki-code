<? rcs_id('$Id: wiki_template.php3,v 1.1.2.2 2000-07-31 18:52:28 dairiki Exp $');
// wiki_template.php3
//
// A new template engine for phpwiki.
//
// Copyright (C) 2000 Geoffrey T. Dairiki <dairiki@dairiki.org>
// You may copy this code freely under the conditions of the GPL.
//

define("TOKEN_BAD", -1);
define("LOOP_CONTINUE", 1);
define("LOOP_END", 0);

/**
 * This clears the cache of a TokenProducer.  (Used on loop iterators.)
 *
 * This function is needed because:
 *
 *  _ClearCache($t->array[$i])
 *
 * works, while
 *
 *  $t->array[$i]->cache = array();
 *
 * results in a "parse error".
 */
function _ClearCache (&$tokproducer) 
{
  $tokproducer->cache = array();
}

function _GetToken (&$tokprod, &$stok, $i) {
  if ($i < sizeof($stok))
      $what = $stok[$i++];
  else
      $what = '';
  
  if (!isset($tokprod->cache[$what]))
      $tokprod->cache[$what] = $tokprod->_get($what);

  if (is_object($tokprod->cache[$what]))
    {
      $val = _GetToken($tokprod->cache[$what], $stok, $i);
      if ($i == sizeof($stok) && !is_string($val))
	  _ClearCache($tokprod->cache[$what]);
    }
  else if ($i < sizeof($stok))
      $val = -1;	// Bad value (not a string).
  else
      $val = $tokprod->cache[$what];

  return $val;
}


class TokenProducer
{
  var $cache = array();
}

class TemplateTokens extends TokenProducer
{
  function safeSet ($name, $val = "") {
    if (is_array($name))
	while (list($n,$v) = each($name))
	    $this->cache[$n] = htmlspecialchars($v);
    else if ($name)
	$this->cache[$name] = htmlspecialchars($val);
  }

  function set ($name, $val = "") {
    if (is_array($name))
	while (list($n,$v) = each($name))
	    $this->cache[$n] = $v;
    else if ($name)
	$this->cache[$name] = $val;
  }

  function get($what) {
    $stok = explode(":", $what);
    $val = _GetToken($this, $stok, 0);
    if (!is_string($val))
      {
	if ($val >= 0)
	    $this->_warning("Illegal reference to loop token \{$what}");
	else
	    $this->_warning("Reference to undefined token \{$what}");
	$val = "\{$what}";
      }
    return $val;
  }
  
  function _get ($what) {
    switch ($what) {
    case 'Browse':
	return new BrowseTokens;
    case 'MostPopular':
	return new MostPopularTokens;
    }
    return TOKEN_BAD;
  }

  function _warning ($message) {
    printf("TOKEN WARNING: %s<br>\n", htmlspecialchars($message));
  }
}

$Tokens = new TemplateTokens;

function SafeSetToken ($name, $val = "") {
  global $Tokens;
  $Tokens->safeSet($name, $val);
}

function SetToken ($name, $val = "") {
  global $Tokens;
  $Tokens->set($name, $val);
}

function GetToken ($name) {
  global $Tokens;
  return $Tokens->get($name);
}

////////////////////////////////////////////////////////////////

// Used by PageTokens 
class PageUrlTokens extends TokenProducer
{
  function PageUrlTokens ($pagename, $version = 0) {
    $this->pagename = $pagename;
    $this->version = $version;
  }
  function _get ($what) {
    return WikiURL($this->pagename, strtolower($what), $this->version);
  }
}

// Used by PageTokens 
class TimeTokens extends TokenProducer 
{
  function TimeTokens ($time) {
    $this->time = $time;
  }
  function _get ($what) {
    global $datetimeformat, $dateformat;
    switch ($what) {
    case '':
    case 'DateTime':
	return htmlspecialchars(date($datetimeformat, $this->time));
    case 'Date':
	return htmlspecialchars(date($dateformat, $this->time));
    }
    return TOKEN_BAD;
  }
}

// Used by PageTokens (and others)
class RefIteratorTokens extends TokenProducer
{
  var $count = 0;
  
  function RefIteratorTokens ($refs, $include_empty_refs = false) {
    $this->refs = $refs;
    $this->inc_empty = $include_empty_refs;
  }
  
  function _get ($what) {
    switch ($what) {
    case '':
	while ($this->refnum++ < NUM_LINKS)
	    if ($this->inc_empty || trim($this->refs[$this->refnum]))
	      {
		$this->count++;
		return LOOP_CONTINUE;
	      }
	return LOOP_END;
    case 'Count': return strval($this->count);
    case 'Num': return strval($this->refnum);
    case 'Url': return htmlspecialchars($this->refs[$this->refnum]);
    }
    return TOKEN_BAD;
  }
}

// Used by PageTokens
class PageFlagTokens extends TokenProducer
{
  function PageFlagTokens ($flags) {
    $this->flags = $flags;
  }
  function _get ($what) {
    switch ($what) {
    case '':       return htmlspecialchars($this->flags);
    case 'Locked': return ($this->flags & FLAG_PAGE_LOCKED) ? 'true' : '';
    }
    return TOKEN_BAD;
  }
}

class PageTokens extends TokenProducer
{
  function PageTokens (&$page, $version = 0) {
    if (is_object($page))
      {
	// Wrap a complete WikiPage.
	$this->page = $page;
	$this->pagename = $page->name();
	$this->version = $page->version();
      }
    else
      {
	// Fake it: only know pagename and possibly page version.
	$this->pagename = $page;
	$this->version = $version;
      }
  }

  function _get_quoted ($what) {

    switch ($what) {
    case '':
	$val = $this->pagename;
        if ($this->version)
	    $val .= " version " . $this->version;
	return $val;
    case 'Name':
	return $this->pagename;
    case 'Version':
	return $this->version ? strval($this->version) : '';

    case 'DiffVsMostRecentUrl':
	return WikiURL($this->pagename, 'diff',
	               array('oldversion' => $this->version));

    }

    if ($this->page)
	switch ($what) {
	case 'Hits':        return strval($this->page->hits());
	case 'Author':      return strval($this->page->author());
	}

    return 0;
  }
  
  function _get ($what) {
    if (is_string($val = $this->_get_quoted($what)))
	return htmlspecialchars($val);
    
    switch ($what) {
    case 'Url':
	$ver = $this->version;
        if ($ver && $this->page && $ver == $this->page->latestversion())
	    $ver = 0;
	return new PageUrlTokens($this->pagename, $ver);

    case 'Latest':
	$lver = $this->page ? $this->page->latestversion() : 0;
        return new PageTokens($this->pagename, $lver);

    case 'Previous':
	global $dbi;
	$pver = $dbi->previousVersion($this->pagename, $this->version);
	return new PageTokens($this->pagename, $pver);
    }

    if ($this->page)
	switch ($what) {
	case 'Created':
	    return new TimeTokens($this->page->created());
	case 'LastModified':
	    return new TimeTokens($this->page->lastmodified());
	case 'Ref':
	    return new RefIteratorTokens($this->page->refs());
	case 'AllRefs':
	    return new RefIteratorTokens($this->page->refs(), 'all');
	case 'Flags':
	    return new PageFlagTokens($this->page->flags());
	case 'ContentAsHtml':
	    return $this->page->asHTML();
	case 'Content':
	    return htmlspecialchars($this->page->packedContent());
	case 'ContentPlain':
	    return nl2br(htmlspecialchars($this->page->packedContent()));
	}

    return TOKEN_BAD;
  }
  
}

class LineIteratorTokens extends TokenProducer
{
  var $count = 0;
  
  function LineIteratorTokens ($lines) {
    $this->lines = $lines;
  }

  function _get ($what) {
    switch ($what) {
    case '': return $this->count++ < sizeof($this->lines)
		 ? LOOP_CONTINUE : LOOP_END;
    case 'Line': return strval($this->lines[$this->count - 1]);
    case 'Count': return strval($this->count);
    }
    return TOKEN_BAD;
  }
}
  
class PageIteratorTokens extends TokenProducer
{
  var $iter;			// Page iterator
  var $count = 0;
  
  function _get ($what) {
    switch ($what) {
    case '':
	if (!$this->next)
	    $this->next = $this->iter->next();
	if (!$this->next)
	    return LOOP_END;
	$this->page = $this->next;
	$this->next = $this->iter->next();
	$this->count++;
	return LOOP_CONTINUE;
    case 'Page':
	return new PageTokens($this->page);
    case 'IsFirst':
	return $this->count == 1 ? 'true' : '';
    case 'IsLast':
	return $this->next ? '' : 'true';
    case 'Count':
	return strval($this->count);
    }
    return $this->_get2($what);	// Hack to allow subclasses to add to this.
  }

  function _get2 ($what) {
    return TOKEN_BAD;
  }
}

class MostPopularTokens extends PageIteratorTokens
{
  function MostPopularTokens($limit = 20) {
    global $dbi;
    $this->iter = $dbi->mostPopular($limit);
  }
}


class BrowseTokens extends TokenProducer
{
  function _get ($what) {
    return WikiURL($what);
  }
}

////////////////////////////////////////////////////////////////
//
// Template parsing:
//
////////////////////////////////////////////////////////////////

class TemplateParser
{
  var $parsed = array();
  
  function TemplateParser ($root, $template_files) {
    $this->root = $root;
    $this->files = $template_files;
  }

  function expand ($tname) {
    $parsed = $this->parse($tname);
    return $parsed->evaluate();
  }
  
  function parse ($tname) {
    if (!isset($this->parsed[$tname]))
      {
	if (!($fn = $this->files[$tname]))
	    die("No file for template '$tname'");

	if ($fn[0] != "/")
	    $fn = $this->root . "/$fn";
	if (!($fp = fopen($fn, "r")))
	    die("Can't open template file '$fn'");
	$data = fread($fp, filesize($fn));
	fclose($fp);

	$this->tname = $tname;
	$this->parsed[$tname] = $this->parseBlock($data);
	if (trim($data))
	    $this->error("trailing cruft: '" . rawurlencode($data) . "'");
      }
    return $this->parsed[$tname];
  }
  
  function expect ($pat, &$line, &$m) {
    if ($ret = preg_match($pat, $line, $m))
	$line = substr($line, strlen($m[0]));
    return $ret;
  }

  function parseBlock (&$line) {
    $block = new ParseBlock;
    while ($this->expect('/^(.*?)(?= $'
			 . ' | ({)\w+(?: :\w+)*} '
			 . ' | <!-- \s* (?:ELSE|END|(IF|LOOP|DEFINE)) [- \t]'
			 . ')/sx', $line, $m))
      {
	if ($m[1])
	    $block->els[] = $m[0]; // leading plain text.

	if ($m[2] == '{')
	  {
	    if (!($block->els[] = $this->parseToken($line)))
		die("assertion error");
	  }
	else if ($m[3])
	  {
	    if ($m[3] == 'IF')
		$el = $this->parseIf($line);
	    else if ($m[3] == 'LOOP')
		$el = $this->parseLoop($line);
	    else if ($m[3] == 'DEFINE')
		$el = $this->parseDefine($line);

	    if (!$el)
		$this->error("Bad " . $m[4] . " begin syntax");
	    $block->els[] = $el;
	  }
	else
	    break;
      }
    return $block;
  }

  function parseToken (&$line) {
    if (!$this->expect('/^{ ( \w+ (?: : \w+ )* ) }/x', $line, $m))
	return false;
    $tok = new ParseToken;
    $tok->stok = explode(':', $m[1]);
    return $tok;
  }

  function parseIf (&$line) {
    if (!$this->expect('/^<!-- \s* IF \s* \( \s* (.*?) \s* \) \s* -->/x',
		       $line, $m))
	return false;

    $ifb = new ParseIf;
    $cond = $m[1];
    if (!($ifb->cond = $this->parseCond($cond)) || $cond)
	$this->error("Bad IF condition: ($m[1])");
	
    $ifb->body = $this->parseBlock($line);

    if ($this->expect('/^<!--\s*ELSE\s*-->/', $line, $m))
	$ifb->elsebody = $this->parseBlock($line);

    if (!$this->expect('/^<!--\s*END\s*-->/', $line, $m))
	$this->error("Unterminated IF " . $m[1] . " block");

    return $ifb;
  }

  function parseLoop (&$line) {
    if (!$this->expect('/^<!-- \s* LOOP \s* {(\w+(?::\w+)*)} \s* -->/x',
		       $line, $m))
	return false;

    $loop = new ParseLoop;
    $loop->stok = explode(':', $m[1]);
    $loop->body = $this->parseBlock($line);

    if (!$this->expect('/^<!--\s*END\s*-->/', $line, $m))
	$this->error("Unterminated LOOP " . $m[1] . " block");
    return $loop;
  }

  function parseDefine (&$line) {
    if (!$this->expect('/^<!-- \s* DEFINE \s* {(\w+(?::\w+)*)} \s* -->/x',
		       $line, $m))
	return false;

    $def = new ParseDefine;
    $def->tok = $m[1];
    $def->body = $this->parseBlock($line);

    if (!$this->expect('/^<!--\s*END\s*-->/', $line, $m))
	$this->error("Unterminated DEFINE ". $m[1] . " block");
    return $def;
  }

  function parseCond (&$line) {
    $save = $line;
    if ($this->expect('/^\s*(!?)\s*/', $line, $m)
        && ($t = $this->parseToken($line))
	&& !trim($line))
      {
        $cond = new ParseCond;
	$cond->op = $m[1];
	$cond->left = $t;
	return $cond;
      }
    $line = $save;
    if (($left = $this->parseTokenOrLiteral($line))
	&& ($this->expect('/^\s*(!=|==)\s*/', $line, $op))
	&& ($right = $this->parseTokenOrLiteral($line))
	&& !trim($line))
      {
        $cond = new ParseCond;
	$cond->op = $op[1];
	$cond->left = $left;
	$cond->right = $right;
	return $cond;
      }
    $line = $save;
    return false;
  }
  
  function parseLiteral (&$line) {
    if ($this->expect('/^\s*('
		      . '"(?:[^"\\\\]|\\\\.)*"'
		      . "|'(?:[^'\\\\]|\\\\.)*'"
		      . '|-?\d+)\s*/', $line, $m))
      {
	$lit = new ParseBlock;
	eval('$lit->els[] = (' . $m[1] . ');');
	return $lit;
      }
    return false;
  }

  function parseTokenOrLiteral (&$line) {
    if ($t = $this->parseToken($line))
	return $t;
    if ($t = $this->parseLiteral($line))
	return $t;
    return false;
  }
  
  function error ($message) {
    printf("ERROR PARSING %s: %s<br>\n",
	   htmlspecialchars($this->tname),
	   htmlspecialchars($message));
    exit;
  }
}

class ParseBlock
{
  var $els = array();
  
  function evaluate () {
    reset($this->els);
    while (list($junk, $el) = each($this->els))
	$out .= is_object($el) ? $el->evaluate() : $el;
    return $out;
  }
}

class ParseToken
{
  function evaluate () {
    global $Tokens;
    $val = _GetToken($Tokens, $this->stok, 0);
    if (!is_string($val))
      {
	$val = "{" . implode(':', $this->stok) . "}";
	if ($val >= 0)
	    $Tokens->_warning("Illegal reference to loop token $val");
	else
	    $Tokens->_warning("Reference to undefined token $val");
      }
    return $val;
  }
}

class ParseIf
{
  function evaluate () {
    if ($this->cond->evaluate())
	return $this->body->evaluate($tokens);
    else if ($this->elsebody)
	return $this->elsebody->evaluate($tokens);
    else
	return '';
  }
}

class ParseLoop
{
  function evaluate () {
    global $Tokens;
    
    $test = _GetToken($Tokens, $this->stok, 0);
    if (is_string($test) || $test < 0)
      {
	$tok = '{' . implode(':', $this->stok) . '}';
	if ($test < 0)
	    $Tokens->_warning("Loop on undefined token $tok");
	else
	    $Tokens->_warning("Loop on non-loop token $tok");
      }
    else if ($test)
      {
	$out = $this->body->evaluate();
	while (_GetToken($Tokens, $this->stok, 0))
	    $out .= $this->body->evaluate();
	return $out;
      }
    return '';
  }
}

class ParseDefine
{
  function evaluate () {
    SetToken($this->tok, $this->body->evaluate());
    return '';
  }
}

class ParseCond
{
  function evaluate () {
    $left = $this->left->evaluate();
    if ($this->right)
	$right = $this->right->evaluate();
    switch ($this->op) {
    case '':   return $left ? true : false;
    case '!':  return ! $left;
    case '==': return $left == $right;
    case '!=': return $left != $right;
    default:   die("assertion error");
    }
  }
}

$TemplateParser = new TemplateParser(WIKI_TEMPLATE_DIR, $TemplateFiles);
function Template ($tname) 
{
  global $TemplateParser;
  return $TemplateParser->expand($tname);
}

?>
