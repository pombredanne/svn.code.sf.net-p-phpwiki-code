<?php rcs_id('$Id: transform.php,v 1.14 2001-02-10 22:15:08 dairiki Exp $');

define('WT_TOKENIZER', 1);
define('WT_SIMPLE_MARKUP', 2);
define('WT_MODE_MARKUP', 3);

class WikiTransform
{
   /*
   function WikiTransform() -- init

   function register($type, $function)
	Registers transformer functions
	This should be done *before* calling do_transform

	$type ... one of WT_TOKENIZER, WT_SIMPLE_MARKUP, WT_MODE_MARKUP
		  Currently on WT_MODE_MARKUP has a special meaning.
		  If one WT_MODE_MARKUP really sets the html mode, then
		  all successive WT_MODE_MARKUP functions are skipped

	$function ... function name

   function SetHTMLMode($tag, $tagtype, $level)
	Wiki HTML output can, at any given time, be in only one mode.
	It will be something like Unordered List, Preformatted Text,
	plain text etc. When we change modes we have to issue close tags
	for one mode and start tags for another.
	SetHTMLMode takes care of this.

	$tag ... HTML tag to insert
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
   var $tokencounter;	// counter of $replacements array

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
   function register($type, $function)
   {
      $this->trfrm_func[] = array ($type, $function);
   }
   
   // sets current mode like list, preformatted text, plain text, ...
   // takes care of closing (open) tags
   function SetHTMLMode($tag, $tagtype, $level)
   {
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
            $retvar .= "<$tag>\n";
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
	       $retvar .= "</$closetag><$tag>\n";
	       $this->stack->push($tag);
	    }
   
         } else { // $level > $this->stack->cnt()
            // we add the diff to the stack
            // stack might be zero
            while ($this->stack->cnt() < $level) {
               $retvar .= "<$tag>\n";
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

      return $retvar;
   }
   // end SetHTMLMode


   // work horse and main loop
   // this function does the transform from wiki markup to HTML
   function do_transform($html, $content)
   {
      global $FieldSeparator;

      $this->content = $content;

      // Loop over all lines of the page and apply transformation rules
      $numlines = count($this->content);
      for ($lnum = 0; $lnum < $numlines; $lnum++)
      {
	 $this->tokencounter = 0;
	 $this->replacements = array();
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
	 for ($func = 0; $func < count($this->trfrm_func); $func++) {
	    // if HTMLmode is already set then skip all following
	    // WT_MODE_MARKUP functions
	    if ($this->mode_set &&
	       ($this->trfrm_func[$func][0] == WT_MODE_MARKUP)) {
	       continue;
	    }
	    // call registered function
	    $line = $this->trfrm_func[$func][1]($line, $this);
	 }

	 // Replace tokens ($replacements was filled by wtt_* functions)
	 for ($i = 0; $i < $this->tokencounter; $i++) {
	     $line = str_replace($FieldSeparator.$FieldSeparator.$i.$FieldSeparator, $this->replacements[$i], $line);
	 }

	 $html .= $line . "\n";
      }
      // close all tags
      $html .= $this->SetHTMLMode('', ZERO_LEVEL, 0);

      return $html;
   }
   // end do_transfrom()

}
// end class WikiTransform


   //////////////////////////////////////////////////////////

   $transform = new WikiTransform;

   // register functions
   // functions are applied in order of registering

   $transform->register(WT_TOKENIZER, 'wtt_bracketlinks');
   $transform->register(WT_TOKENIZER, 'wtt_urls');
   if ($InterWikiLinking) {
      $transform->register(WT_TOKENIZER, 'wtt_interwikilinks');
   }
   $transform->register(WT_TOKENIZER, 'wtt_bumpylinks');

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

   // helper function which does actual tokenizing and is
   // called by other wtt_* functions
   function wt_tokenize($str, $pattern, &$orig, &$ntokens) {
      global $FieldSeparator;
      // Find any strings in $str that match $pattern and
      // store them in $orig, replacing them with tokens
      // starting at number $ntokens - returns tokenized string
      $new = '';      
      while (preg_match("/^(.*?)($pattern)/", $str, $matches)) {
         $linktoken = $FieldSeparator . $FieldSeparator . ($ntokens++) . $FieldSeparator;
         $new .= $matches[1] . $linktoken;
	 $orig[] = $matches[2];
         $str = substr($str, strlen($matches[0]));
      }
      $new .= $str;
      return $new;
   }


   // New linking scheme: links are in brackets. This will
   // emulate typical HTML linking as well as Wiki linking.
   function wtt_bracketlinks($line, &$trfrm)
   {
      static $footnotes = array();

      // protecting [[
      $n = $ntok = $trfrm->tokencounter;
      $line = wt_tokenize($line, '\[\[', $trfrm->replacements, $ntok);
      while ($n < $ntok) {
         $trfrm->replacements[$n++] = '[';
      }

      // match anything else between brackets 
      $line = wt_tokenize($line, '\[.+?\]', $trfrm->replacements, $ntok);
      while ($n < $ntok) {
	$link = ParseAndLink($trfrm->replacements[$n]);
	if (strpos($link['type'], 'footnote') === false) {
	   $trfrm->replacements[$n] = $link['link'];
	} else {
	   $ftnt = $link['link'];
	   if (isset($footnotes[$ftnt])) {
	      $trfrm->replacements[$n] = "<A NAME=\"footnote-$ftnt\"></A><A HREF=\"#footnote-rev-$ftnt\">[$ftnt]</A>";
	   } else { // first encounter of [x]
	      $trfrm->replacements[$n] = "<A NAME=\"footnote-rev-$ftnt\"></A><SUP><A HREF=\"#footnote-$ftnt\">[$ftnt]</A></SUP>";
	      $footnotes[$ftnt] = 1;
	   }
	}
	$n++;
      }

      $trfrm->tokencounter = $ntok;
      return $line;
   }


   // replace all URL's with tokens, so we don't confuse them
   // with Wiki words later. Wiki words in URL's break things.
   // URLs preceeded by a '!' are not linked
   function wtt_urls($line, &$trfrm)
   {
      global $AllowedProtocols;

      $n = $ntok = $trfrm->tokencounter;
      $line = wt_tokenize($line, "!?\b($AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]", $trfrm->replacements, $ntok);
      while ($n < $ntok) {
        if($trfrm->replacements[$n][0] == '!')
	   $trfrm->replacements[$n] = substr($trfrm->replacements[$n], 1);
	else
	   $trfrm->replacements[$n] = LinkURL($trfrm->replacements[$n]);
        $n++;
      }

      $trfrm->tokencounter = $ntok;
      return $line;
   }



   // Link InterWiki links
   // These can be protected by a '!' like Wiki words.
   function wtt_interwikilinks($line, &$trfrm)
   {
      global $InterWikiLinkRegexp, $WikiNameRegexp;

      $n = $ntok = $trfrm->tokencounter;
      $line = wt_tokenize($line, "!?(?<![A-Za-z0-9])$InterWikiLinkRegexp:$WikiNameRegexp", $trfrm->replacements, $ntok);
      while ($n < $ntok) {
	 $old = $trfrm->replacements[$n];
	 if ($old[0] == '!') {
	    $trfrm->replacements[$n] = substr($old,1);
	 } else {
	    $trfrm->replacements[$n] = LinkInterWikiLink($old);
	 }
	 $n++;
      }

      $trfrm->tokencounter = $ntok;
      return $line;
   }


   // Link Wiki words (BumpyText)
   // Wikiwords preceeded by a '!' are not linked
   function wtt_bumpylinks($line, &$trfrm)
   {
      global $WikiNameRegexp, $dbi;

      $n = $ntok = $trfrm->tokencounter;
      $line = wt_tokenize($line, "!?$WikiNameRegexp", $trfrm->replacements, $ntok);
      while ($n < $ntok) {
        $old = $trfrm->replacements[$n];
        if ($old[0] == '!') {
	  $trfrm->replacements[$n] = substr($old,1);
	} elseif (IsWikiPage($dbi, $old)) {
	  $trfrm->replacements[$n] = LinkExistingWikiWord($old);
	} else {
	  $trfrm->replacements[$n] = LinkUnknownWikiWord($old);
	}
	$n++;
      }

      $trfrm->tokencounter = $ntok;
      return $line;
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
?>
