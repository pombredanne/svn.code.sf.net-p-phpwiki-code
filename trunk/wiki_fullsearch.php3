<!-- $Id: wiki_fullsearch.php3,v 1.6 2000-06-18 15:12:13 ahollosi Exp $ -->
<?
   /*
      Search the text of pages for a match.
      A few too many regexps for my liking, but it works.
   */

   $found = $count = 0;

   if(get_magic_quotes_gpc())
      $full = stripslashes($full);

   $result = "<P><B>Searching for \"" . htmlspecialchars($full) .
		"\" ....</B></P>\n<DL>\n";

   // quote regexp chars
   $full = preg_quote($full);

   // search matching pages
   $query = InitFullSearch($dbi, $full);
   while ($page = FullSearchNextMatch($dbi, $query)) {
      $pagename = $page['name'];
      $pagehash = $page['hash'];

      $result .= "<DT><B>" . LinkExistingWikiWord($pagename) . "</B>\n";
      $count++;

      // print out all matching lines, highlighting the match
      for ($j = 0; $j < (count($pagehash["content"])); $j++) {
         if (preg_match("/$full/i", $pagehash["content"][$j], $pmatches)) {
            $matched = preg_replace("/$full/i", "<b>\\0</b>",
                                    $pagehash["content"][$j]);
            $result .= "<dd><small>$matched</small></dd>\n";
            $found += count($pmatches);
         }
      }
   }

   $result .= "</dl>\n<hr noshade>$found matches found in $count pages.\n";
   GeneratePage('MESSAGE', $result, "Full Text Search Results", 0);
?>
