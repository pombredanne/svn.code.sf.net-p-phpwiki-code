<?php
rcs_id('$Id$');

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

    function load() {
	// CSS file defines fonts, colors and background images for this
	// style.  The companion '*-heavy.css' file isn't defined, it's just
	// expected to be in the same directory that the base style is in.

	$this->setDefaultCSS(_("Sidebar"), 'sidebar.css');
	//$this->addAlternateCSS('PhpWiki', 'phpwiki.css');
	//$this->setDefaultCSS('PhpWiki', 'phpwiki.css');
	$this->addAlternateCSS(_("Printer"), 'phpwiki-printer.css', 'print, screen');
	$this->addAlternateCSS(_("Modern"), 'phpwiki-modern.css');

	/**
	 * The logo image appears on every page and links to the HomePage.
	 */
	//$this->addImageAlias('logo', 'logo.png');

	/**
	 * The Signature image is shown after saving an edited page. If this
	 * is not set, any signature defined in index.php will be used. If it
	 * is not defined by index.php or in here then the "Thank you for
	 * editing..." screen will be omitted.
	 */

	// Comment this next line out to enable signature.
	$this->addImageAlias('signature', false);

	$this->addImageAlias('search', 'search.png');

	/*
	 * Link icons.
	 */
	$this->setLinkIcon('http');
	$this->setLinkIcon('https');
	$this->setLinkIcon('ftp');
	$this->setLinkIcon('mailto');
	$this->setLinkIcon('interwiki');
	$this->setLinkIcon('*', 'url');

	//$this->setButtonSeparator(' | ');

	/**
	 * WikiWords can automatically be split by inserting spaces between
	 * the words. The default is to leave WordsSmashedTogetherLikeSo.
	 */
	$this->setAutosplitWikiWords(true);

	/**
	 * If true (default) show create '?' buttons on not existing pages, even if the 
	 * user is not signed in.
	 * If false, anon users get no links and it looks cleaner, but then they 
	 * cannot easily fix missing pages.
	 */
	$this->setAnonEditUnknownLinks(false);

	/*
	 * You may adjust the formats used for formatting dates and times
	 * below.  (These examples give the default formats.)
	 * Formats are given as format strings to PHP strftime() function See
	 * http://www.php.net/manual/en/function.strftime.php for details.
	 * Do not include the server's zone (%Z), times are converted to the
	 * user's time zone.
	 */
	//$this->setDateFormat("%B %d, %Y");
    }
}

$WikiTheme = new Theme_Sidebar('Sidebar');

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
