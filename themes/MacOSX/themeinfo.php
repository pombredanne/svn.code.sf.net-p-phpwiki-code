<?php

rcs_id('$Id: themeinfo.php,v 1.1 2002-01-02 01:28:57 carstenklapp Exp $');

/**
 * A PhpWiki theme inspired by the Aqua appearance of Mac OS X.
 * 
 * The images used with this theme depend on the PNG alpha channel to
 * blend in with whatever background color or texture is on the page.
 * When viewed with an older browser, the images may be incorrectly
 * rendered with a thick solid black border. When viewed with a modern
 * browser, the images will display with nice edges and blended shadows.
 * */

// To activate this theme, specify this setting in index.php:
//$theme="MacOSX";
// To deactivate themes, comment out all the $theme=lines in index.php.

// CSS location
//
// CSS file defines fonts, colors and background images for this style.
// The companion '*-heavy.css' file isn't defined, it's just expected to
// be in the same directory that the base style is in.
//$CSS_URL = "themes/$theme/phpwiki.css";

// Logo image appears on every page and links to the HomePage.
$logo = "themes/$theme/PhpWiki.png";

// Signature image which is shown after saving an edited page.
// If this is left blank, any signature defined in index.php will be
// used. If it is not defined by index.php or in here then the
// "Thank you for editing..." screen will be omitted.
//$SignatureImg = "themes/$theme/signature.png";

// If this theme defines any templates, they will completely override
// whatever templates have been defined in index.php.
/*
$templates = array('BROWSE' =>    "themes/$theme/templates/browse.html",
		   'EDITPAGE' =>  "themes/$theme/templates/editpage.html",
		   'MESSAGE' =>   "themes/$theme/templates/message.html");
*/

// If this theme defines any custom link icons, they will completely override
// any link icon settings defined in index.php.
/*
$URL_LINK_ICONS = array(
                    'http'	=> "themes/$theme/icons/http.png",
                    'https'	=> "themes/$theme/icons/https.png",
                    'ftp'	=> "themes/$theme/icons/ftp.png",
                    'mailto'	=> "themes/$theme/icons/mailto.png",
                    'interwiki' => "themes/$theme/icons/interwiki.png",
                    '*'		=> "themes/$theme/icons/zapg.png"
                    );
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
