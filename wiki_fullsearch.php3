<!-- $Id: wiki_fullsearch.php3,v 1.8.2.1 2000-07-21 18:29:07 dairiki Exp $ -->
<?
   /*
      Search the text of pages for a match.
      A few too many regexps for my liking, but it works.
   */

   $found = 0;
   $count = 0;

   $result = "<P><B>Searching for \"" . htmlspecialchars($search_term) .
		"\" ....</B></P>\n<DL>\n";

   // quote regexp chars
   $term = preg_quote($search_term);

   // search matching pages
   $query = InitFullSearch($dbi, $term);
   while ($pagehash = FullSearchNextMatch($dbi, $query)) {
      $result .= "<DT><B>" . LinkExistingWikiWord($pagehash["pagename"]) . "</B>\n";
      $count++;

      // print out all matching lines, highlighting the match
      for ($j = 0; $j < (count($pagehash["content"])); $j++) {
         if ($hits = preg_match_all("|$term|i", $pagehash["content"][$j], $dummy)) {
            $matched = preg_replace("|$term|i", "<b>\\0</b>",
                                    $pagehash["content"][$j]);
            $result .= "<dd><small>$matched</small></dd>\n";
            $found += $hits;
         }
      }
   }

   $result .= "</dl>\n<hr noshade>$found matches found in $count pages.\n";
   GeneratePage('MESSAGE', $result, "Full Text Search Results", 0);
?>
