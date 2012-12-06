<!-- $Id: wiki_search.php3,v 1.4 2000-06-05 21:46:50 wainstead Exp $ -->
<?
/*
   Title search:
   This file will return the results of the search. It will: display 
   the logo, the title "Search Results," then a list of all Wiki 
   pages that match seperated by five dots and the text that matched;
   an HR tag, and then a statement:
      32 pages match your query.
   where the number is correct.

   The online classic Wiki has both full search and title search.
   Title search is a good idea; it doesn't come with the script you 
   download from c2.com.
*/

   echo WikiHeader("Search Results");
   echo "<h1>$LogoImage Search Results</h1>\n";

   $found = 0;

   if(get_magic_quotes_gpc())
      $full = stripslashes($full);

   // quote regexp chars
   $search = preg_quote($search);

   // search matching pages
   $query = InitTitleSearch($dbi, $search);
   while ($page = TitleSearchNextMatch($dbi, $query)) {
      $found++;
      echo LinkExistingWikiWord($page), "<br>\n";
   }

   echo "<hr>\n";
   echo "$found pages match your query.\n";
   echo WikiFooter();

?>