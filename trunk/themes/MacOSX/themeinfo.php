<?php

rcs_id('$Id: themeinfo.php,v 1.25 2002-01-19 03:23:45 carstenklapp Exp $');

/**
 * A PhpWiki theme inspired by the Aqua appearance of Mac OS X.
 * 
 * The images used with this theme depend on the PNG alpha channel to
 * blend in with whatever background color or texture is on the page.
 * When viewed with an older browser, the images may be incorrectly
 * rendered with a thick solid black border. When viewed with a modern
 * browser, the images will display with nice edges and blended
 * shadows.
 *
 * Known Problems:
 *
 * Most of the images you will see a white area around the outside.
 * Once the icons for the buttons have been finalized, the alpha
 * channel will be added to eliminate the white parts.
 *
 * The button toolbars use tables for positioning. Yuck. (It will do
 * for now).
 *
 * Only English buttons are available. Full localization is coming.
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
 * Automatic button generation for localized buttons
 * isn't going to happen for this theme--there is a gradient across
 * the glass surface of the button that only Mac OS X Aqua can
 * generate. Chopping a button up and stamping it with localized words
 * means a lot of tweaking to the blank button pieces to get the seams
 * invisible, so a more authentic Mac OS X user experience is achived by
 * producing the buttons by hand.
 *
 * The defaut link icons I want to move into this theme, and come up
 * with some new linkicons for the default look. (Any ideas, feedback?)
 *
 * */

// To activate this theme, specify this setting in index.php:
//$theme="MacOSX";
// To deactivate themes, comment out all the $theme=lines in index.php.
require_once('lib/Theme.php');


class Theme_MacOSX extends Theme {
    function getCSS() {
        // FIXME: this is a hack which will not be needed once
        //        we have dynamic CSS.
        $css = Theme::getCSS();
        $css .= Element('style', array('type' => 'text/css'),
                        sprintf("<!--\nbody {background-image: url(%s);}\n-->\n",
                                $this->getImageURL('bgpaper8')));
                                //for non-browse pages, like former editpage, message etc.
                                //$this->getImageURL('bggranular')));
        return $css;
    }

    function getRecentChangesFormatter ($format) {
        $this->requireFile('lib/RecentChanges.php');
        if ($format == 'rss')
            return false;       // use default
        return '_MacOSX_RecentChanges_Formatter';
    }

    function getPageHistoryFormatter ($format) {
        $this->requireFile('lib/RecentChanges.php');
        if ($format == 'rss')
            return false;       // use default
        return '_MacOSX_PageHistory_Formatter';
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

$Theme->setDateTimeFormat("%A, %B %e, %Y. %l:%M:%S %p %Z"); // may contain time of day
$Theme->setDateFormat("%A, %B %e, %Y"); // must not contain time

// Controls whether the '?' appears before or after UnknownWikiWords.
// The PhpWiki default is for the '?' to appear before.
$Theme->setWikiMark('%s?');

/*
$ToolbarImages = array(
'RecentChanges' => array(
'1 day'		=> "themes/$theme/buttons/en/1+day.png",
'2 days'	=> "themes/$theme/buttons/en/2+days.png",
'3 days'	=> "themes/$theme/buttons/en/3+days.png",
'4 days'	=> "themes/$theme/buttons/en/4+days.png",
'7 days'	=> "themes/$theme/buttons/en/7+days.png",
'30 days'	=> "themes/$theme/buttons/en/30+days.png",
'90 days'	=> "themes/$theme/buttons/en/90+days.png",
'...'		=> "themes/$theme/buttons/en/alltime.png")
);
*/

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
