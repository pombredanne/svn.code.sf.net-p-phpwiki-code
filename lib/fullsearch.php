<?php
   // Search the text of pages for a match.
   rcs_id('$Id: fullsearch.php,v 1.6 2001-02-12 01:43:10 dairiki Exp $');

   if (empty($searchterm))
      $searchterm = '';		// FIXME: do something better here?

   fix_magic_quotes_gpc($searchterm);

   $html = "<P><B>"
	   . sprintf(gettext ("Searching for \"%s\" ....."),
		   htmlspecialchars($searchterm))
	   . "</B></P>\n<DL>\n";

   // search matching pages
   $query = InitFullSearch($dbi, $searchterm);

   // quote regexp chars (space are treated as "or" operator)
   $qterm = preg_replace("/\s+/", "|", preg_quote($searchterm));

   $found = 0;
   $count = 0;
   while ($pagehash = FullSearchNextMatch($dbi, $query)) {
      $html .= "<DT><B>" . LinkExistingWikiWord($pagehash["pagename"]) . "</B>\n";
      $count++;

      // print out all matching lines, highlighting the match
      for ($j = 0; $j < (count($pagehash["content"])); $j++) {
         if ($hits = preg_match_all("/$qterm/i", $pagehash["content"][$j], $dummy)) {
            $matched = preg_replace("/$qterm/i",
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

   echo GeneratePage('MESSAGE', $html, gettext ("Full Text Search Results"), 0);
?>
