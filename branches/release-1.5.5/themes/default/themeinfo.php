<?php
// Avoid direct call to this file.
// PHPWIKI_VERSION is defined in lib/prepend.php
if (!defined('PHPWIKI_VERSION')) {
    header("Location: /");
    exit;
}

/*
 * This file defines the default appearance ("theme") of PhpWiki.
 */

require_once 'lib/WikiTheme.php';

$WikiTheme = new WikiTheme('default');
