<?php rcs_id('$Id: transform.php,v 1.16 2001-02-14 05:22:49 dairiki Exp $');

define('WT_SIMPLE_MARKUP', 0);
define('WT_TOKENIZER', 1);
define('WT_MODE_MARKUP', 2);

define("ZERO_LEVEL", 0);
define("NESTED_LEVEL", 1);

class WikiTransform
{
   /*
   function WikiTransform() -- init

   function register($type, $function, [$regexp])
	Registers transformer functions
	This should be done *before* calling do_transform

	$type:
	   WT_MODE_MARKUP
	          If one WT_MODE_MARKUP really sets the html mode, then
		  all successive WT_MODE_MARKUP functions are skipped
           WT_TOKENIZER
		  The transformer function is called once for each match
		  of the $regexp in the line.  The matched values are tokenized
		  to protect them from further transformation.

	$function: function name

        $regexp:  Required for WT_TOKENIZER functions.  Optional for others.
	          If given, the transformer function will only be called if the
		  line matches the $regexp.
	
   function SetHTMLMode($tag, $tagtype, $level)
	Wiki HTML output can, at any given time, be in only one mode.
	It will be something like Unordered List, Preformatted Text,
	plain text etc. When we change modes we have to issue close tags
	for one mode and start tags for another.
	SetHTMLMode takes care of this.

	$tag ... HTML tag to insert
	         If $tag is an array, first element give tag, second element
		 is a hash containing arguments for the tag.
	$tagtype ... ZERO_LEVEL - close all open tags before inserting $tag
		     NESTED_LEVEL - close tags until depths match
	$level ... nesting level (depth) of $tag
		   nesting is arbitrary limited to 10 levels

   function do_transform($html, $content)
	contains main-loop and calls transformer functions

	$html ... HTML header (if needed, otherwise '')
	$content ... wiki markup as array of lines
   */


   // public variables (only meaningful during do_transform)
   var $linenumber;	// current linenumber
   var $replacements;	// storage for tokenized strings of current line
   var $user_data;	// can be used by the transformer functions
                        // to store miscellaneous data.
   
   // private variables
   var $content;	// wiki markup, array of lines
   var $mode_set;	// stores if a HTML mode for this line has been set
   var $trfrm_func;	// array of registered functions
   var $stack;		// stack for SetHTMLMode (keeping track of open tags)

   // init function
   function WikiTransform()
   {
      $this->trfrm_func = array();
      $this->stack = new Stack;
   }

   // register transformation functions
   function register($type, $function, $regexp = false)
   {
      $this->trfrm_func[] = array ($type, $function, $regexp);
   }
   
   // sets current mode like list, preformatted text, plain text, ...
   // takes care of closing (open) tags
   function SetHTMLMode($tag, $tagtype, $level)
   {
      if (is_array($tag)) {
	 $args = $tag[1];
	 $tag = $tag[0];
      }
      else {
	 $args = array();
      }

      $this->mode_set = 1;	// in order to prevent other mode markup
				// to be executed
      $retvar = '';
	 
      if ($tagtype == ZERO_LEVEL) {
         // empty the stack until $level == 0;
         if ($tag == $this->stack->top()) {
            return; // same tag? -> nothing to do
         }
         while ($this->stack->cnt() > 0) {
            $closetag = $this->stack->pop();
            $retvar .= "</$closetag>\n";
         }
   
         if ($tag) {
            $retvar .= StartTag($tag, $args) . "\n";
            $this->stack->push($tag);
         }


      } elseif ($tagtype == NESTED_LEVEL) {
         if ($level <= $this->stack->cnt()) {
            // $tag has fewer nestings (old: tabs) than stack,
	    // reduce stack to that tab count
            while ($this->stack->cnt() > $level) {
               $closetag = $this->stack->pop();
               if ($closetag == false) {
                  //echo "bounds error in tag stack";
                  break;
               }
               $retvar .= "</$closetag>\n";
            }

	    // if list type isn't the same,
	    // back up one more and push new tag
	    if ($tag != $this->stack->top()) {
	       $closetag = $this->stack->pop();
	       $retvar .= "</$closetag>" . StartTag($tag, $args) . "\n";
	       $this->stack->push($tag);
	    }
   
         } else { // $level > $this->stack->cnt()
            // we add the diff to the stack
            // stack might be zero
            while ($this->stack->cnt() < $level) {
               $retvar .= StartTag($tag, $args) . "\n";
               $this->stack->push($tag);
               if ($this->stack->cnt() > 10) {
                  // arbitrarily limit tag nesting
                  ExitWiki(gettext ("Stack bounds exceeded in SetHTMLOutputMode"));
               }
            }
         }

      } else { // unknown $tagtype
         ExitWiki ("Passed bad tag type value in SetHTMLOutputMode");
      }

      return $this->token($retvar);
   }
   // end SetHTMLMode


   // work horse and main loop
   // this function does the transform from wiki markup to HTML
   function do_transform($html, $content)
   {
      global $FieldSeparator;

      $this->content = $content;
      $this->replacements = array();
      $this->user_data = array();
      
      // Loop over all lines of the page and apply transformation rules
      $numlines = count($this->content);
      for ($lnum = 0; $lnum < $numlines; $lnum++)
      {
	 
	 $this->linenumber = $lnum;
	 $line = $this->content[$lnum];

	 // blank lines clear the current mode
	 if (!strlen($line) || $line == "\r") {
            $html .= $this->SetHTMLMode('', ZERO_LEVEL, 0);
            continue;
	 }

	 $this->mode_set = 0;

	 // main loop applying all registered functions
	 // tokenizers, markup, html mode, ...
	 // functions are executed in order of registering
	 for (reset($this->trfrm_func);
	      list($flags, $func, $regexp) = current($this->trfrm_func);
	      next($this->trfrm_func)) {

	    // if HTMLmode is already set then skip all following
	    // WT_MODE_MARKUP functions
	    if ($this->mode_set && ($flags & WT_MODE_MARKUP) != 0) 
	       continue;

	    if (!empty($regexp) && !preg_match("/$regexp/", $line))
	       continue;

	    // call registered function
	    if (($flags & WT_TOKENIZER) != 0)
	       $line = $this->tokenize($line, $regexp, $func);
	    else
	       $line = $func($line, $this);
	 }

	 $html .= $line . "\n";
      }
      // close all tags
      $html .= $this->SetHTMLMode('', ZERO_LEVEL, 0);

      return $this->untokenize($html);
   }
   // end do_transfrom()

   // Register a new token.
   function token($repl) {
      global $FieldSeparator;
      $tok = $FieldSeparator . sizeof($this->replacements) . $FieldSeparator;
      $this->replacements[] = $repl;
      return $tok;
   }
   
   // helper function which does actual tokenizing
   function tokenize($str, $pattern, $func) {
      // Find any strings in $str that match $pattern and
      // store them in $orig, replacing them with tokens
      // starting at number $ntokens - returns tokenized string
      $new = '';      
      while (preg_match("/^(.*?)($pattern)/", $str, $matches)) {
	 $str = substr($str, strlen($matches[0]));
	 $new .= $matches[1] . $this->token($func($matches[2], $this));
      }
      return $new . $str;
   }

   function untokenize($line) {
      global $FieldSeparator;
      
      $chunks = explode ($FieldSeparator, "$line ");
      $line = $chunks[0];
      for ($i = 1; $i < count($chunks); $i += 2)
      {
	 $tok = $chunks[$i];
	 $line .= $this->replacements[$tok] . $chunks[$i + 1];
      }
      return $line;
   }
}
// end class WikiTransform


//////////////////////////////////////////////////////////

$transform = new WikiTransform;

// register functions
// functions are applied in order of registering

$transform->register(WT_TOKENIZER, 'wtt_doublebrackets', '\[\[');
$transform->register(WT_TOKENIZER, 'wtt_footnotes', '^\[\d+\]');
$transform->register(WT_TOKENIZER, 'wtt_footnoterefs', '\[\d+\]');
$transform->register(WT_TOKENIZER, 'wtt_bracketlinks', '\[.+?\]');
$transform->register(WT_TOKENIZER, 'wtt_urls',
		     "!?\b($AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]");

if (function_exists('wtt_interwikilinks')) {
   $transform->register(WT_TOKENIZER, 'wtt_interwikilinks',
			"!?(?<![A-Za-z0-9])$InterWikiLinkRegexp:$WikiNameRegexp");
}
$transform->register(WT_TOKENIZER, 'wtt_bumpylinks', "!?$WikiNameRegexp");

if (function_exists('wtm_table')) {
   $transform->register(WT_MODE_MARKUP, 'wtm_table', '^\|');
}
   $transform->register(WT_SIMPLE_MARKUP, 'wtm_htmlchars');
   $transform->register(WT_SIMPLE_MARKUP, 'wtm_linebreak');
   $transform->register(WT_SIMPLE_MARKUP, 'wtm_bold_italics');
   $transform->register(WT_SIMPLE_MARKUP, 'wtm_title_search');
   $transform->register(WT_SIMPLE_MARKUP, 'wtm_fulltext_search');
   $transform->register(WT_SIMPLE_MARKUP, 'wtm_mostpopular');

   $transform->register(WT_MODE_MARKUP, 'wtm_list_ul');
   $transform->register(WT_MODE_MARKUP, 'wtm_list_ol');
   $transform->register(WT_MODE_MARKUP, 'wtm_list_dl');
   $transform->register(WT_MODE_MARKUP, 'wtm_preformatted');
   $transform->register(WT_MODE_MARKUP, 'wtm_headings');
   $transform->register(WT_MODE_MARKUP, 'wtm_hr');
   $transform->register(WT_MODE_MARKUP, 'wtm_paragraph');

   $html = $transform->do_transform($html, $pagehash['content']);

/*
Requirements for functions registered to WikiTransform:

Signature:  function wtm_xxxx($line, &$transform)

$line ... current line containing wiki markup
	(Note: it may already contain HTML from other transform functions)
&$transform ... WikiTransform object -- public variables of this
	object and their use see above.

Functions have to return $line (doesn't matter if modified or not)
All conversion should take place inside $line.

Tokenizer functions should use $transform->replacements to store
the replacement strings. Also, they have to keep track of
$transform->tokencounter. See functions below. Back substitution
of tokenized strings is done by do_transform().
*/



   //////////////////////////////////////////////////////////
   // Tokenizer functions


function  wtt_doublebrackets($match, &$trfrm)
{
   return '[';
}

function wtt_footnotes($match, &$trfrm)
{
   // FIXME: should this set HTML mode?
   $ftnt = trim(substr($match,1,-1)) + 0;
   $fntext = "[$ftnt]";
   $html = "<br>";

   $fnlist = $trfrm->user_data['footnotes'][$ftnt];
   if (!is_array($fnlist))
      return $html . $fntext;	
   
   $trfrm->user_data['footnotes'][$ftnt] = 'footnote_seen';

   while (list($k, $anchor) = each($fnlist))
   {
      $html .=  Element("a", array("name" => "footnote-$ftnt",
				   "href" => "#$anchor",
				   "class" => "footnote-rev"),
			$fntext);
      $fntext = '+';
   }
   return $html;
}

function wtt_footnoterefs($match, &$trfrm)
{
   $ftnt = trim(substr($match,1,-1)) + 0;

   $footnote_definition_seen = false;

   if (empty($trfrm->user_data['footnotes']))
      $trfrm->user_data['footnotes'] = array();
   if (empty($trfrm->user_data['footnotes'][$ftnt]))
      $trfrm->user_data['footnotes'][$ftnt] = array();
   else if (!is_array($trfrm->user_data['footnotes'][$ftnt]))
      $footnote_definition_seen = true;
   

   $args['href'] = "#footnote-$ftnt";
   if (!$footnote_definition_seen)
   {
      $args['name'] = "footrev-$ftnt-" .
	  count($trfrm->user_data['footnotes'][$ftnt]);
      $trfrm->user_data['footnotes'][$ftnt][] = $args['name'];
   }
   
   return Element('sup', array('class' => 'footnote'),
		  QElement("a", $args, "[$ftnt]"));
}

function wtt_bracketlinks($match, &$trfrm)
{
   $link = ParseAndLink($match);
   return $link["link"];
}



// replace all URL's with tokens, so we don't confuse them
// with Wiki words later. Wiki words in URL's break things.
// URLs preceeded by a '!' are not linked
function wtt_urls($match, &$trfrm)
{
   if ($match[0] == "!")
      return htmlspecialchars(substr($match,1));
   return LinkURL($match);
}

// Link Wiki words (BumpyText)
// Wikiwords preceeded by a '!' are not linked
function wtt_bumpylinks($match, &$trfrm)
{
   global $dbi;
   if ($match[0] == "!")
      return htmlspecialchars(substr($match,1));
   // FIXME: make a LinkWikiWord() function?
   if (IsWikiPage($dbi, $match))
      return LinkExistingWikiWord($match);
   return LinkUnknownWikiWord($match);
}

// end of tokenizer functions
//////////////////////////////////////////////////////////


   //////////////////////////////////////////////////////////
   // basic simple markup functions

   // escape HTML metachars
   function wtm_htmlchars($line, &$transformer)
   {
      $line = str_replace('&', '&amp;', $line);
      $line = str_replace('>', '&gt;', $line);
      $line = str_replace('<', '&lt;', $line);
      return($line);
   }


   // %%% are linebreaks
   function wtm_linebreak($line, &$transformer) {
      return str_replace('%%%', '<br>', $line);
   }

   // bold and italics
   function wtm_bold_italics($line, &$transformer) {
      $line = preg_replace('|(__)(.*?)(__)|', '<strong>\2</strong>', $line);
      $line = preg_replace("|('')(.*?)('')|", '<em>\2</em>', $line);
      return $line;
   }



   //////////////////////////////////////////////////////////
   // some tokens to be replaced by (dynamic) content

   // wiki token: title search dialog
   function wtm_title_search($line, &$transformer) {
      if (strpos($line, '%%Search%%') !== false) {
	 $html = LinkPhpwikiURL(
	    "phpwiki:?action=search&searchterm=()&searchtype=title",
	    gettext("Search"));

	 $line = str_replace('%%Search%%', $html, $line);
      }
      return $line;
   }

   // wiki token: fulltext search dialog
   function wtm_fulltext_search($line, &$transformer) {
      if (strpos($line, '%%Fullsearch%%') !== false) {
	 $html = LinkPhpwikiURL(
	    "phpwiki:?action=search&searchterm=()&searchtype=full",
	    gettext("Search"));

	 $line = str_replace('%%Fullsearch%%', $html, $line);
      }
      return $line;
   }

   // wiki token: mostpopular list
   function wtm_mostpopular($line, &$transformer) {
      global $ScriptUrl, $dbi;
      if (strpos($line, '%%Mostpopular%%') !== false) {
	 $query = InitMostPopular($dbi, MOST_POPULAR_LIST_LENGTH);
	 $html = "<DL>\n";
	 while ($qhash = MostPopularNextMatch($dbi, $query)) {
	    $html .= "<DD>$qhash[hits] ... " . LinkExistingWikiWord($qhash['pagename']) . "\n";
	 }
	 $html .= "</DL>\n";
	 $line = str_replace('%%Mostpopular%%', $html, $line);
      }
      return $line;
   }


   //////////////////////////////////////////////////////////
   // mode markup functions


   // tabless markup for unordered, ordered, and dictionary lists
   // ul/ol list types can be mixed, so we only look at the last
   // character. Changes e.g. from "**#*" to "###*" go unnoticed.
   // and wouldn't make a difference to the HTML layout anyway.

   // unordered lists <UL>: "*"
   // has to be registereed before list OL
   function wtm_list_ul($line, &$trfrm) {
      if (preg_match("/^([#*]*\*)[^#]/", $line, $matches)) {
         $numtabs = strlen($matches[1]);
         $line = preg_replace("/^([#*]*\*)/", '', $line);
         $html = $trfrm->SetHTMLMode('ul', NESTED_LEVEL, $numtabs) . '<li>';
         $line = $html . $line;
      }
      return $line;
   }

   // ordered lists <OL>: "#"
   function wtm_list_ol($line, &$trfrm) {
      if (preg_match("/^([#*]*\#)/", $line, $matches)) {
         $numtabs = strlen($matches[1]);
         $line = preg_replace("/^([#*]*\#)/", "", $line);
         $html = $trfrm->SetHTMLMode('ol', NESTED_LEVEL, $numtabs) . '<li>';
         $line = $html . $line;
      }
      return $line;
   }


   // definition lists <DL>: ";text:text"
   function wtm_list_dl($line, &$trfrm) {
      if (preg_match("/(^;+)(.*?):(.*$)/", $line, $matches)) {
         $numtabs = strlen($matches[1]);
         $line = $trfrm->SetHTMLMode('dl', NESTED_LEVEL, $numtabs);
	 if(trim($matches[2]))
            $line = '<dt>' . $matches[2];
	 $line .= '<dd>' . $matches[3];
      }
      return $line;
   }

   // mode: preformatted text, i.e. <pre>
   function wtm_preformatted($line, &$trfrm) {
      if (preg_match("/^\s+/", $line)) {
         $line = $trfrm->SetHTMLMode('pre', ZERO_LEVEL, 0) . $line;
      }
      return $line;
   }

   // mode: headings, i.e. <h1>, <h2>, <h3>
   // lines starting with !,!!,!!! are headings
   function wtm_headings($line, &$trfrm) {
      if (preg_match("/^(!{1,3})[^!]/", $line, $whichheading)) {
	 if($whichheading[1] == '!') $heading = 'h3';
	 elseif($whichheading[1] == '!!') $heading = 'h2';
	 elseif($whichheading[1] == '!!!') $heading = 'h1';
	 $line = preg_replace("/^!+/", '', $line);
	 $line = $trfrm->SetHTMLMode($heading, ZERO_LEVEL, 0) . $line;
      }
      return $line;
   }

// markup for tables
function wtm_table($line, &$trfrm)
{
   $row = '';
   while (preg_match('/^(\|+)(v*)([<>^]?)([^|]*)/', $line, $m))
   {
      $line = substr($line, strlen($m[0]));
      $td = array();
      
      if (strlen($m[1]) > 1)
	 $td['colspan'] = strlen($m[1]);
      if (strlen($m[2]) > 0)
	 $td['rowspan'] = strlen($m[2]) + 1;
      
      if ($m[3] == '<')
	 $td['align'] = 'left';
      else if ($m[3] == '>')
	 $td['align'] = 'right';
      else
	 $td['align'] = 'center';
      
      $row .= $trfrm->token(StartTag('td', $td) . "&nbsp;");
      $row .= trim($m[4]);
      $row .= $trfrm->token("&nbsp;</td>");
   }
   assert(empty($line));
   $row = $trfrm->token("<tr>") . $row . $trfrm->token("</tr>");
   
   return $trfrm->SetHTMLMode(array('table',
				    array('align' => 'center',
					  'cellpadding' => 1,
					  'cellspacing' => 1,
					  'border' => 1)),
			      ZERO_LEVEL, 0) .
      $row;
}

   // four or more dashes to <hr>
   // Note this is of type WT_MODE_MARKUP becuase <hr>'s aren't
   // allowed within <p>'s. (e.g. "<p><hr></p>" is not valid HTML.)
   function wtm_hr($line, &$trfrm) {
      if (preg_match('/^-{4,}(.*)$/', $line, $m)) {
	 $line = $trfrm->SetHTMLMode('', ZERO_LEVEL, 0) . '<hr>';
	 if ($m[1])
	    $line .= $trfrm->SetHTMLMode('p', ZERO_LEVEL, 0) . $m[1];
      }
      return $line;
   }

   // default mode: simple text paragraph
   function wtm_paragraph($line, &$trfrm) {
      $line = $trfrm->SetHTMLMode('p', ZERO_LEVEL, 0) . $line;
      return $line;
   }

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
