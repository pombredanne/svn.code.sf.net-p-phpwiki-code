<?
   /*
      Search the text of pages for a match.
      A few too many regexps for my liking, but it works.
   */

   echo WikiHeader("Search Results");
   echo "<h1>$LogoImage Search Results</h1>\n";

   $found = $count = 0;

   // from classic wiki: $pat =~ s/[+?.*()[\]{}|\\]/\\$&/g;
   // disallow _'_ and _%_ too, _\_ have to be doubled
   $full = preg_replace("/[+?.*()'%[\\]{}|\\\\]/", "", $full);

   // search matching pages
   $query = InitFullSearch($dbi, $full);
   while ($page = FullSearchNextMatch($dbi, $query)) {
      $pagename = $page['name'];
      $pagehash = $page['hash'];

      echo "<h3>", LinkExistingWikiWord($pagename), "</h3>\n";
      $count++;

      // print out all matching lines, highlighting the match
      for ($j = 0; $j < (count($pagehash["text"])); $j++) {
         if (preg_match("/$full/i", $pagehash["text"][$j], $pmatches)) {
            $matched = preg_replace("/$full/i", "<b>\\0</b>",
                                    $pagehash["text"][$j]);
            echo "<li>", $matched, "</li>\n";
            $found += count($pmatches);
         }
      }
      echo "<hr>\n";
   }

   echo "$found matches found in $count pages.\n";
   echo WikiFooter();
?>
