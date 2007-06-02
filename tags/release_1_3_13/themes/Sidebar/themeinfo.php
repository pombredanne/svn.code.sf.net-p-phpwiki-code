<?php
rcs_id('$Id: themeinfo.php,v 1.26 2007-01-13 23:42:20 rurban Exp $');

/*
 * This file defines the Sidebar appearance ("theme") of PhpWiki,
 * which can be used as parent class for all sidebar themes. See blog.
 * This use the dynamic jscalendar, which doesn't need extra requests 
 * per month/year change.
 */

require_once('lib/Theme.php');
require_once('lib/WikiPlugin.php');

class Theme_Sidebar extends Theme {

    function Theme_Sidebar ($theme_name='Sidebar') {
        $this->Theme($theme_name);
        $this->calendarInit(true);
    }

    function findTemplate ($name) {
        // hack for navbar.tmpl to hide the buttonseparator
        if ($name == "navbar") {
            $this->setButtonSeparator(HTML::Raw("<br />\n&nbsp;&middot;&nbsp;"));
        }
        if ($name == "actionbar" || $name == "signin") {
            $this->setButtonSeparator(" ");
        }
        return parent::findTemplate($name);
    }
}

$WikiTheme = new Theme_Sidebar('Sidebar');

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.

$WikiTheme->setDefaultCSS(_("Sidebar"), 'sidebar.css');
//$WikiTheme->addAlternateCSS('PhpWiki', 'phpwiki.css');
//$WikiTheme->setDefaultCSS('PhpWiki', 'phpwiki.css');
$WikiTheme->addAlternateCSS(_("Printer"), 'phpwiki-printer.css', 'print, screen');
$WikiTheme->addAlternateCSS(_("Modern"), 'phpwiki-modern.css');

/**
 * The logo image appears on every page and links to the HomePage.
 */
//$WikiTheme->addImageAlias('logo', 'logo.png');

/**
 * The Signature image is shown after saving an edited page. If this
 * is not set, any signature defined in index.php will be used. If it
 * is not defined by index.php or in here then the "Thank you for
 * editing..." screen will be omitted.
 */

// Comment this next line out to enable signature.
$WikiTheme->addImageAlias('signature', false);

$WikiTheme->addImageAlias('search', 'search.png');

/*
 * Link icons.
 */
$WikiTheme->setLinkIcon('http');
$WikiTheme->setLinkIcon('https');
$WikiTheme->setLinkIcon('ftp');
$WikiTheme->setLinkIcon('mailto');
$WikiTheme->setLinkIcon('interwiki');
$WikiTheme->setLinkIcon('*', 'url');

//$WikiTheme->setButtonSeparator(' | ');

/**
 * WikiWords can automatically be split by inserting spaces between
 * the words. The default is to leave WordsSmashedTogetherLikeSo.
 */
$WikiTheme->setAutosplitWikiWords(true);

/**
 * If true (default) show create '?' buttons on not existing pages, even if the 
 * user is not signed in.
 * If false, anon users get no links and it looks cleaner, but then they 
 * cannot easily fix missing pages.
 */
$WikiTheme->setAnonEditUnknownLinks(false);

/*
 * You may adjust the formats used for formatting dates and times
 * below.  (These examples give the default formats.)
 * Formats are given as format strings to PHP strftime() function See
 * http://www.php.net/manual/en/function.strftime.php for details.
 * Do not include the server's zone (%Z), times are converted to the
 * user's time zone.
 */
//$WikiTheme->setDateFormat("%B %d, %Y");


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
