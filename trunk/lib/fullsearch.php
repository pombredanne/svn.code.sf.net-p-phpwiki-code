<?php
   // Search the text of pages for a match.
   rcs_id('$Id: fullsearch.php,v 1.4 2000-12-30 21:09:13 ahollosi Exp $');

   if(get_magic_quotes_gpc())
      $full = stripslashes($full);

   $html = "<P><B>"
	   . sprintf(gettext ("Searching for \"%s\" ....."),
		   htmlspecialchars($full))
	   . "</B></P>\n<DL>\n";

   // search matching pages
   $query = InitFullSearch($dbi, $full);

   // quote regexp chars (space are treated as "or" operator)
   $full = preg_replace("/\s+/", "|", preg_quote($full));

   $found = 0;
   $count = 0;
   while ($pagehash = FullSearchNextMatch($dbi, $query)) {
      $html .= "<DT><B>" . LinkExistingWikiWord($pagehash["pagename"]) . "</B>\n";
      $count++;

      // print out all matching lines, highlighting the match
      for ($j = 0; $j < (count($pagehash["content"])); $j++) {
         if ($hits = preg_match_all("/$full/i", $pagehash["content"][$j], $dummy)) {
            $matched = preg_replace("/$full/i",
				"${FieldSeparator}OT\\0${FieldSeparator}CT",
                                $pagehash["content"][$j]);
	    $matched = htmlspecialchars($matched);
	    $matched = str_replace("${FieldSeparator}OT", '<b>', $matched);
	    $matched = str_replace("${FieldSeparator}CT", '</b>', $matched);
            $html .= "<dd><small>$matched</small></dd>\n";
            $found += $hits;
         }
      }
   }

   $html .= "</dl>\n<hr noshade>"
	    . sprintf (gettext ("%d matches found in %d pages."),
		       $found, $count)
	    . "\n";

   GeneratePage('MESSAGE', $html, gettext ("Full Text Search Results"), 0);
?>
