<?php rcs_id('$Id: stdlib.php,v 1.32 2001-02-15 19:32:55 dairiki Exp $');


   /*
      Standard functions for Wiki functionality
         ExitWiki($errormsg)
         WikiURL($pagename, $args, $abs)
	 
         LinkExistingWikiWord($wikiword, $linktext) 
         LinkUnknownWikiWord($wikiword, $linktext) 
         LinkURL($url, $linktext)
         LinkImage($url, $alt)
         LinkInterWikiLink($link, $linktext)
         CookSpaces($pagearray) 
         class Stack (push(), pop(), cnt(), top())
         SetHTMLOutputMode($newmode, $depth)
         UpdateRecentChanges($dbi, $pagename, $isnewpage) 
         ParseAndLink($bracketlink)
         ExtractWikiPageLinks($content)
         LinkRelatedPages($dbi, $pagename)
	 GeneratePage($template, $content, $name, $hash)
   */

function fix_magic_quotes_gpc (&$text)
{
   if (get_magic_quotes_gpc()) {
      $text = stripslashes($text);
   }
   return $text;
}


function get_remote_host () {
   // Apache won't show REMOTE_HOST unless the admin configured it
   // properly. We'll be nice and see if it's there.
   if (getenv('REMOTE_HOST'))
      return getenv('REMOTE_HOST');
   $host = getenv('REMOTE_ADDR');
   if (ENABLE_REVERSE_DNS)
      return gethostbyaddr($host);
   return $host;
}


function arrays_equal ($a, $b) 
{
   if (sizeof($a) != sizeof($b))
      return false;
   for ($i = 0; $i < sizeof($a); $i++)
      if ($a[$i] != $b[$i])
	 return false;
   return true;
}


   function DataURL($url) {
      if (preg_match('@^(\w+:|/)@', $url))
	 return $url;
      return SERVER_URL . DATA_PATH . "/$url";
   }
	  
   function WikiURL($pagename, $args = '') {
      if (is_array($args))
      {
	 reset($args);
	 $enc_args = array();
	 while (list ($key, $val) = each($args)) {
	    $enc_args[] = urlencode($key) . '=' . urlencode($val);
	 }
	 $args = join('&', $enc_args);
      }

      if (USE_PATH_INFO) {
         $url = rawurlencode($pagename);
	 if ($args)
	    $url .= "?$args";
      }
      else {
	 $url = basename(SCRIPT_NAME) .
	     "?pagename=" . rawurlencode($pagename);
	 if ($args)
	    $url .= "&$args";
      }

      return $url;
   }

function StartTag($tag, $args = '')
{
   $s = '';
   if (is_array($args))
   {
      while (list($key, $val) = each($args))
      {
	 if (is_string($val) || is_numeric($val))
	    $s .= sprintf(' %s="%s"', $key, htmlspecialchars($val));
	 else if ($val)
	    $s .= " $key";
      }
   }
   return "<$tag $s>";
}

   
   define('NO_END_TAG_PAT',
	  '/^' . join('|', array('area', 'base', 'basefont',
				 'br', 'col', 'frame',
				 'hr', 'image', 'input',
				 'isindex', 'link', 'meta',
				 'param')) . '$/i');

   function Element($tag, $args = '', $content = '')
   {
      $html = "<$tag";
      if (!is_array($args))
      {
	 $content = $args;
	 $args = false;
      }
      $html = StartTag($tag, $args);
      if (!preg_match(NO_END_TAG_PAT, $tag))
      {
	 $html .= $content;
	 $html .= "</$tag>";//FIXME: newline might not always be desired.
      }
      return $html;
   }

   function QElement($tag, $args = '', $content = '')
   {
      if (is_array($args))
	 return Element($tag, $args, htmlspecialchars($content));
      else
      {
	 $content = $args;
	 return Element($tag, htmlspecialchars($content));
      }
   }
   
   function LinkURL($url, $linktext='') {
      // FIXME: Is this needed (or sufficient?)
      if(ereg("[<>\"]", $url)) {
         return "<b><u>BAD URL -- remove all of &lt;, &gt;, &quot;</u></b>";
      }


      if (empty($linktext))
	 $linktext = QElement('span', array('class' => 'rawurl'), $url);
      else
	 $linktext = htmlspecialchars($linktext);

      return Element('a',
		     array('href' => $url, 'class' => 'linkurl'),
		     $linktext);
   }

   function LinkExistingWikiWord($wikiword, $linktext='') {
      if (empty($linktext))
	 $linktext = QElement('span', array('class' => 'wikiword'), $wikiword);
      else
	 $linktext = htmlspecialchars($linktext);
      
      return Element('a', array('href' => WikiURL($wikiword),
				'class' => 'wikilink'),
		     $linktext);
   }

   function LinkUnknownWikiWord($wikiword, $linktext='') {
      if (empty($linktext))
	 $linktext = QElement('span', array('class' => 'wikiword'), $wikiword);
      else
	 $linktext = htmlspecialchars($linktext);

      return Element('span', array('class' => 'wikiunknown'),
		     Element('u', $linktext) .
		     QElement('a',
			      array('href' => WikiURL($wikiword, array('action' => 'edit')),
				    'class' => 'wikiunknown'),
			      '?'));
   }


   function LinkImage($url, $alt='[External Image]') {
      // FIXME: Is this needed (or sufficient?)
      //  As long as the src in htmlspecialchars()ed I think it's safe.
      if(ereg('[<>"]', $url)) {
         return "<b><u>BAD URL -- remove all of &lt;, &gt;, &quot;</u></b>";
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
      $formargs['method'] = 'post';
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
	    }
	 }

	 $contents .= Element('input', $a);
      }

      $row = Element('td', $contents);
      
      if (!empty($button_text)) {
	 $row .= Element('td', Element('input', array('type' => 'submit',
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
      global $pagename;
      $args = array();
      $page = $pagename;

      if (!preg_match('/^ phpwiki: ([^?]*) [?]? (.*) $/x', $url, $m))
         return "<b><u>BAD phpwiki: URL</u></b>";

      if ($m[1])
	 $page = urldecode($m[1]);
      $qargs = $m[2];
      
      if (!$page && preg_match('/^(diff|edit|links|info|diff)=([^&]+)$/', $qargs, $m))
      {
	 // Convert old style links (to not break diff links in RecentChanges).
	 $page = urldecode($m[2]);
	 $args = array("action" => $m[1]);
      }
      else
      {
	 $args = SplitQueryArgs($qargs);
      }

      if (isset($args['action']) && $args['action'] == 'browse')
	 unset($args['action']);

      if (empty($args['action']))
	 $class = 'wikilink';
      else if (IsSafeAction($args['action']))
	 $class = 'wikiaction';
      else
      {
         // Don't allow administrative links on unlocked pages.
	 // FIXME: Ugh: don't like this...
	 global $pagehash;
	 if (($pagehash['flags'] & FLAG_PAGE_LOCKED) == 0)
	    return QElement('u', array('class' => 'wikiunsafe'),
			    gettext('Lock page to enable link'));

	 $class = 'wikiadmin';
      }
      
      // FIXME: ug, don't like this
      if (preg_match('/=\d*\(/', $qargs))
	 return MakeWikiForm($page, $args, $class, $text);
      else
      {
	 if ($text)
	    $text = htmlspecialchars($text);
	 else
	    $text = QElement('span', array('class' => 'rawurl'), $url);
			     
	 return Element('a', array('href' => WikiURL($page, $args),
				   'class' => $class),
			$text);
      }
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

      if (IsWikiPage($dbi, $URL)) {
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

      $wikilinks = array();
      $numlines = count($content);
      for($l = 0; $l < $numlines; $l++)
      {
	 // remove escaped '['
         $line = str_replace('[[', ' ', $content[$l]);

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
      return $wikilinks;
   }      

   function LinkRelatedPages($dbi, $pagename)
   {
      // currently not supported everywhere
      if(!function_exists('GetWikiPageLinks'))
         return '';

      $links = GetWikiPageLinks($dbi, $pagename);

      $txt = "<b>";
      $txt .= sprintf (gettext ("%d best incoming links:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['in'][$i])) {
            list($name, $score) = $links['in'][$i];
	    $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }

      $txt .= "\n<br><b>";
      $txt .= sprintf (gettext ("%d best outgoing links:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['out'][$i])) {
            list($name, $score) = $links['out'][$i];
	    if(IsWikiPage($dbi, $name))
	       $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }

      $txt .= "\n<br><b>";
      $txt .= sprintf (gettext ("%d most popular nearby:"), NUM_RELATED_PAGES);
      $txt .= "</b>\n";
      for($i = 0; $i < NUM_RELATED_PAGES; $i++) {
         if(isset($links['popular'][$i])) {
            list($name, $score) = $links['popular'][$i];
	    $txt .= LinkExistingWikiWord($name) . " ($score), ";
         }
      }
      
      return $txt;
   }

   
   # GeneratePage() -- takes $content and puts it in the template $template
   # this function contains all the template logic
   #
   # $template ... name of the template (see config.php for list of names)
   # $content ... html content to put into the page
   # $name ... page title
   # $hash ... if called while creating a wiki page, $hash points to
   #           the $pagehash array of that wiki page.

   function GeneratePage($template, $content, $name, $hash)
   {
      global $templates;
      global $datetimeformat, $dbi, $logo, $FieldSeparator;
      global $user, $pagename;
      
      if (!is_array($hash))
         unset($hash);

      function _dotoken ($id, $val, &$page) {
	 global $FieldSeparator;
         $page = str_replace("$FieldSeparator#$id$FieldSeparator#",
				$val, $page);
      }

      function _iftoken ($id, $condition, &$page) {
         global $FieldSeparator;

	 // line based IF directive
	 $lineyes = "$FieldSeparator#IF $id$FieldSeparator#";
	 $lineno = "$FieldSeparator#IF !$id$FieldSeparator#";
         // block based IF directive
	 $blockyes = "$FieldSeparator#IF:$id$FieldSeparator#";
	 $blockyesend = "$FieldSeparator#ENDIF:$id$FieldSeparator#";
	 $blockno = "$FieldSeparator#IF:!$id$FieldSeparator#";
	 $blocknoend = "$FieldSeparator#ENDIF:!$id$FieldSeparator#";

	 if ($condition) {
	    $page = str_replace($lineyes, '', $page);
	    $page = str_replace($blockyes, '', $page);
	    $page = str_replace($blockyesend, '', $page);
	    $page = preg_replace("/$blockno(.*?)$blocknoend/s", '', $page);
	    $page = ereg_replace("${lineno}[^\n]*\n", '', $page);
         } else {
	    $page = str_replace($lineno, '', $page);
	    $page = str_replace($blockno, '', $page);
	    $page = str_replace($blocknoend, '', $page);
	    $page = preg_replace("/$blockyes(.*?)$blockyesend/s", '', $page);
	    $page = ereg_replace("${lineyes}[^\n]*\n", '', $page);
	 }
      }

      $page = join('', file(FindLocalizedFile($templates[$template])));
      $page = str_replace('###', "$FieldSeparator#", $page);

      // valid for all pagetypes
      _iftoken('COPY', isset($hash['copy']), $page);
      _iftoken('LOCK',	(isset($hash['flags']) &&
			($hash['flags'] & FLAG_PAGE_LOCKED)), $page);
      _iftoken('ADMIN', $user->is_admin(), $page);
      _iftoken('ANONYMOUS', !$user->is_authenticated(), $page);

      if (empty($hash['minor_edit_checkbox']))
	  $hash['minor_edit_checkbox'] = '';
      _iftoken('MINOR_EDIT_CHECKBOX', $hash['minor_edit_checkbox'], $page);
      
      _dotoken('MINOR_EDIT_CHECKBOX', $hash['minor_edit_checkbox'], $page);

      _dotoken('USERID', htmlspecialchars($user->id()), $page);
      _dotoken('PAGE', htmlspecialchars($name), $page);
      _dotoken('LOGO', htmlspecialchars(DataURL($logo)), $page);
      _dotoken('CSS_URL', htmlspecialchars(DataURL(CSS_URL)), $page);

      _dotoken('RCS_IDS', $GLOBALS['RCS_IDS'], $page);

      $prefs = $user->getPreferences();
      _dotoken('EDIT_AREA_WIDTH', $prefs['edit_area.width'], $page);
      _dotoken('EDIT_AREA_HEIGHT', $prefs['edit_area.height'], $page);

      // FIXME: Clean up this stuff
      $browse_page = WikiURL($name);
      _dotoken('BROWSE_PAGE', $browse_page, $page);
      $arg_sep = strstr($browse_page, '?') ? '&amp;' : '?';
      _dotoken('ACTION', $browse_page . $arg_sep . "action=", $page);
      _dotoken('BROWSE', WikiURL(''), $page);

      if (USE_PATH_INFO)
	 _dotoken('BASE_URL',
		  SERVER_URL . VIRTUAL_PATH . "/" . WikiURL($pagename), $page);
      else
	 _dotoken('BASE_URL', SERVER_URL . SCRIPT_NAME, $page);
      
      // invalid for messages (search results, error messages)
      if ($template != 'MESSAGE') {
         _dotoken('PAGEURL', rawurlencode($name), $page);
	 if (!empty($hash['lastmodified']))
	    _dotoken('LASTMODIFIED',
		     date($datetimeformat, $hash['lastmodified']), $page);
	 if (!empty($hash['author']))
	    _dotoken('LASTAUTHOR', $hash['author'], $page);
	 if (!empty($hash['version']))
	    _dotoken('VERSION', $hash['version'], $page);
	 if (strstr($page, "$FieldSeparator#HITS$FieldSeparator#")) {
            _dotoken('HITS', GetHitCount($dbi, $name), $page);
	 }
	 if (strstr($page, "$FieldSeparator#RELATEDPAGES$FieldSeparator#")) {
            _dotoken('RELATEDPAGES', LinkRelatedPages($dbi, $name), $page);
	 }
      }

      _dotoken('CONTENT', $content, $page);
      return $page;
   }

function UpdateRecentChanges($dbi, $pagename, $isnewpage)
{
   global $user;
   global $dateformat;
   global $WikiPageStore;

   $recentchanges = RetrievePage($dbi, gettext ("RecentChanges"), $WikiPageStore);

   // this shouldn't be necessary, since PhpWiki loads 
   // default pages if this is a new baby Wiki
   $now = time();
   $today = date($dateformat, $now);

   if (!is_array($recentchanges)) {
      $recentchanges = array('version' => 1,
			     'created' => $now,
			     'lastmodified' => $now - 48 * 4600, // force $isNewDay
			     'flags' => FLAG_PAGE_LOCKED,
			     'author' => $GLOBALS['user']->id());
      $recentchanges['content']
	  = array(gettext("The most recently changed pages are listed below."),
		  '',
		  "____$today " . gettext("(first day for this Wiki)"),
		  '',
		  gettext("Quick title search:"),
		  '[phpwiki:?action=search&searchterm=()]',
		  '----');
   }
   $recentchanges['lastmodified'] = $now;

   if (date($dateformat, $recentchanges['lastmodified']) != $today) {
      $isNewDay = TRUE;
   } else {
      $isNewDay = FALSE;
   }

   $numlines = sizeof($recentchanges['content']);
   $newpage = array();
   $k = 0;

   // scroll through the page to the first date and break
   // dates are marked with "____" at the beginning of the line
   for ($i = 0; $i < $numlines; $i++) {
      if (preg_match("/^____/",
		     $recentchanges['content'][$i])) {
	 break;
      } else {
	 $newpage[$k++] = $recentchanges['content'][$i];
      }
   }

   // if it's a new date, insert it
   $newpage[$k++] = $isNewDay ? "____$today\r"
			      : $recentchanges['content'][$i++];

   $userid = $user->id();

   // add the updated page's name to the array
   if($isnewpage) {
      $newpage[$k++] = "* [$pagename] (new) ..... $userid\r";
   } else {
      $diffurl = "phpwiki:" . rawurlencode($pagename) . "?action=diff";
      $newpage[$k++] = "* [$pagename] ([diff|$diffurl]) ..... $userid\r";
   }
   if ($isNewDay)
      $newpage[$k++] = "\r";

   // copy the rest of the page into the new array
   // and skip previous entry for $pagename
   $pagename = preg_quote($pagename);
   for (; $i < $numlines; $i++) {
      if (!preg_match("|\[$pagename\]|", $recentchanges['content'][$i])) {
	 $newpage[$k++] = $recentchanges['content'][$i];
      }
   }

   $recentchanges['content'] = $newpage;

   InsertPage($dbi, gettext ("RecentChanges"), $recentchanges);
}

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
