<?php

rcs_id('$Id: themeinfo.php,v 1.14 2002-01-19 03:23:45 carstenklapp Exp $');

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
$Theme->addAlternateCSS(_("Modern"), 'phpwiki-modern.css');
$Theme->addAlternateCSS('PhpWiki', 'phpwiki.css');

// Logo image appears on every page and links to the HomePage.
$Theme->addImageAlias('logo', 'PalmBeach.jpg');

//$Theme->addImageAlias('signature', 'SubmersiblePiscesV.jpg');
$Theme->addImageAlias('signature', 'WaterFall.jpg');

// If you want to see more than just the waterfall let a random
// picture be chosen for the signature image:
require_once('lib/random.php');
$imgSet = new ImageSet("themes/Hawaiian/images/pictures");
$imgFile = "pictures/" . $imgSet->pickRandomImage();
$Theme->addImageAlias('signature', $imgFile);

//To test out the randomization just use logo instead of signature
//$Theme->addImageAlias('logo', $imgFile);


/*
 * Link Icons
 */
$Theme->setLinkIcon('interwiki');
$Theme->setLinkIcon('*', 'flower.png');


// This defines separators used in RecentChanges and RecentEdits lists.
// If undefined, defaults to '' (nothing) and '...' (three periods).
//define("RC_SEPARATOR_A", ' . . . ');
//define("RC_SEPARATOR_B", ' --');

// Controls whether the '?' appears before or after UnknownWikiWords.
// The PhpWiki default is for the '?' to appear before.
//$Theme->setWikiMark('?%s');


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
