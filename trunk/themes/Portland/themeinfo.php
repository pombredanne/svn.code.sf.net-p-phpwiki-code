<?php
rcs_id('$Id: themeinfo.php,v 1.1 2002-01-19 22:48:42 carstenklapp Exp $');

/*
 * This file defines an appearance ("theme") of PhpWiki similar to the Portland Pattern Repository.
 */

require_once('lib/Theme.php');

class Theme_Portland extends Theme {
    function LinkUnknownWikiWord($wikiword, $linktext = '') {
        if (empty($linktext)) {
            $linktext = $wikiword;
            if ($this->getAutoSplitWikiWords())
                $linktext=split_pagename($linktext);
            $class = 'wikiunknown';
        } else
            $class = 'named-wikiunknown';

        return Element('span', array('class' => $class),
                       Element('u', $linktext)
                       . Element('a', array('href' => WikiURL($wikiword, array('action' => 'edit'))),
                                 '?'));
    }
}

$Theme = new Theme_Portland('Portland');

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.

$Theme->setDefaultCSS('Portland', 'portland.css');
$Theme->addAlternateCSS(_("Printer"), 'phpwiki-printer.css', 'print, screen');
$Theme->addAlternateCSS(_("Modern"), 'phpwiki-modern.css');
$Theme->addAlternateCSS('PhpWiki', 'phpwiki.css');

/**
 * The logo image appears on every page and links to the HomePage.
 */
//$Theme->addImageAlias('logo', 'logo.png');

/**
 * The Signature image is shown after saving an edited page. If this
 * is not set, any signature defined in index.php will be used. If it
 * is not defined by index.php or in here then the "Thank you for
 * editing..." screen will be omitted.
 */
$Theme->addImageAlias('signature', 'signature.png');

/*
 * Link icons.
 */
//$Theme->setLinkIcon('http');
//$Theme->setLinkIcon('https');
//$Theme->setLinkIcon('ftp');
//$Theme->setLinkIcon('mailto');
//$Theme->setLinkIcon('interwiki');
//$Theme->setLinkIcon('*', 'url');

$Theme->setButtonSeparator(' ');

/**
 * WikiWords can automatically be split by inserting spaces between
 * the words. The default is to leave WordsSmashedTogetherLikeSo.
 */
$Theme->setAutosplitWikiWords(false);

/*
 * You may adjust the formats used for formatting dates and times
 * below.  (These examples give the default formats.)
 * Formats are given as format strings to PHP strftime() function See
 * http://www.php.net/manual/en/function.strftime.php for details.
 */
$Theme->setDateTimeFormat("%B %e, %Y");   // may contain time of day
$Theme->setDateFormat("%B %e, %Y");	    // must not contain time


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
