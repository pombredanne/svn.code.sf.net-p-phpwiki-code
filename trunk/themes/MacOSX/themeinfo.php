<?php

rcs_id('$Id: themeinfo.php,v 1.38 2002-01-23 06:39:50 carstenklapp Exp $');

/**
 * A PhpWiki theme inspired by the Aqua appearance of Mac OS X.
 * 
 * The images used with this theme depend on the PNG alpha channel to
 * blend in with whatever background color or texture is on the page.
 * When viewed with an older browser, the images may be incorrectly
 * rendered with a thick solid black border. When viewed with a modern
 * browser, the images will display with nice edges and blended
 * shadows.
 */

// Button Localization:
//
// English "C"  English    - all present, only some with alpha shadows
// Dutch   "nl" Nederlands - some
// Spanish "es" Español    - some
// French  "fr" Français   - none
// German  "de" Deutsch    - most
// Swedish "sv" Svenska    - none
// Italian "it" Italiano   - none
//
// More translations are needed in po files before the localized
// button images can be completed.

/**
 * Known Problems:
 *
 * Most of the images you will see a white area around the outside.
 * Once the icons for the buttons have been finalized, the alpha
 * channel will be added to eliminate the white parts.
 *
 * The button toolbars use tables for positioning. Yuck. (It will do
 * for now).
 *
 * The CSS is still mostly the same as phpwiki.css. I'd like to change
 * it a bit but have no specific plans yet. Just a general feeling
 * that it should look and feel like a Mac interface: with subtle
 * effects and a fine color scheme. Since I'm sick of the stripes
 * Mr. Jobs thoughtfully plastered all over my screen, I've chosen a
 * brushed paper (or stucco?) texture effect very close to white. If
 * your monitor isn't calibrated well you might not even see it.
 *
 * Send me some feedback, do you like the icons used in the buttons?
 *
 * Automatic button generation for localized buttons isn't going to
 * happen for this theme--there is a gradient across the glass surface
 * of the button that only Mac OS X Aqua can generate. Chopping a
 * button up and stamping it with localized words means a lot of
 * tweaking to the blank button pieces to get the seams invisible, so
 * a more authentic Mac OS X user experience is achived by producing
 * the buttons by hand.
 *
 * The defaut link icons I want to move into this theme, and come up
 * with some new linkicons for the default look. (Any ideas,
 * feedback?)
 *
 * */

require_once('lib/Theme.php');

class Theme_MacOSX extends Theme {
    function getCSS() {
        // FIXME: this is a hack which will not be needed once
        //        we have dynamic CSS.
        $css = Theme::getCSS();
        $css[] = HTML::style(array('type' => 'text/css'),
                             new RawXml(sprintf("<!--\nbody {background-image: url(%s);}\n-->\n",
                                                $this->getImageURL('bgpaper8'))));
                                //for non-browse pages, like former editpage, message etc.
                                //$this->getImageURL('bggranular')));
        return $css;
    }

    function getRecentChangesFormatter ($format) {
        include_once($this->file('lib/RecentChanges.php'));
        if (preg_match('/^rss/', $format))
            return false;       // use default
        return '_MacOSX_RecentChanges_Formatter';
    }

    function getPageHistoryFormatter ($format) {
        include_once($this->file('lib/RecentChanges.php'));
        if (preg_match('/^rss/', $format))
            return false;       // use default
        return '_MacOSX_PageHistory_Formatter';
    }

    function linkUnknownWikiWord($wikiword, $linktext = '') {
        $url = WikiURL($wikiword, array('action' => 'edit'));
        //$link = HTML::span(HTML::a(array('href' => $url), '?'));
        $link = HTML::span($this->makeButton('?', $url));
        

        if (!empty($linktext)) {
            $link->unshiftContent(HTML::u($linktext));
            $link->setAttr('class', 'named-wikiunknown');
        }
        else {
            $link->unshiftContent(HTML::u($this->maybeSplitWikiWord($wikiword)));
            $link->setAttr('class', 'wikiunknown');
        }
        
        return $link;
    }
}

$Theme = new Theme_MacOSX('MacOSX');

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.
$Theme->setDefaultCSS("MacOSX", "MacOSX.css");
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
//$Theme->addImageAlias('signature', 'signature.png');

/*
 * Link icons.
 */
$Theme->setLinkIcon('http');
$Theme->setLinkIcon('https');
$Theme->setLinkIcon('ftp');
$Theme->setLinkIcon('mailto');
$Theme->setLinkIcon('interwiki');
$Theme->setLinkIcon('*', 'url');

$Theme->setButtonSeparator(' ');

$Theme->addButtonAlias('?', 'uww');
$Theme->addButtonAlias(_("Lock Page"), "Lock Page");
$Theme->addButtonAlias(_("Unlock Page"), "Unlock Page");
$Theme->addButtonAlias(_("Page Locked"), "Page Locked");
$Theme->addButtonAlias("...", "alltime");

/**
 * WikiWords can automatically be split by inserting spaces between
 * the words. The default is to leave WordsSmashedTogetherLikeSo.
 */
//$Theme->setAutosplitWikiWords(false);

/*
 * You may adjust the formats used for formatting dates and times
 * below.  (These examples give the default formats.)
 * Formats are given as format strings to PHP strftime() function See
 * http://www.php.net/manual/en/function.strftime.php for details.
 */
//$Theme->setDateTimeFormat("%B %e, %Y");   // may contain time of day
//$Theme->setDateFormat("%B %e, %Y");	    // must not contain time
$Theme->setDateTimeFormat("%A, %B %e, %Y. %l:%M:%S %p %Z"); // may contain time of day
$Theme->setDateFormat("%A, %B %e, %Y"); // must not contain time


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
