<?php

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
define("CSS_URL", "phpwiki.css");

// logo image
$logo = "wikibase.png";

// Signature image which is shown after saving an edited page
// If this is left blank (or unset), the signature will be omitted.
$SignatureImg = "signature.png";

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
