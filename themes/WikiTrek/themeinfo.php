<?php

rcs_id('$Id: themeinfo.php,v 1.4 2002-01-01 07:18:42 carstenklapp Exp $');

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
 *
 * This theme does not render properly in all browsers. In particular,
 * OmniWeb renders some text as black-on-black. Netscape 4 will probably
 * choke on it too.
 * * * * * * * * */

// To activate this theme, specify this setting in index.php:
//$theme="WikiTrek";

// CSS file defines fonts, colors and background images for this style.
$CSS_URL = "themes/$theme/WikiTrek.css";

// Logo image appears on every page.
$logo = "themes/$theme/Ufp-logo.png";

// Signature image which is shown after saving an edited page
// If this is left blank (or unset), the signature will be omitted.
$SignatureImg = "themes/$theme/lights.gif";

// If this theme defines any custom link icons, it will completely override
// any link icon settings defined in index.php.
/*
$URL_LINK_ICONS = array(
                    'http'	=> "themes/$theme/http.png",
                    'https'	=> "themes/$theme/https.png",
                    'ftp'	=> "themes/$theme/ftp.png",
                    'mailto'    => "themes/$theme/mailto.png",
                    'interwiki' => "themes/$theme/interwiki.png",
                    '*'	        => "themes/$theme/zapg.png"
                    );
*/

//gettext() does not work here because we are included from index.php
//normally themes shouldn't override date & time settings because this will mess up
//any future user-specific preferences for locale
$datetimeformat = sprintf(("Stardate %s"),'%B.%e.%Y');	// may contain time of day
$dateformat = sprintf(("Stardate %s"),'%B.%e.%Y');	// must not contain time

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
