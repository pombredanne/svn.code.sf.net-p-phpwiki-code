<?php // -*-php-*-

rcs_id('$Id: themeinfo.php,v 1.11 2002-01-19 00:04:50 carstenklapp Exp $');

/**
 * This PhpWiki theme is experimental and will likely not appear as
 * part of any release ("accessories not included"--download
 * seperately.)
 *
 * The first experimental (and very alpha) theme pack is here, have
 * some fun and play with it, fix it up if you like.
 *
 * This one is (by design) completely css-based so unfortunately it
 * doesn't render properly or even the same across different browsers.
 * A preview screen snapshot is also included for comparison testing.
 *
 * The reverse coloring of this theme was chosen to provide an extreme
 * example of a heavily customized PhpWiki, through which any
 * potential visual problems can be identified. The intention is to
 * elimate as many non-html elements from the html templates as
 * possible.
 *
 * This theme does not render properly in all browsers. In particular,
 * OmniWeb renders some text as black-on-black. Netscape 4 will
 * probably choke on it too.
 * * * * * * * * * * * * */

require_once('lib/Theme.php');

$Theme = new Theme('WikiTrek');

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.

$Theme->setDefaultCSS('WikiTrek', 'WikiTrek.css');
$Theme->addAlternateCSS(_("Printer"), 'phpwiki-printer.css', 'print, screen');


// The logo image
$Theme->addImageAlias('logo', 'Ufp-logo.jpg');

// Signature image which is shown after saving an edited page.  If
// this is left blank, any signature defined in index.php will be
// used. If it is not defined by index.php or in here then the "Thank
// you for editing..." screen will be omitted.
$Theme->addImageAlias('signature', 'lights.gif');

// The gettext() function does not work here because we are included
// from 'index.php'. Normally themes shouldn't override date & time
// settings because this will mess up any future user-specific
// preferences for locale.
$Theme->setDateTimeFormat("Stardate %B.%e.%Y"); // may contain time of day
$Theme->setDateFormat("Stardate %B.%e.%Y"); // must not contain time


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
