<?php // -*-php-*-
// iso-8859-1

// IMPORTANT NOTE: Use of the ***configurator.php*** to generate an
// index.php is depreciated, because it is out of date and a new
// configuration system is in the works (see the config directory, not
// finished yet though). DO compare or diff the configurator's output
// against this file if you feel you must use it to generate an
// index.php!

/*
Copyright 1999,2000,2001,2002,2003,2004 $ThePhpWikiProgrammingTeam 
= array(
"Steve Wainstead", "Clifford A. Adams", "Lawrence Akka", 
"Scott R. Anderson", "Jon Åslund", "Neil Brown", "Jeff Dairiki",
"Stéphane Gourichon", "Jan Hidders", "Arno Hollosi", "John Jorgensen",
"Antti Kaihola", "Jeremie Kass", "Carsten Klapp", "Marco Milanesi",
"Grant Morgan", "Jan Nieuwenhuizen", "Aredridel Niothke", 
"Pablo Roca Rozas", "Sandino Araico Sánchez", "Joel Uckelman", 
"Reini Urban", "Joby Walker", "Tim Voght", "Jochen Kalmbach");

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

require_once (dirname(__FILE__).'/lib/prepend.php');
rcs_id('$Id: index.php,v 1.142 2004-04-26 12:16:40 rurban Exp $');

require_once(dirname(__FILE__).'/lib/IniConfig.php');
IniConfig(dirname(__FILE__)."/config/config.ini");

//if (defined('WIKI_SOAP') and WIKI_SOAP) return;

////////////////////////////////////////////////////////////////
// PrettyWiki
// Check if we were included by some other wiki version 
// (getimg.php, en, de, wiki, ...) or not. 
// If the server requested this index.php fire up the code by loading lib/main.php.
// Parallel wiki scripts can now simply include /index.php for the 
// main configuration, extend or redefine some settings and 
// load lib/main.php by themselves. See the file 'wiki'.
// This overcomes the IndexAsConfigProblem.
// Generally a simple 
//   define('VIRTUAL_PATH', $_SERVER['SCRIPT_NAME']);
// is enough in the wiki file, plus the action definition in a .htaccess file
////////////////////////////////////////////////////////////////

// If your lib/main.php is not loaded, comment that out, and  
// uncomment the include "lib/main.php" line below.
if (defined('VIRTUAL_PATH') and defined('USE_PATH_INFO')) {
    if ($HTTP_SERVER_VARS['SCRIPT_NAME'] != VIRTUAL_PATH) {
        include(dirname(__FILE__)."/lib/main.php");
    }
    elseif (defined('SCRIPT_NAME') and 
            ($HTTP_SERVER_VARS['SCRIPT_NAME'] != SCRIPT_NAME)) {
        include(dirname(__FILE__)."/lib/main.php");
    }
} else {
    if (defined('SCRIPT_NAME') and 
        ($HTTP_SERVER_VARS['SCRIPT_NAME'] == SCRIPT_NAME)) {
        include(dirname(__FILE__)."/lib/main.php");
    } elseif (strstr($HTTP_SERVER_VARS['PHP_SELF'],'index.php')) {
        include(dirname(__FILE__)."/lib/main.php");
    }
}
//include(dirname(__FILE__)."/lib/main.php");

// $Log: not supported by cvs2svn $
// Revision 1.141  2004/04/19 23:13:02  zorloc
// Connect the rest of PhpWiki to the IniConfig system.  Also the keyword regular expression is not a config setting
//
// Revision 1.140  2004/04/12 18:29:12  rurban
// exp. Session auth for already authenticated users from another app
//
// Revision 1.139  2004/04/12 16:24:28  rurban
// 1.3.10pre, JS_SEARCHREPLACE => pref option
//
// Revision 1.138  2004/04/12 12:27:07  rurban
// more notes and themes
//
// Revision 1.137  2004/04/11 10:42:02  rurban
// pgsrc/CreatePagePlugin
//
// Revision 1.136  2004/04/10 04:14:13  rurban
// sf.net 906436 Suggestion
//
// Revision 1.135  2004/04/10 03:33:03  rurban
// Oops revert
//
// Revision 1.134  2004/04/10 02:55:48  rurban
// fixed old WikiUser
//
// Revision 1.133  2004/04/08 01:22:53  rurban
// fixed PageChange Notification
//
// Revision 1.132  2004/04/01 15:57:10  rurban
// simplified Sidebar theme: table, not absolute css positioning
// added the new box methods.
// remaining problems: large left margin, how to override _autosplitWikiWords in Template only
//
// Revision 1.131  2004/03/14 16:24:35  rurban
// authenti(fi)cation spelling
//
// Revision 1.130  2004/03/09 17:16:43  rurban
// fixed $LDAP_SET_OPTION
//
// Revision 1.129  2004/02/29 04:10:55  rurban
// new POP3 auth (thanks to BiloBilo: pentothal at despammed dot com)
// fixed syntax error in index.php
//
// Revision 1.128  2004/02/29 02:06:05  rurban
// And this is the SOAP server. Just a view methods for now. (page content)
// I would like to see common-wiki soap wdsl.
//
// "SOAP is a bloated, over engineered mess of a perfectly trivial concept. Sigh."
//   -- http://www.wlug.org.nz/SOAP
//
// Revision 1.127  2004/02/28 21:18:29  rurban
// new SQL auth_create, don't ever use REPLACE sql calls!
// moved HttpAuth to the end of the chain
// PrettyWiki enabled again
//
// Revision 1.126  2004/02/27 16:27:48  rurban
// REPLACE is a dirty hack, and erases passwd btw.
//
// Revision 1.125  2004/02/24 02:51:57  rurban
// release 1.3.8 ready
//
// Revision 1.124  2004/02/16 00:20:30  rurban
// new Japanses language
//
// Revision 1.123  2004/02/09 03:58:07  rurban
// for now default DB_SESSION to false
// PagePerm:
//   * not existing perms will now query the parent, and not
//     return the default perm
//   * added pagePermissions func which returns the object per page
//   * added getAccessDescription
// WikiUserNew:
//   * added global ->prepare (not yet used) with smart user/pref/member table prefixing.
//   * force init of authdbh in the 2 db classes
// main:
//   * fixed session handling (not triple auth request anymore)
//   * don't store cookie prefs with sessions
// stdlib: global obj2hash helper from _AuthInfo, also needed for PagePerm
//
// Revision 1.122  2004/02/07 14:20:18  rurban
// consistent mysql schema with index.php (userid)
//
// Revision 1.121  2004/02/07 10:41:25  rurban
// fixed auth from session (still double code but works)
// fixed GroupDB
// fixed DbPassUser upgrade and policy=old
// added GroupLdap
//
// Revision 1.120  2004/02/03 09:45:39  rurban
// LDAP cleanup, start of new Pref classes
//
// Revision 1.119  2004/02/01 09:14:10  rurban
// Started with Group_Ldap (not yet ready)
// added new _AuthInfo plugin to help in auth problems (warning: may display passwords)
// fixed some configurator vars
// renamed LDAP_AUTH_SEARCH to LDAP_BASE_DN
// changed PHPWIKI_VERSION from 1.3.8a to 1.3.8pre
// USE_DB_SESSION defaults to true on SQL
// changed GROUP_METHOD definition to string, not constants
// changed sample user DBAuthParams from UPDATE to REPLACE to be able to
//   create users. (Not to be used with external databases generally, but
//   with the default internal user table)
//
// fixed the IndexAsConfigProblem logic. this was flawed:
//   scripts which are the same virtual path defined their own lib/main call
//   (hmm, have to test this better, phpwiki.sf.net/demo works again)
//
// Revision 1.118  2004/01/28 14:34:13  rurban
// session table takes the common prefix
// + various minor stuff
// reallow password changing
//
// Revision 1.117  2004/01/27 23:25:50  rurban
// added new tables to mysql schema
// fixed default DBAUthParam samples to match these
// added group constants (look terrible, I'd prefer strings instead of constants)
//
// Revision 1.116  2004/01/25 04:21:02  rurban
// WikiUserNew support (temp. ENABLE_USER_NEW constant)
//
// Revision 1.115  2003/12/22 04:58:11  carstenklapp
// Incremented release version.
//
// Revision 1.114  2003/12/05 16:00:42  carstenklapp
// ACK! gettext is not available at this point in index.php.
//
// Revision 1.113  2003/12/05 15:51:37  carstenklapp
// Added note that use of the configurator is depreciated.
//
// Enable localization/gettextification of $KeywordLinkRegexp. (Also, now
// users not familiar with regex can more easily just edit the $keywords
// array).
//
// Added four new constants to define author and copyright link rel~s
// used in html head. This makes it easier to run multiple wikis off of
// one set of code.
//
// Eliminated RECENT_CHANGES constant for RSS auto discovery because it's
// another step to watch out for when running a non-english wiki. Now
// simply defined as _("RecentChanges") in head.tmpl itself. Non-standard
// wikis where the RecentChanges page has been named to something else
// will have to modify this in head.tmpl (along with all other places the
// word RecentChanges appears in the code, something that already would
// have had to be done on such a wiki anyway).
//
// Added a little more info and instructions to flesh out:
// DEBUG, WIKI_NAME, ADMIN_USER, $DisabledActions, $DBParams, CHARSET.
//
// A few typos and spelling mistakes corrected, and some text rewrapped.
//
// Revision 1.112  2003/11/17 15:49:21  carstenklapp
// Updated version number to 1.3.7pre (beyond current release
// 1.3.6). Disabled DEBUG output by default (hide DebugInfo, XHTML &
// CSS validator buttons). Note the DebugInfo button remains visible
// for the Admin, and can be accessed by anyone else by adding
// "?action=DebugInfo" to the URL for the occasional use.
//
// Revision 1.111  2003/03/18 21:40:04  dairiki
// Copy Lawrence's memo on USE_PATH_INFO/AcceptPathInfo to configurator.php
// (as promised).
//
// Plus slight clarification of default (auto-detect) behavior.
//
// Revision 1.110  2003/03/18 20:51:10  lakka
// Revised comments on use of USE_PATH_INFO with Apache 2
//
// Revision 1.109  2003/03/17 21:24:50  dairiki
// Fix security bugs in the RawHtml plugin.
//
// Change the default configuration to allow use of plugin, since
// I believe the plugin is now safe for general use. (Raw HTML will only
// work on locked pages.)
//
// Revision 1.108  2003/03/07 22:47:01  dairiki
// A few more if(!defined(...))'s
//
// Revision 1.107  2003/03/07 20:51:54  dairiki
// New feature: Automatic extraction of keywords (for the meta keywords tag)
// from Category* and Topic* links on each page.
//
// Revision 1.106  2003/03/07 02:48:23  dairiki
// Add option to prevent HTTP redirect.
//
// Revision 1.105  2003/03/04 02:08:08  dairiki
// Fix and document the WIKIDB_NOCACHE_MARKUP config define.
//
// Revision 1.104  2003/02/26 02:55:52  dairiki
// New config settings in index.php to control cache control strictness.
//
// Revision 1.103  2003/02/22 19:43:50  dairiki
// Fix comment regarding connecting to SQL server over a unix socket.
//
// Revision 1.102  2003/02/22 18:53:38  dairiki
// Renamed method Request::compress_output to Request::buffer_output.
//
// Added config option to disable compression.
//
// Revision 1.101  2003/02/21 19:29:30  dairiki
// Update PHPWIKI_VERSION to 1.3.5pre.
//
// Revision 1.100  2003/01/04 03:36:58  wainstead
// Added 'file' as a database type alongside 'dbm'; added cvs log tag
//

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>