<?php
/**
 * Copyright © 2002 Carsten Klapp
 * Copyright © 2004-2005,2007,2009-2010 Reini Urban
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

// Avoid direct call to this file.
// PHPWIKI_VERSION is defined in lib/prepend.php
if (!defined('PHPWIKI_VERSION')) {
    header("Location: /");
    exit;
}

/*
 * This file defines the Sidebar theme of PhpWiki,
 * which can be used as parent class for all sidebar themes. See MonoBook and blog.
 * It is now an extension of the MonoBook theme.
 *
 * This is a complete rewrite and not related to the old Sidebar theme.
 * It is derived from MonoBook, includes the calendar and can derive from wikilens.
 *
 * Changes to MonoBook:
 *  folderArrow
 *  special login, search and tags
 *  CbNewUserEdit - when a new user creates or edits a page, a Userpage template is created
 *  CbUpload - uploads are virus checked
 */

if (!defined("CLAMDSCAN_PATH")) {
    define("CLAMDSCAN_PATH", "/usr/local/bin/clamdscan");
}
if (!defined("CLAMDSCAN_VIRUS")) {
    define("CLAMDSCAN_VIRUS", "/var/www/virus-found");
}

require_once 'lib/WikiTheme.php';
require_once 'lib/WikiPlugin.php';
require_once 'themes/MonoBook/themeinfo.php';

class WikiTheme_Sidebar extends WikiTheme_MonoBook
{
    public function __construct($theme_name = 'Sidebar')
    {
        parent::__construct($theme_name);
    }

    /* Display up/down button with persistent state */
    /* persistent state per block in cookie for 30 days */
    public function folderArrow($id, $init = 'Open')
    {
        global $request;
        if ($cookie = $request->cookies->get("folder_" . $id)) {
            $init = $cookie;
        }
        if ($init == 'Open' or $init == 'Closed') {
            $png = $this->_findData('images/folderArrow' . $init . '.png');
        } else {
            $png = $this->_findData('images/folderArrowOpen.png');
        }
        return HTML::img(array('id' => $id . '-img',
            'src' => $png,
            //'align' => 'right',
            'onclick' => "showHideFolder('$id')",
            'alt' => _("Click to hide/show"),
            'title' => _("Click to hide/show")));
    }

    /* Callback when a new user creates or edits a page */
    public function CbNewUserEdit(&$request, $userid)
    {
        $content = "{{Template/UserPage}}";
        $dbi =& $request->_dbi;
        $page = $dbi->getPage($userid);
        $page->save($content, WIKIDB_FORCE_CREATE, array('author' => $userid));
        $dbi->touch();
    }

    /** CbUpload (&$request, $pathname) => true or false
     * Callback when a file is uploaded. virusscan, ...
     * @param $request
     * @param $pathname
     * @return bool   true for success, false to abort gracefully.
     * In case of false, the file is deleted by the caller, but the callback must
     * inform the user why the file was deleted.
     * Src:
     *   if (!$WikiTheme->CbUpload($request, $file_dir . $userfile_name))
     *      unlink($file_dir . $userfile_name);
     */
    public function CbUpload(&$request, $pathname)
    {
        $cmdline = CLAMDSCAN_PATH . " --nosummary --move=" . CLAMDSCAN_VIRUS;
        $report = `$cmdline "$pathname"`;
        if (!$report) {
            trigger_error("clamdscan failed", E_USER_WARNING);
            return true;
        }
        if (!preg_match("/: OK$/", $report)) {
            //preg_match("/: (.+)$/", $report, $m);
            trigger_error("Upload failed. virus-scanner: $report", E_USER_WARNING);
            return false;
        } else {
            return true;
        }
    }

    public function findTemplate($name)
    {
        // hack for navbar.tmpl to hide the button separator
        if ($name == "navbar") {
            $this->setButtonSeparator(HTML::raw("<br />\n&nbsp;&middot;&nbsp;"));
        }
        if ($name == "actionbar" || $name == "signin") {
            $this->setButtonSeparator(" ");
        }
        return parent::findTemplate($name);
    }

    public function load()
    {
        $this->initGlobals();

        // CSS file defines fonts, colors and background images for this
        // style.  The companion '*-heavy.css' file isn't defined, it's just
        // expected to be in the same directory that the base style is in.

        $this->setDefaultCSS(_("Sidebar"), array('' => 'sidebar.css',
            'print' => 'phpwiki-printer.css'));
        $this->addAlternateCSS(_("Printer"), 'phpwiki-printer.css');
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
        //$this->setAutosplitWikiWords(true);

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
         * https://www.php.net/manual/en/function.strftime.php for details.
         * Do not include the server's zone (%Z), times are converted to the
         * user's time zone.
         */
        //$this->setDateFormat("%d %B %Y");

        /**
         * Custom UserPreferences:
         * A list of name => _UserPreference class pairs.
         * Rationale: Certain themes should be able to extend the predefined list
         * of preferences. Display/editing is done in the theme specific userprefs.tmpl
         * but storage/sanification/update/... must be extended to the get/setPreferences methods.
         * See themes/wikilens/themeinfo.php
         */
        //$this->customUserPreference();

        /**
         * Register custom PageList type and define custom PageList classes.
         * Rationale: Certain themes should be able to extend the predefined list
         * of pagelist types. E.g. certain plugins, like MostPopular might use
         * info=pagename,hits,rating
         * which displays the rating column whenever the wikilens theme is active.
         * See themes/wikilens/themeinfo.php
         */
        //$this->addPageListColumn();
    }
}

$WikiTheme = new WikiTheme_Sidebar('Sidebar');
if (ENABLE_RATEIT) {
    require_once 'lib/wikilens/CustomPrefs.php';
    require_once 'lib/wikilens/PageListColumns.php';
    //require_once("lib/plugin/RateIt.php");
    $plugin = new WikiPlugin_RateIt();
    // add parent to current theme to find the RateIt images
    // $WikiTheme->addParent('wikilens', 'noinit');
    $WikiTheme->_parents[] = new WikiTheme('wikilens', 'noinit');
    $plugin->head();
}
