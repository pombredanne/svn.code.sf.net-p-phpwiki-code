<?php

rcs_id('$Id: themeinfo.php,v 1.2 2002-01-01 21:41:32 carstenklapp Exp $');

/**
 * WikiWiki Hawaiian theme for PhpWiki.
 */

// To activate this theme, specify this setting in index.php:
//$theme=Hawaiian;
// To deactivate themes, comment out all the $theme=lines in index.php.

// CSS location
//
// CSS file defines fonts, colors and background images for this style.
// The companion '*-heavy.css' file isn't defined, it's just expected to
// be in the same directory that the base style is in.
$CSS_URL = "themes/$theme/Hawaiian.css";

// Logo image appears on every page and links to the HomePage.
$logo = "themes/$theme/PalmBeach.jpg";

// Signature image which is shown after saving an edited page.
// If this is left blank, any signature defined in index.php will be
// used. If it is not defined by index.php or in here then the
// "Thank you for editing..." screen will be omitted.
//$SignatureImg = "themes/$theme/pictures/SubmersiblePiscesV.jpg"; #for Steve
$SignatureImg = "themes/$theme/WaterFall.jpg";
// If you want to see more than just the waterfall
// let a random picture be chosen for the signature image:
//include("themes/$theme/pictures/random.php");

// If this theme defines any templates, they will completely override
// whatever templates have been defined in index.php.
/*
$templates = array('BROWSE' =>    "themes/$theme/templates/browse.html",
		   'EDITPAGE' =>  "themes/$theme/templates/editpage.html",
		   'MESSAGE' =>   "themes/$theme/templates/message.html");
*/

// If this theme defines any custom link icons, they will completely override
// any link icon settings defined in index.php.
$URL_LINK_ICONS = array(
/*                    'http'	  => "themes/$theme/http.png",     */
/*                    'https'	  => "themes/$theme/https.png",    */
/*                    'ftp'	  => "themes/$theme/ftp.png",      */
/*                    'mailto'    => "themes/$theme/mailto.png",   */
                      'interwiki' => "themes/$theme/interwiki.png",
                      '*'	  => "themes/$theme/flower.png"
                    );

// If this theme defines any templates, they will completely override
// whatever templates have been defined in index.php.
/*
$templates = array('BROWSE' =>    "themes/$theme/templates/browse.html",
		   'EDITPAGE' =>  "themes/$theme/templates/editpage.html",
		   'MESSAGE' =>   "themes/$theme/templates/message.html");
*/

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
