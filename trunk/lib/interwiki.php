<?php rcs_id('$Id: interwiki.php,v 1.2 2001-02-12 01:43:10 dairiki Exp $');

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
   return LinkURL($url, $linktext ? $linktext : $link);
}

// Link InterWiki links
// These can be protected by a '!' like Wiki words.
function wtt_interwikilinks($line, &$trfrm)
{
   global $InterWikiLinkRegexp, $WikiNameRegexp;

   $n = $ntok = $trfrm->tokencounter;

   // FIXME: perhaps WikiNameRegexp is a bit too restrictive?
   $line = wt_tokenize($line, "!?(?<![A-Za-z0-9])$InterWikiLinkRegexp:$WikiNameRegexp",
		       $trfrm->replacements, $ntok);
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

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
