<?php rcs_id('$Id: stdlib.php,v 1.51 2001-12-03 23:12:04 carstenklapp Exp $');

   /*
      Standard functions for Wiki functionality
         WikiURL($pagename, $args, $abs)
         LinkWikiWord($wikiword, $linktext) 
         LinkExistingWikiWord($wikiword, $linktext) 
         LinkUnknownWikiWord($wikiword, $linktext) 
         LinkURL($url, $linktext)
         LinkImage($url, $alt)
         LinkInterWikiLink($link, $linktext)
         CookSpaces($pagearray) 
         class Stack (push(), pop(), cnt(), top())
         UpdateRecentChanges($dbi, $pagename, $isnewpage) 
         ParseAndLink($bracketlink)
         ExtractWikiPageLinks($content)
         LinkRelatedPages($dbi, $pagename)
   */


   function DataURL($url) {
      if (preg_match('@^(\w+:|/)@', $url))
	 return $url;
      return SERVER_URL . DATA_PATH . "/$url";
      // Beginnings of theme support by Carsten Klapp:
      /*
      global $theme;
      return SERVER_URL . DATA_PATH . "/templates/$theme/$url";
      */
   }
	  
function WikiURL($pagename, $args = '', $get_abs_url = false) {
    if (is_array($args)) {
        $enc_args = array();
        foreach  ($args as $key => $val) {
            $enc_args[] = urlencode($key) . '=' . urlencode($val);
        }
        $args = join('&', $enc_args);
    }

    if (USE_PATH_INFO) {
        $url = $get_abs_url ? SERVER_URL . VIRTUAL_PATH . "/" : '';
        $url .= rawurlencode($pagename);
        if ($args)
            $url .= "?$args";
    }
    else {
        $url = $get_abs_url ? SERVER_URL . SCRIPT_NAME : basename(SCRIPT_NAME);
        $url .= "?pagename=" . rawurlencode($pagename);
        if ($args)
            $url .= "&$args";
    }

    return $url;
}

define('NO_END_TAG_PAT',
       '/^' . join('|', array('area', 'base', 'basefont',
                              'br', 'col', 'frame',
                              'hr', 'img', 'input',
                              'isindex', 'link', 'meta',
                              'param')) . '$/i');

function StartTag($tag, $args = '')
{
   $s = "<$tag";
   if (is_array($args))
   {
      while (list($key, $val) = each($args))
      {
	 if (is_string($val) || is_numeric($val))
	    $s .= sprintf(' %s="%s"', $key, htmlspecialchars($val));
	 else if ($val)
	    $s .= " $key=\"$key\"";
      }
   }
   return "$s>";
}

   
function Element($tag, $args = '', $content = '')
{
    $html = "<$tag";
    if (!is_array($args)) {
        $content = $args;
        $args = false;
    }
    $html = StartTag($tag, $args);
    if (preg_match(NO_END_TAG_PAT, $tag)) {
        assert(! $content);
        return preg_replace('/>$/', " />", $html);
    } else {
        $html .= $content;
        $html .= "</$tag>";//FIXME: newline might not always be desired.
    }
    return $html;
}

function QElement($tag, $args = '', $content = '')
{
    if (is_array($args))
        return Element($tag, $args, htmlspecialchars($content));
    else {
        $content = $args;
        return Element($tag, htmlspecialchars($content));
    }
}
   
   function LinkURL($url, $linktext='') {
      // FIXME: Is this needed (or sufficient?)
      if(ereg("[<>\"]", $url)) {
          return Element('strong',
                         QElement('u', array('class' => 'baduri'),
                                  'BAD URL -- remove all of <, >, "'));
      }

      if (empty($linktext)) {
          $linktext = $url;
          $class = 'rawurl';
      }
      else {
          $class = 'namedurl';
      }

      if (!defined('USE_LINK_ICONS')) {
          return QElement('a',
                     array('href' => $url, 'class' => $class),
                     $linktext);
      } else {
            //ideally the link image would be specified by a map file
            //similar to the interwiki.map
            $linkproto = substr($url, 0, strrpos($url, ":"));
            if ($linkproto == "mailto") {
                $linkimg = "/images/mailto.png";
            } elseif ($linkproto == "http") { 
                $linkimg = "/images/http.png";
            } elseif ($linkproto == "https") { 
                $linkimg = "/images/https.png";
            } elseif ($linkproto == "ftp") { 
                $linkimg = "/images/ftp.png";
            } else {
                $linkimg = "/images/http.png";
      }
      return Element('a',
                 array('href' => $url, 'class' => $class),
                 Element('img', array('src' => DATA_PATH . $linkimg, 'alt' => $linkproto)) ." ". $linktext);
    }
   }

function LinkWikiWord($wikiword, $linktext='') {
    global $dbi;
    if ($dbi->isWikiPage($wikiword))
        return LinkExistingWikiWord($wikiword, $linktext);
    else
        return LinkUnknownWikiWord($wikiword, $linktext);
}

    
   function LinkExistingWikiWord($wikiword, $linktext='') {
      if (empty($linktext)) {
          $linktext = $wikiword;
          if (defined("autosplit_wikiwords"))
              $linktext=split_pagename($linktext);
         $class = 'wiki';
      }
      else
          $class = 'named-wiki';
      
      return QElement('a', array('href' => WikiURL($wikiword),
                                 'class' => $class),
		     $linktext);
   }

   function LinkUnknownWikiWord($wikiword, $linktext='') {
      if (empty($linktext)) {
          $linktext = $wikiword;
          if (defined("autosplit_wikiwords"))
              $linktext=split_pagename($linktext);
          $class = 'wikiunknown';
      }
      else {
          $class = 'named-wikiunknown';
      }

      return Element('span', array('class' => $class),
		     QElement('a',
                              array('href' => WikiURL($wikiword, array('action' => 'edit'))),
                              '?')
                     . Element('u', $linktext));
   }

   function LinkImage($url, $alt='[External Image]') {
      // FIXME: Is this needed (or sufficient?)
      //  As long as the src in htmlspecialchars()ed I think it's safe.
      if(ereg('[<>"]', $url)) {
         return Element('strong',
                        QElement('u', array('class' => 'baduri'),
                                 'BAD URL -- remove all of <, >, "'));
      }
      return Element('img', array('src' => $url, 'alt' => $alt));
   }

   // converts spaces to tabs
   function CookSpaces($pagearray) {
      return preg_replace("/ {3,8}/", "\t", $pagearray);
   }


   class Stack {
      var $items = array();
      var $size = 0;

      function push($item) {
         $this->items[$this->size] = $item;
         $this->size++;
         return true;
      }  
   
      function pop() {
         if ($this->size == 0) {
            return false; // stack is empty
         }  
         $this->size--;
         return $this->items[$this->size];
      }  
   
      function cnt() {
         return $this->size;
      }  

      function top() {
         if($this->size)
            return $this->items[$this->size - 1];
         else
            return '';
      }  

   }  
   // end class definition


   function MakeWikiForm ($pagename, $args, $class, $button_text = '')
   {
      $formargs['action'] = USE_PATH_INFO ? WikiURL($pagename) : SCRIPT_NAME;
      $formargs['method'] = 'get';
      $formargs['class'] = $class;
      
      $contents = '';
      $input_seen = 0;
      
      while (list($key, $val) = each($args))
      {
	 $a = array('name' => $key, 'value' => $val, 'type' => 'hidden');
	 
	 if (preg_match('/^ (\d*) \( (.*) \) ((upload)?) $/xi', $val, $m))
	 {
	    $input_seen++;
	    $a['type'] = 'text';
	    $a['size'] = $m[1] ? $m[1] : 30;
	    $a['value'] = $m[2];
	    if ($m[3])
	    {
	       $a['type'] = 'file';
	       $formargs['enctype'] = 'multipart/form-data';
	       $contents .= Element('input',
				    array('name' => 'MAX_FILE_SIZE',
					  'value' => MAX_UPLOAD_SIZE,
					  'type' => 'hidden'));
               $formargs['method'] = 'post';
	    }
	 }

	 $contents .= Element('input', $a);
      }

      $row = Element('td', $contents);
      
      if (!empty($button_text)) {
	 $row .= Element('td', Element('input', array('type' => 'submit',
                                                      'class' => 'button',
						      'value' => $button_text)));
      }

      return Element('form', $formargs,
		     Element('table', array('cellspacing' => 0, 'cellpadding' => 2, 'border' => 0),
			     Element('tr', $row)));
   }

   function SplitQueryArgs ($query_args = '') 
   {
      $split_args = split('&', $query_args);
      $args = array();
      while (list($key, $val) = each($split_args))
	 if (preg_match('/^ ([^=]+) =? (.*) /x', $val, $m))
	    $args[$m[1]] = $m[2];
      return $args;
   }
   
function LinkPhpwikiURL($url, $text = '') {
	$args = array();

	if (!preg_match('/^ phpwiki: ([^?]*) [?]? (.*) $/x', $url, $m))
            return Element('strong',
                           QElement('u', array('class' => 'baduri'),
                                    'BAD phpwiki: URL'));
	if ($m[1])
		$pagename = urldecode($m[1]);
	$qargs = $m[2];
      
	if (empty($pagename) && preg_match('/^(diff|edit|links|info)=([^&]+)$/', $qargs, $m)) {
		// Convert old style links (to not break diff links in RecentChanges).
		$pagename = urldecode($m[2]);
		$args = array("action" => $m[1]);
	}
	else {
		$args = SplitQueryArgs($qargs);
	}

	if (empty($pagename))
		$pagename = $GLOBALS['pagename'];

	if (isset($args['action']) && $args['action'] == 'browse')
		unset($args['action']);
        /*FIXME:
	if (empty($args['action']))
		$class = 'wikilink';
	else if (is_safe_action($args['action']))
		$class = 'wikiaction';
        */
	if (empty($args['action']) || is_safe_action($args['action']))
                $class = 'wikiaction';
	else {
		// Don't allow administrative links on unlocked pages.
		// FIXME: Ugh: don't like this...
		global $dbi;
		$page = $dbi->getPage($GLOBALS['pagename']);
		if (!$page->get('locked'))
			return QElement('u', array('class' => 'wikiunsafe'),
							gettext('Lock page to enable link'));

		$class = 'wikiadmin';
	}
      
	// FIXME: ug, don't like this
	if (preg_match('/=\d*\(/', $qargs))
		return MakeWikiForm($pagename, $args, $class, $text);
	if ($text)
		$text = htmlspecialchars($text);
	else
		$text = QElement('span', array('class' => 'rawurl'), $url);

	return Element('a', array('href' => WikiURL($pagename, $args),
							  'class' => $class),
				   $text);
}

   function ParseAndLink($bracketlink) {
      global $dbi, $AllowedProtocols, $InlineImages;
      global $InterWikiLinkRegexp;

      // $bracketlink will start and end with brackets; in between
      // will be either a page name, a URL or both separated by a pipe.

      // strip brackets and leading space
      preg_match("/(\[\s*)(.+?)(\s*\])/", $bracketlink, $match);
      // match the contents 
      preg_match("/([^|]+)(\|)?([^|]+)?/", $match[2], $matches);

      if (isset($matches[3])) {
         // named link of the form  "[some link name | http://blippy.com/]"
         $URL = trim($matches[3]);
         $linkname = trim($matches[1]);
	 $linktype = 'named';
      } else {
         // unnamed link of the form "[http://blippy.com/] or [wiki page]"
         $URL = trim($matches[1]);
	 $linkname = '';
	 $linktype = 'simple';
      }

      if ($dbi->isWikiPage($URL)) {
         $link['type'] = "wiki-$linktype";
         $link['link'] = LinkExistingWikiWord($URL, $linkname);
      } elseif (preg_match("#^($AllowedProtocols):#", $URL)) {
        // if it's an image, embed it; otherwise, it's a regular link
         if (preg_match("/($InlineImages)$/i", $URL)) {
	    $link['type'] = "image-$linktype";
            $link['link'] = LinkImage($URL, $linkname);
         } else {
	    $link['type'] = "url-$linktype";
            $link['link'] = LinkURL($URL, $linkname);
            
        
           $link['link'] = LinkURL($URL, $linkname);
        

	 }
      } elseif (preg_match("#^phpwiki:(.*)#", $URL, $match)) {
	 $link['type'] = "url-wiki-$linktype";
	 $link['link'] = LinkPhpwikiURL($URL, $linkname);
      } elseif (preg_match("#^\d+$#", $URL)) {
         $link['type'] = "footnote-$linktype";
	 $link['link'] = $URL;
      } elseif (function_exists('LinkInterWikiLink') &&
		preg_match("#^$InterWikiLinkRegexp:#", $URL)) {
	 $link['type'] = "interwiki-$linktype";
	 $link['link'] = LinkInterWikiLink($URL, $linkname);
      } else {
	 $link['type'] = "wiki-unknown-$linktype";
         $link['link'] = LinkUnknownWikiWord($URL, $linkname);
      }

      return $link;
   }


function ExtractWikiPageLinks($content)
{
    global $WikiNameRegexp;
    
    if (is_string($content))
        $content = explode("\n", $content);

    $wikilinks = array();
    foreach ($content as $line) {
        // remove plugin code
        $line = preg_replace('/<\?plugin\s+\w.*?\?>/', '', $line);
        // remove escaped '['
        $line = str_replace('[[', ' ', $line);

  // bracket links (only type wiki-* is of interest)
  $numBracketLinks = preg_match_all("/\[\s*([^\]|]+\|)?\s*(.+?)\s*\]/", $line, $brktlinks);
  for ($i = 0; $i < $numBracketLinks; $i++) {
     $link = ParseAndLink($brktlinks[0][$i]);
     if (preg_match("#^wiki#", $link['type']))
        $wikilinks[$brktlinks[2][$i]] = 1;

         $brktlink = preg_quote($brktlinks[0][$i]);
         $line = preg_replace("|$brktlink|", '', $line);
  }

      // BumpyText old-style wiki links
      if (preg_match_all("/!?$WikiNameRegexp/", $line, $link)) {
         for ($i = 0; isset($link[0][$i]); $i++) {
            if($link[0][$i][0] <> '!')
               $wikilinks[$link[0][$i]] = 1;
     }
      }
   }
   return array_keys($wikilinks);
}      

   function LinkRelatedPages($dbi, $pagename)
   {
      // currently not supported everywhere
      if(!function_exists('GetWikiPageLinks'))
         return '';

      //FIXME: fix or toss?
      $links = GetWikiPageLinks($dbi, $pagename);

      $txt = QElement('strong',
                      sprintf (gettext ("%d best incoming links:"), NUM_RELATED_PAGES));
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['in'][$i])) {
            list($name, $score) = $links['in'][$i];
	    $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }
      
      $txt .= "\n" . Element('br');
      $txt .= Element('strong',
                      sprintf (gettext ("%d best outgoing links:"), NUM_RELATED_PAGES));
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['out'][$i])) {
            list($name, $score) = $links['out'][$i];
	    if($dbi->isWikiPage($name))
	       $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }

      $txt .= "\n" . Element('br');
      $txt .= Element('strong',
                      sprintf (gettext ("%d most popular nearby:"), NUM_RELATED_PAGES));
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['popular'][$i])) {
            list($name, $score) = $links['popular'][$i];
	    $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }
      
      return $txt;
   }


/**
 * Split WikiWords in page names.
 *
 * It has been deemed useful to split WikiWords (into "Wiki Words")
 * in places like page titles.  This is rumored to help search engines
 * quite a bit.
 *
 * @param $page string The page name.
 *
 * @return string The split name.
 */
function split_pagename ($page) {
    
    if (preg_match("/\s/", $page))
        return $page;           // Already split --- don't split any more.

    // FIXME: this algorithm is Anglo-centric.
    static $RE;
    if (!isset($RE)) {
        // This mess splits between a lower-case letter followed by either an upper-case
	// or a numeral; except that it wont split the prefixes 'Mc', 'De', or 'Di' off
        // of their tails.
			$RE[] = '/([[:lower:]])((?<!Mc|De|Di)[[:upper:]]|\d)/';
        // This the single-letter words 'I' and 'A' from any following capitalized words.
        $RE[] = '/(?: |^)([AI])([[:upper:]])/';
	// Split numerals from following letters.
        $RE[] = '/(\d)([[:alpha:]])/';

        foreach ($RE as $key => $val)
            $RE[$key] = pcre_fix_posix_classes($val);
    }

    foreach ($RE as $regexp)
        $page = preg_replace($regexp, '\\1 \\2', $page);
    return $page;
}

function NoSuchRevision ($page, $version) {
    $html = Element('p', QElement('strong', gettext("Bad Version"))) . "\n";
    $html .= QElement('p',
                      sprintf(gettext("I'm sorry.  Version %d of %s is not in my database."),
                              $version, $page->getName())) . "\n";

    include_once('lib/Template.php');
    echo GeneratePage('MESSAGE', $html, gettext("Bad Version"));
    ExitWiki ("");
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
