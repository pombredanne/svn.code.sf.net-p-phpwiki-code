<?php

rcs_id('$Id: themeinfo.php,v 1.5 2001-12-27 18:11:49 carstenklapp Exp $');

// FIXME: these files are moved to a subfolder in /templates
// e.g. /templates/vanilla so relative paths to index.php
// should be irrelevant.

// The current .htaccess in /templates generates an error in apache 1.3.20.
// It should be removed or changed to allow access for themes to work.

// If you specify a relative URL for the CSS and images,
// the are interpreted relative to DATA_PATH (see below).
// (The default value of DATA_PATH is the directory in which
// index.php (this file) resides.)

//use this setting in index.php:
//$theme="default";

// CSS location
//
// Note that if you use the stock phpwiki style sheet, 'phpwiki.css',
// you should make sure that it's companion 'phpwiki-heavy.css'
// is installed in the same directory that the base style file is.
$CSS_URL = "themes/$theme/phpwiki.css";

// logo image
$logo = "themes/$theme/wikibase.png";

// Signature image which is shown after saving an edited page
// If this is left blank (or unset), the signature will be omitted.
$SignatureImg = "themes/$theme/signature.png";

$templates = array('BROWSE' =>    "themes/$theme/templates/browse.html",
		   'EDITPAGE' =>  "themes/$theme/templates/editpage.html",
		   'MESSAGE' =>   "themes/$theme/templates/message.html");

$URL_LINK_ICONS = array(
                    'http'	=> "themes/$theme/icons/http.png",
                    'https'	=> "themes/$theme/icons/https.png",
                    'ftp'	=> "themes/$theme/icons/ftp.png",
                    'mailto'	=> "themes/$theme/icons/mailto.png",
                    'interwiki' => "themes/$theme/icons/interwiki.png",
                    '*'		=> "themes/$theme/icons/zapg.png"
                    );

rcs_id('$Id: themeinfo.php,v 1.5 2001-12-27 18:11:49 carstenklapp Exp $');

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
