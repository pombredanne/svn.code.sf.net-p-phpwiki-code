<?php // -*-php-*-
rcs_id('$Id: PluginManager.php,v 1.10 2003-11-30 18:23:48 carstenklapp Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// TODO:
// Some of this code can be simplified with the relatively new
// WikiLink($p, 'auto') function.

// Set this to true if you don't want regular users to view this page.
// So far there are no known security issues.
define('REQUIRE_ADMIN', false);

class WikiPlugin_PluginManager
extends WikiPlugin
{
    function getName () {
        return _("PluginManager");
    }

    function getDescription () {
        return _("Provides a list of plugins on this wiki.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.10 $");
    }

    function getDefaultArguments() {
        return array('info' => 'args');
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $h = HTML();
        $this->_generatePageheader($info, $h);

        if (! REQUIRE_ADMIN || $request->_user->isadmin()) {
            $h->pushContent(HTML::h2(_("Plugins")));

            $table = HTML::table(array('class' => "pagelist"));
            $this->_generateColgroups($info, $table);
            $this->_generateColheadings($info, $table);
            $this->_generateTableBody($info, $dbi, $request, $table);
            $h->pushContent($table);

            //$h->pushContent(HTML::h2(_("Disabled Plugins")));
        }
        else {
            $h->pushContent(fmt("You must be an administrator to %s.",
                                _("use this plugin")));
        }
        return $h;
    }

    function _generatePageheader(&$info, &$html) {
        $html->pushContent(HTML::p($this->getDescription()));
    }

    function _generateColgroups(&$info, &$table) {
        // specify last two column widths
        $colgroup = HTML::colgroup();
        $colgroup->pushContent(HTML::col(array('width' => '0*')));
        $colgroup->pushContent(HTML::col(array('width' => '0*',
                                               'align' => 'right')));
        $colgroup->pushContent(HTML::col(array('width' => '9*')));
        if ($info == 'args')
            $colgroup->pushContent(HTML::col(array('width' => '2*')));
        $table->pushcontent($colgroup);
    }

    function _generateColheadings(&$info, &$table) {
        // table headings
        $tr = HTML::tr();
        $headings = array(_("Plugin"), _("Version"), _("Description"));
        if ($info == 'args')
            $headings []= _("Arguments");
        foreach ($headings as $title) {
            $tr->pushContent(HTML::td($title));
        }
        $table->pushContent(HTML::thead($tr));
    }

    function _generateTableBody(&$info, &$dbi, &$request, &$table) {
        $row_no = 0;
        $plugin_dir = 'lib/plugin';
        if (defined('PHPWIKI_DIR'))
            $plugin_dir = PHPWIKI_DIR . "/$plugin_dir";
        $pd = new fileSet($plugin_dir, '*.php');
        $plugins = $pd->getFiles();
        sort($plugins);
        // table body
        $tbody = HTML::tbody();
        global $WikiNameRegexp;
        foreach($plugins as $pname) {
            // instantiate a plugin
            $pname = str_replace(".php", "", $pname);
            $temppluginclass = "<? plugin $pname ?>"; // hackish
            $w = new WikiPluginLoader;
            // obtain plugin name & description
            $p = $w->getPlugin($pname, false); // second arg?
            $desc = $p->getDescription();
            // obtain plugin version
            if (method_exists($p, 'getVersion')) {
                $ver = $p->getVersion();
            }
            else {
                $ver = "--";
            }
            // obtain plugin's default arguments
            $arguments = HTML();
            $args = $p->getDefaultArguments();
            
            foreach ($args as $arg => $default) {
                if (stristr($default, ' '))
                    $default = "'$default'";
                $arguments->pushcontent("$arg=$default", HTML::br());
            }
            // make a link if an actionpage exists
            $pnamelink = $pname;
            $plink = false;
            // Also look for pages in the current locale
            if (_($pname) != $pname) {
                $l1 = _($pname);
            }
            else
                $l1 = '';
            if (preg_match("/^$WikiNameRegexp\$/", $pname)
                && $dbi->isWikiPage($pname)) {
                $pnamelink = HTML(WikiLink($pname));
            }
            // make another link if an XxxYyyPlugin page exists
            $ppname = $pname . "Plugin";
            // Also look for pages in the current locale
            if (_($ppname) != $ppname) {
                $l2 = _($ppname);
            }
            else
                $l2 = '';
            if (preg_match("/^$WikiNameRegexp\$/", $ppname)
                && $dbi->isWikiPage($ppname)) {
                $plink = HTML(WikiLink($ppname));
            }
            else {
                // don't link to actionpages and plugins starting with
                // an _ from page list
                if (!preg_match("/^_/", $pname)
                    //&& !(@$request->isActionPage($pname)) //FIXME?
                    ) {
                    // $plink = WikiLink($ppname, 'unknown');
                    global $Theme;
                    $plink = $Theme->linkUnknownWikiWord($ppname);
                }
                else
                    $plink = false;
            }
            // insert any found locale-specific pages at the bottom of
            // the td
            if ($l1 || $l2) {
                // really this should all just be put into a new <p>
                $par = HTML::p();
                //$plink->pushContent(HTML::br());
                //$plink->pushContent(HTML::br());
                if ($l1) {
                    // Don't offer to create a link to a non-wikiword
                    // localized plugin page but show those that
                    // already exist (Calendar, Comment, etc.)  (Non
                    // non-wikiword plugins are okay, they just can't
                    // become actionPages.)
                    if (preg_match("/^$WikiNameRegexp\$/", $l1) || $dbi->isWikiPage($l1)) {
                        $par->pushContent(WikiLink($l1, 'auto'));
                    }
                    else {
                        // probably incorrectly translated, so no page link
                        $par->pushContent($l1, ' ' . _("(Not a WikiWord)"));
                    }
                }
                if ($l1 && $l2)
                    $par->pushContent(HTML::br());
                if ($l2) {
                    if (preg_match("/^$WikiNameRegexp\$/", $l2) || $dbi->isWikiPage($l2)) {
                        $par->pushContent(WikiLink($l2, 'auto'));
                    }
                    else {
                        // probably incorrectly translated, so no page link
                        $par->pushContent($l2, ' ' . _("(Not a WikiWord)"));
                    }
                }
                $plink->pushContent($par);
            }

            // highlight alternate rows
            $row_no++;
            $group = (int)($row_no / 1); //_group_rows
            $class = ($group % 2) ? 'evenrow' : 'oddrow';
            // generate table row
            $tr = HTML::tr(array('class' => $class));
            if ($plink) {
                // plugin has a XxxYyyPlugin page
                $tr->pushContent(HTML::td($pnamelink, HTML::br(), $plink));
                $plink = false;
                //$row_no++;
            }
            else {
                // plugin just has an actionpage
                $tr->pushContent(HTML::td($pnamelink));
            }
            $tr->pushContent(HTML::td($ver), HTML::td($desc));
            if ($info == 'args') {
                // add Arguments column
                $style = array('style'
                               => 'font-family:monospace;font-size:smaller');
                $tr->pushContent(HTML::td($style, $arguments));
            }
            $tbody->pushContent($tr);
        }
        $table->pushContent($tbody);
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.9  2003/11/19 00:02:42  carstenklapp
// Include found locale-specific pages for the current (non-English)
// locale.
//
// Revision 1.8  2003/11/15 21:53:53  wainstead
// Minor change: list plugins in asciibetical order. It'd be better if
// they were alphabetical.
//
// Revision 1.7  2003/02/24 01:36:25  dairiki
// Don't use PHPWIKI_DIR unless it's defined.
// (Also typo/bugfix in SystemInfo plugin.)
//
// Revision 1.6  2003/02/24 00:56:53  carstenklapp
// Updated to work with recent changes to WikiLink function (fix
// "==Object(wikipagename)==" for unknown wiki links).
//
// Revision 1.5  2003/02/22 20:49:56  dairiki
// Fixes for "Call-time pass by reference has been deprecated" errors.
//
// Revision 1.4  2003/02/20 18:13:38  carstenklapp
// Workaround for recent changes to WikiPlugin->getPlugin.
// Made admin restriction for viewing this page optional.
// Now defaults to any user may view this page (mainly for PhpWiki Demo site).
// Minor code changes & reformatting.
//
// Revision 1.3  2003/01/04 02:30:12  carstenklapp
// Added 'info' argument to show / hide plugin "Arguments"
// column. Improved row highlighting and error message when viewed by
// non-admin user. Code refactored. Added copyleft.

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
