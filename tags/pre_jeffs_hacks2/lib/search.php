<?php

// Title search: returns pages having a name matching the search term

rcs_id('$Id: search.php,v 1.7 2001-07-07 17:44:25 wainstead Exp $');

if (empty($searchterm)) {
    $searchterm = '';		// FIXME: do something better here?
}

fix_magic_quotes_gpc($searchterm);

$html = "<P><B>"
      . sprintf(gettext ("Searching for \"%s\" ....."),
        htmlspecialchars($searchterm))
      . "</B></P>\n";

// quote regexp chars
$search = preg_quote($searchterm);


// search matching pages
$query = InitTitleSearch($dbi, $searchterm);
$found = 0;

while ($page = TitleSearchNextMatch($dbi, $query)) {
    $found++;
    $html .= LinkExistingWikiWord($page) . "<br>\n";
}

$html .= "<hr noshade>\n"
      . sprintf(gettext ("%d pages match your query."), $found)
      . "\n";

echo GeneratePage('MESSAGE', $html, gettext ("Title Search Results"), 0);

?>
