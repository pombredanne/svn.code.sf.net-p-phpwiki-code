<?php
rcs_id('$Id: search.php,v 1.9 2001-09-19 03:24:36 wainstead Exp $');
// Title search: returns pages having a name matching the search term

require_once('lib/Template.php');
require_once('lib/TextSearchQuery.php');

$search_title = gettext("Title Search");
$search_descrip = sprintf(gettext("Title search results for '%s'"),
                          $args->get('searchterm'));
$search_descrip = htmlspecialchars($search_descrip);

$html = "<p><b>$search_descrip</b></p>\n<ul>";

$iter = $dbi->titleSearch(new TextSearchQuery($args->get('searchterm')));

$found = 0;
while ($page = $iter->next()) {
    $found++;
    $html .= "<li>" . LinkExistingWikiWord($page->getName()) . "\n";
}

$html .= ("</ul><hr noshade>\n"
          . sprintf(gettext ("%d pages match your query."), $found)
          . "\n");

echo GeneratePage('MESSAGE', $html, "$search_title: $searchterm");

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>
