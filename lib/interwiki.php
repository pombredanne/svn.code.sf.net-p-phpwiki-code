<?php rcs_id('$Id: interwiki.php,v 1.6 2001-09-18 19:16:23 dairiki Exp $');

function generate_interwikimap_and_regexp()
{
   global $interwikimap_file, $InterWikiLinkRegexp, $interwikimap;

   $intermap_data = file(INTERWIKI_MAP_FILE, 1);
   $wikiname_regexp = "";
   for ($i=0; $i<count($intermap_data); $i++)
   {
      list( $wiki, $inter_url ) = split(' ', chop($intermap_data[$i]));
      $interwikimap[$wiki] = $inter_url;
      if ($wikiname_regexp)
	 $wikiname_regexp .= "|";
      $wikiname_regexp .= $wiki;
   }

   $InterWikiLinkRegexp = "($wikiname_regexp)";
}

generate_interwikimap_and_regexp();

function LinkInterWikiLink($link, $linktext='')
{
   global $interwikimap;

   list( $wiki, $page ) = split( ":", $link, 2 );

   $url = $interwikimap[$wiki];

   // Urlencode page only if it's a query arg.
   if (strstr($url, '?'))
      $url .= rawurlencode($page);
   else
      $url .= $page;

   if ($linktext) {
      $linktext = htmlspecialchars($linktext);
      $class = 'named-interwiki';
   }
   else {
      $linktext = ( htmlspecialchars("$wiki:")
                    . QElement('span', array('class' => 'wikipage'), $page) );
      $class = 'interwiki';
   }
   
   return Element('a', array('href' => $url,
			     'class' => $class),
		  $linktext);
}

// Link InterWiki links
// These can be protected by a '!' like Wiki words.
function wtt_interwikilinks($match, &$trfrm)
{
   if ($match[0] == "!")
      return htmlspecialchars(substr($match,1));
   return LinkInterWikiLink($match);
}

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
