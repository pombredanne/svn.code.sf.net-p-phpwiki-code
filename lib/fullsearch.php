<?php
// Search the text of pages for a match.
rcs_id('$Id: fullsearch.php,v 1.8 2001-09-19 03:24:36 wainstead Exp $');
require_once('lib/Template.php');
require_once('lib/TextSearchQuery.php');

$query = new TextSearchQuery($args->get('searchterm'));

$html = ("<p><b>"
         . sprintf(gettext ("Searching for \"%s\" ....."),
                   htmlspecialchars($args->get('searchterm')))
         . "</b></p>\n<dl>\n" );

// search matching pages
$iter = $dbi->fullsearch($query);

// quote regexp chars (space are treated as "or" operator)
$hilight_re = $query->getHighlightRegexp();

$found = 0;
$count = 0;
while ($page = $iter->next()) {
    $html .= "<dt><b>" . LinkExistingWikiWord($page->getName()) . "</b>\n";
    $count++;
    if (empty($hilight_re))
        continue;               // nothing to highlight
    
    // print out all matching lines, highlighting the match
    $current = $page->getCurrentRevision();
    $matches = preg_grep("/$hilight_re/i", $current->getContent());
    foreach ($matches as $line) {
        if ($hits = preg_match_all("/$hilight_re/i", $line, $dummy)) {
            $line = preg_replace("/$hilight_re/i",
                                 "${FieldSeparator}OT\\0${FieldSeparator}CT",
                                 $line);
            $line = htmlspecialchars($line);
            $line = str_replace("${FieldSeparator}OT", '<b>', $line);
            $line = str_replace("${FieldSeparator}CT", '</b>', $line);
            $html .= "<dd><small>$line</small></dd>\n";
            $found += $hits;
        }
    }
}

$html .= ( "</dl>\n<hr noshade>"
           . sprintf (gettext ("%d matches found in %d pages."),
                      $found, $count)
           . "\n");
           
echo GeneratePage('MESSAGE', $html, sprintf(gettext("Full Text Search: %s"), $searchterm));


// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>
