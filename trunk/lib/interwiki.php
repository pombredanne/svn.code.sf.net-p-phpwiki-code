<?php rcs_id("$Id: interwiki.php,v 1.1 2001-02-08 10:39:41 ahollosi Exp $");

   function generate_interwikimap_and_regexp()
   {
      global $interwikimap_file, $InterWikiLinkRegexp, $interwikimap;

      $intermap_data = file($interwikimap_file);
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
?>
