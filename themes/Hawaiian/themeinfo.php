<?php

rcs_id('$Id: themeinfo.php,v 1.9 2002-01-18 06:11:37 carstenklapp Exp $');

/**
 * WikiWiki Hawaiian theme for PhpWiki.
 */

require_once('lib/Theme.php');

$Theme = new Theme('Hawaiian');

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.

$Theme->setDefaultCSS('Hawaiian', 'Hawaiian.css');
$Theme->addAlternateCSS(_("Printer"), 'phpwiki-printer.css', 'print, screen');


// Logo image appears on every page and links to the HomePage.
$Theme->addImageAlias('logo', 'PalmBeach.jpg');
//$Theme->addImageAlias('signature', 'SubmersiblePiscesV.jpg'); //for Steve
$Theme->addImageAlias('signature', 'WaterFall.jpg'); //for Steve

/*
 * Link Icons
 */
$Theme->setLinkIcon('interwiki');
$Theme->setLinkIcon('*', 'flower.png');

$Theme->setButtonSep(' | ');

// FIXME: do we need this?
// If you want to see more than just the waterfall let a random
// picture be chosen for the signature image:
//include("themes/$theme/pictures/random.php");

// This defines separators used in RecentChanges and RecentEdits lists.
// If undefined, defaults to '' (nothing) and '...' (three periods).
//define("RC_SEPARATOR_A", ' . . . ');
//define("RC_SEPARATOR_B", ' --');

// Controls whether the '?' appears before or after UnknownWikiWords.
// The PhpWiki default is for the '?' to appear before.
//define('WIKIMARK_AFTER', true);

// If this theme defines any templates, they will completely override
// whatever templates have been defined in index.php.
/*
$templates = array(
                   'BROWSE'   => "themes/$theme/templates/browse.html",
                   'EDITPAGE' => "themes/$theme/templates/editpage.html",
                   'MESSAGE'  => "themes/$theme/templates/message.html"
                   );
*/


// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
