<!-- $Id: fullsearch.php,v 1.2 2000-10-20 11:42:52 ahollosi Exp $ -->
<?php
   /*
      Search the text of pages for a match.
      A few too many regexps for my liking, but it works.
   */

   $found = 0;
   $count = 0;

   if(get_magic_quotes_gpc())
      $full = stripslashes($full);

   $result = "<P><B>";
   $result .= sprintf(gettext ("Searching for \"%s\" ....."),
      htmlspecialchars($full));
   $result .= "</B></P>\n<DL>\n";

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
            $matched = preg_replace("|$full|i",
				"${FieldSeparator}OT\\0${FieldSeparator}CT",
                                $pagehash["content"][$j]);
	    $matched = htmlspecialchars($matched);
	    $matched = str_replace("${FieldSeparator}OT", '<b>', $matched);
	    $matched = str_replace("${FieldSeparator}CT", '</b>', $matched);
            $result .= "<dd><small>$matched</small></dd>\n";
            $found += $hits;
         }
      }
   }

   $result .= "</dl>\n<hr noshade>";
   $result .= sprintf (gettext ("%d matches found in %d pages."),
     $found, $count);
   $result .= "\n";
   GeneratePage('MESSAGE', $result, gettext ("Full Text Search Results"), 0);
?>
