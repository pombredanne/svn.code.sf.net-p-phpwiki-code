<!-- $Id: wiki_search.php3,v 1.5.2.1 2000-07-21 18:29:07 dairiki Exp $ -->
<?
   // Title search: returns pages having a name matching the search term

   $found = 0;

   $result = "<P><B>Searching for \"" . htmlspecialchars($search_term) .
		"\" ....</B></P>\n";

   // quote regexp chars
   $term = preg_quote($search_term);

   // search matching pages
   $query = InitTitleSearch($dbi, $term);
   while ($page = TitleSearchNextMatch($dbi, $query)) {
      $found++;
      $result .= LinkExistingWikiWord($page) . "<br>\n";
   }

   $result .= "<hr noshade>\n$found pages match your query.\n";
   GeneratePage('MESSAGE', $result, "Title Search Results", 0);
?>
