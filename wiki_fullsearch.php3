<?
   /*
      Search the text of pages for a match.
      A few too many regexps for my liking, but it works.
   */

   WikiHeader("Search Results");
   echo "<h1>$LogoImage Search Results</h1>\n";

   $found = $count = 0;

   // from classic wiki: $pat =~ s/[+?.*()[\]{}|\\]/\\$&/g;

   $full = preg_replace("/[+?.*()[\]{}|\\\]/", "", $full);

   // looping through all keys
   $key = dbmfirstkey($dbi);

   while ($key) {
      $pagedata = dbmfetch($dbi, $key);   

      // test the serialized data first, before going further
      if (preg_match("/$full/i", $pagedata)) {

         echo "<h3>", LinkExistingWikiWord($key), "</h3>\n";
         $pagehash = unserialize($pagedata);

         // print out all matching lines, highlighting the match
         for ($j = 0; $j < (count($pagehash["text"])); $j++) {
            if (preg_match("/$full/i", $pagehash["text"][$j], $pmatches)) {
               $matched = preg_replace("/$full/i", "<b>\\0</b>",
                                       $pagehash["text"][$j]);
               $found += count($pmatches);
               echo "<li>", $matched, "</li>\n";
            }
         }
         echo "<hr>\n";
      }
      $count++;
      $key = dbmnextkey($dbi, $key);
   }

   echo "$found matches found out of $count pages searched.\n";
   WikiFooter();


?>
