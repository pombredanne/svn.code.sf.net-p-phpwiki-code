<?php rcs_id('$Id: interwiki.php,v 1.3 2001-02-14 05:22:49 dairiki Exp $');

function generate_interwikimap_and_regexp()
{
   global $interwikimap_file, $InterWikiLinkRegexp, $interwikimap;

   $intermap_data = file(INTERWIKI_MAP_FILE);
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

   list( $wiki, $page ) = split( ":", $link );

   $url = $interwikimap[$wiki] . urlencode($page);

   if ($linktext)
      $linktext = htmlspecialchars($linktext);
   else
      $linktext = Element('span', array('class' => 'interwiki'),
			  htmlspecialchars("$wiki:") .
			  QElement('span', array('class' => 'wikiword'), $page));
   
   return Element('a', array('href' => $url,
			     'class' => 'interwikilink'),
		  $linktext);
}

// Link InterWiki links
// These can be protected by a '!' like Wiki words.
function wtt_interwikilinks($match, &$trfrm)
{
   global $InterWikiLinkRegexp, $WikiNameRegexp;

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
