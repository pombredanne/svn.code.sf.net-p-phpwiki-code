<!-- $Id: wiki_search.php3,v 1.6 2000-08-29 02:37:42 aredridel Exp $ -->
<?php
   // Title search: returns pages having a name matching the search term

   $found = 0;

   if(get_magic_quotes_gpc())
      $search = stripslashes($search);

   $result = "<P><B>Searching for \"" . htmlspecialchars($search) .
		"\" ....</B></P>\n";

   // quote regexp chars
   $search = preg_quote($search);

   // search matching pages
   $query = InitTitleSearch($dbi, $search);
   while ($page = TitleSearchNextMatch($dbi, $query)) {
      $found++;
      $result .= LinkExistingWikiWord($page) . "<br>\n";
   }

   $result .= "<hr noshade>\n$found pages match your query.\n";
   GeneratePage('MESSAGE', $result, "Title Search Results", 0);
?>
