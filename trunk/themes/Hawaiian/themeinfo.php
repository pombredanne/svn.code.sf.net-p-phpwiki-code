<?php

rcs_id('$Id: themeinfo.php,v 1.1 2001-12-31 23:24:37 carstenklapp Exp $');

// If you specify a relative URL for the CSS and images,
// the are interpreted relative to DATA_PATH (see below).
// (The default value of DATA_PATH is the directory in which
// index.php resides.)

// To activate this theme, specify this setting in index.php:
//$theme=Hawaiian;

// CSS file defines fonts, colors and background images for this style.
$CSS_URL = "themes/$theme/Hawaiian.css";

// Logo image appears on every page.
$logo = "themes/$theme/PalmBeach.jpg";

// Signature image which is shown after saving an edited page
// If this is left blank (or unset), the signature will be omitted.
//$SignatureImg = "themes/$theme/Submersible.jpg";
$SignatureImg = "themes/$theme/WaterFall.jpg";

// If any custom xhtml template files are defined by this theme they
// will appear here.
/*
$templates = array('BROWSE' =>    "themes/$theme/templates/browse.html",
		   'EDITPAGE' =>  "themes/$theme/templates/editpage.html",
		   'MESSAGE' =>   "themes/$theme/templates/message.html");
*/

// If this theme defines any custom link icons, it will completely override
// any link icon settings defined in index.php.

$URL_LINK_ICONS = array(
/*                    'http'	  => "themes/$theme/http.png",     */
/*                    'https'	  => "themes/$theme/https.png",    */
/*                    'ftp'	  => "themes/$theme/ftp.png",      */
/*                    'mailto'    => "themes/$theme/mailto.png",   */
                      'interwiki' => "themes/$theme/interwiki.png",
                      '*'	  => "themes/$theme/flower.png"
                    );


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
