<!-- $Id: wiki_fullsearch.php3,v 1.8 2000-07-04 21:26:43 ahollosi Exp $ -->
<?
   /*
      Search the text of pages for a match.
      A few too many regexps for my liking, but it works.
   */

   $found = 0;
   $count = 0;

   if(get_magic_quotes_gpc())
      $full = stripslashes($full);

   $result = "<P><B>Searching for \"" . htmlspecialchars($full) .
		"\" ....</B></P>\n<DL>\n";

   // quote regexp chars
   $full = preg_quote($full);

   // search matching pages
   $query = InitFullSearch($dbi, $full);
   while ($pagehash = FullSearchNextMatch($dbi, $query)) {
      $result .= "<DT><B>" . LinkExistingWikiWord($pagehash["pagename"]) . "</B>\n";
      $count++;

      // print out all matching lines, highlighting the match
      for ($j = 0; $j < (count($pagehash["content"])); $j++) {
         if ($hits = preg_match_all("|$full|i", $pagehash["content"][$j], $dummy)) {
            $matched = preg_replace("|$full|i", "<b>\\0</b>",
                                    $pagehash["content"][$j]);
            $result .= "<dd><small>$matched</small></dd>\n";
            $found += $hits;
         }
      }
   }

   $result .= "</dl>\n<hr noshade>$found matches found in $count pages.\n";
   GeneratePage('MESSAGE', $result, "Full Text Search Results", 0);
?>