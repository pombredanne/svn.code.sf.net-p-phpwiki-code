<?
/*
   Title search:
   This file will return the results of the search. It will: display 
   the logo, the title "Search Results," then a list of all Wiki 
   pages that match seperated by five dots and the text that matched;
   an HR tag, and then a statement:
      32 pages found out of 94 pages searched.
   where the numbers are correct.

   The online classic Wiki has both full search and title search.
   Title search is a good idea; it doesn't come with the script you 
   download from c2.com.
*/

   WikiHeader("Search Results");
   echo "<h1>$LogoImage Search Results</h1>\n";

   $found = $count = 0;

   // from classic wiki: $pat =~ s/[+?.*()[\]{}|\\]/\\$&/g;

   $search = preg_replace("/[+?.*()[\]{}|\\\]/", "", $search);

   // looping through all keys
   $key = dbmfirstkey($dbi);
   while ($key) {
      if (eregi("$search", $key)) {
         $found++;
         echo LinkExistingWikiWord($key), "<br>\n";
      }
      $count++;
      $key = dbmnextkey($dbi, $key);
   }

   echo "<hr>\n";
   echo "$found pages found out of $count titles searched.\n";
   WikiFooter();

?>
