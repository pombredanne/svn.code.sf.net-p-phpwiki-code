<?php

rcs_id('$Id: themeinfo.php,v 1.14 2002-01-14 05:53:32 carstenklapp Exp $');

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
 * Only English buttons are available. Full localization is still a
 * ways off. Work is in progress to modularize & tokenize the normal
 * templates and (somehow) some cool toolbar functions are going to
 * result from it.  This should make editing the MacOSX theme template
 * files much easier as well as providing a generic localization
 * method for any other toolbars (whether image or text).
 *
 * There is an image for a BackLinks button but it's not used yet
 * either.  For now BackLinks are still accessed by clicking in the
 * title.
 *
 * The CSS is still mostly the same as phpwiki.css. I'd like to change
 * it a bit but have no specific plans yet. Just a general feeling
 * that it should look and feel like a Mac interface: with subtle
 * effects and a fine color scheme. Since I'm sick of the stripes
 * Mr. Jobs thoughtfully plastered all over my screen, I've chosen a
 * brushed paper (or stucco?) texture effect very close to white. If
 * your monitor isn't calibrated well you might not even see it.
 *
 * I probably won't be submitting anything else for this theme for a
 * bit.  Not until the default toolbar stuff and templates are further
 * along anyway.
 * 
 * Send me some feedback, do you like the icons used in the buttons?
 * Got any ideas for code to pick out the localized buttons from the
 * right directory? Automatic button generation for localized buttons
 * isn't going to happen for this theme--there is a gradient across
 * the glass surface of the button that only Mac OS X Aqua can
 * generate. Chopping a button up and stamping it with localized words
 * means a lot of tweaking to the blank button pieces to get the seams
 * invisible. So it will be a nicer effect to produce them by hand.
 *
 * The current link icons I want to move into this theme, and come up
 * with some new linkicons for the default look. (Comments, feedback?)
 *
 * */

// To activate this theme, specify this setting in index.php:
//$theme="MacOSX";
// To deactivate themes, comment out all the $theme=lines in index.php.

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.
$CSS_DEFAULT = "MacOSX";

$CSS_URLS = array_merge($CSS_URLS,
                        array("$CSS_DEFAULT" => "themes/$theme/${CSS_DEFAULT}.css"));

// Logo image appears on every page and links to the HomePage.
$logo = "themes/$theme/PhpWiki.png";

// RSS logo icon (path relative to index.php)
// If this is left blank (or unset), the default "images/rss.png"
// will be used.
//$rssicon = "images/rss.png";
$rssicon = "themes/$theme/RSS.png";

// Signature image which is shown after saving an edited page.  If
// this is left blank, any signature defined in index.php will be
// used. If it is not defined by index.php or in here then the "Thank
// you for editing..." screen will be omitted.
$SignatureImg = "themes/$theme/Signature.png"; // Papyrus 19pt

// If this theme defines any templates, they will completely override
// whatever templates have been defined in index.php.

$templates = array(
                   'BROWSE'   => "themes/$theme/templates/browse.html",
                   'EDITPAGE' => "themes/$theme/templates/editpage.html",
                   'MESSAGE'  => "themes/$theme/templates/message.html"
                   );

// If this theme defines any custom link icons, they will completely
// override any link icon settings defined in index.php.

$URL_LINK_ICONS = array(
                        'http'      => "images/http.png",
                        'https'     => "images/https.png",
                        'ftp'       => "images/ftp.png",
                        'mailto'    => "themes/$theme/mailto.png",
                        'interwiki' => "images/interwiki.png",
                        '*'         => "images/url.png"
                        );

$ToolbarImages = array(
'RecentChanges' => array(
'1 day'		=> "themes/$theme/locale/en/toolbars/RecentChanges/1day.png",
'2 day'		=> "themes/$theme/locale/en/toolbars/RecentChanges/2days.png",
'3 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/3days.png",
'4 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/4days.png",
'7 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/7days.png",
'30 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/30days.png",
'90 days'	=> "themes/$theme/locale/en/toolbars/RecentChanges/90days.png",
'...'		=> "themes/$theme/locale/en/toolbars/RecentChanges/alltime.png")
);

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
