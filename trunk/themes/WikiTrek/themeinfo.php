<?php

/**
 * This PhpWiki theme is experimental and will likely not appear as 
 * part of any release ("accessories not included"--download seperately.)
 *
 * The first experimental (and very alpha) theme pack is here, have some
 * fun and play with it, fix it up if you like.
 *
 * This one is (by design) completely css-based so unfortunately it
 * doesn't render properly or even the same across different browsers.
 * A preview screen snapshot is also included for comparison testing.
 *
 * The reverse coloring of this theme was chosen to provide an
 * extreme example of a heavily customized PhpWiki, through which
 * any potential visual problems can be identified. The intention is
 * to elimate as many non-html elements from the html templates as
 * possible.
 */

//This theme does not render properly in all browsers. In particular,
// OmniWeb renders some text as black-on-black. Netscape 4 will probably
// choke on it too.

//use this setting in index.php:
//$theme="WikiTrek";

// FIXME: these files are moved to a subfolder in /templates
// e.g. /templates/vanilla so relative paths to index.php
// should be irrelevant.

// The current .htaccess in /templates generates an error in apache 1.3.20.
// It should be removed or changed to allow access for themes to work.

// If you specify a relative URL for the CSS and images,
// the are interpreted relative to DATA_PATH (see below).
// (The default value of DATA_PATH is the directory in which
// index.php (this file) resides.)

// CSS location
//
// Note that if you use the stock phpwiki style sheet, 'phpwiki.css',
// you should make sure that it's companion 'phpwiki-heavy.css'
// is installed in the same directory that the base style file is.


$CSS_URL = "themes/$theme/WikiTrek.css";

// logo image
$logo = "themes/$theme/ufp-logo.png";

// Signature image which is shown after saving an edited page
// If this is left blank (or unset), the signature will be omitted.
$SignatureImg = "themes/$theme/lights.gif";

//gettext() does not work here yet
//normally themes shouldn't override date & time settings because this will mess up
//any future user-specific preferences for locale
$datetimeformat = sprintf(("Stardate %s"),'%B %e, %Y');	// may contain time of day
$dateformat = sprintf(("Stardate %s"),'%B %e, %Y');	// must not contain time

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
