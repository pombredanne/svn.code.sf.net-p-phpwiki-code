<?php

/*
  This is the starting file for PhpWiki. All this file does
   is set configuration options, and at the end of the file 
   it includes() the file lib/main.php, where the real action begins.

   This file is divided into six parts: Parts Zero, One, Two, Three,
   Four and Five. Each one has different configuration settings you 
   can change; in all cases the default should work on your system, 
   however, we recommend you tailor things to your particular setting.
*/

/////////////////////////////////////////////////////////////////////
// Part Zero: If PHP needs help in finding where you installed the
//   rest of the PhpWiki code, you can set the include_path here.


//ini_set('include_path', '.:/where/you/installed/phpwiki');

/////////////////////////////////////////////////////////////////////
// Part Null: Don't touch this!

define ('PHPWIKI_VERSION', '1.3.0pre');
require "lib/prepend.php";
rcs_id('$Id: index.php,v 1.18 2001-07-12 03:21:35 wainstead Exp $');

/////////////////////////////////////////////////////////////////////
//
// Part One:
// Authentication and security settings:
// 
/////////////////////////////////////////////////////////////////////

// If set, we will perform reverse dns lookups to try to convert the users
// IP number to a host name, even if the http server didn't do it for us.
define('ENABLE_REVERSE_DNS', true);

// Username and password of administrator.
// Set these to your preferences. For heaven's sake
// pick a good password!
define('ADMIN_USER', "");
define('ADMIN_PASSWD', "");

// If true, only the admin user can make zip dumps, else
// zip dumps require no authentication.
define('ZIPDUMP_AUTH', false);

// The maximum file upload size.
define('MAX_UPLOAD_SIZE', 16 * 1024 * 1024);

// If the last edit is older than MINOR_EDIT_TIMEOUT seconds, the default
// state for the "minor edit" checkbox on the edit page form will be off.
define("MINOR_EDIT_TIMEOUT", 7 * 24 * 3600);

// Actions listed in this array will not be allowed.
//$DisabledActions = array('dumpserial', 'loadfile');

// PhpWiki can generate an access_log (in "NCSA combined log" format)
// for you.  If you want one, define this to the name of the log file.
define('ACCESS_LOG', '/tmp/wiki_access_log');

/////////////////////////////////////////////////////////////////////
//
// Part Two:
// Database Selection
//
/////////////////////////////////////////////////////////////////////

//
// This array holds the parameters which select the database to use.
//
// Not all of these parameters are used by any particular DB backend.
//
$DBParams = array(
   // Select the database type:
   // Uncomment one of these, or leave all commented for the default
   // data base type ('dba' if supported, else 'dbm'.)
   //'dbtype' => 'dba',
   //'dbtype' => 'dbm',
   //'dbtype' => 'mysql',
   //'dbtype' => 'pgsql',
   //'dbtype' => 'msql',
   //'dbtype' => 'file',
   
   // Used by all DB types:
   'database' => 'wiki',
   'prefix' => 'phpwiki_',	// prefix for filenames or table names
   
   // Used by 'dbm', 'dba', 'file'
   'directory' => "/tmp",

   // 'dbm' and 'dba create files named "$directory/${database}{$prefix}*".
   // 'file' creates files named "$directory/${database}/{$prefix}*/*".
   // The sql types use tables named "{$prefix}*"
   
   // Used by 'dbm', 'dba'
   'timeout' => 20,
   
   // Used by *sql as neccesary to log in to server:
   'server'   => 'localhost',
   'port'     => '',
   'socket'   => '',
   'user'     => 'guest',
   'password' => ''
);


/////////////////////////////////////////////////////////////////////
// 
// Part Three:
// Page appearance and layout
//
/////////////////////////////////////////////////////////////////////

// Select your language/locale - default language "C": English
// other languages available: Dutch "nl", Spanish "es", German "de",
// Swedish "sv", and Italian, "it".
//
// Note that on some systems, apprently using these short forms for
// the locale won't work.  On my home system 'LANG=de' won't result
// in german pages.  Somehow the system must recognize the locale
// as a valid locale before gettext() will work, i.e., use 'de_DE',
// 'nl_NL'.
$LANG='C';
//$LANG='nl_NL';

// Setting the LANG environment variable (accomplished above) may or
// may not be sufficient to cause PhpWiki to produce dates in your
// native language.  (It depends on the configuration of the operating
// system on your http server.)  The problem is that, e.g. 'de' is
// often not a valid locale.
//
// A standard locale name is typically of  the  form
// language[_territory][.codeset][@modifier],  where  language is
// an ISO 639 language code, territory is an ISO 3166 country code,
// and codeset  is  a  character  set or encoding identifier like
// ISO-8859-1 or UTF-8.
//
// You can tailor the locale used for time and date formatting by setting
// the LC_TIME environment variable.  You'll have to experiment to find
// the correct setting:
//putenv('LC_TIME=de_DE');

// If you specify a relative URL for the CSS and images,
// the are interpreted relative to DATA_PATH (see below).
// (The default value of DATA_PATH is the directory in which
// index.php (this file) resides.)

// CSS location
define("CSS_URL", "phpwiki.css");

// logo image (path relative to index.php)
$logo = "images/wikibase.png";

// Signature image which is shown after saving an edited page
// If this is left blank (or unset), the signature will be omitted.
//$SignatureImg = "images/signature.png";

// Date & time formats used to display modification times, etc.
// Formats are given as format strings to PHP strftime() function
// See http://www.php.net/manual/en/function.strftime.php for details.
$datetimeformat = "%B %e, %Y";	// may contain time of day
$dateformat = "%B %e, %Y";	// must not contain time

// this defines how many page names to list when displaying
// the MostPopular pages; the default is to show the 20 most popular pages
define("MOST_POPULAR_LIST_LENGTH", 20);

// this defines how many page names to list when displaying related pages
define("NUM_RELATED_PAGES", 5);

// Template files (filenames are relative to script position)
// However, if a LANG is set, they we be searched for in a locale
// specific location first.
$templates = array("BROWSE" =>    "templates/browse.html",
		   "EDITPAGE" =>  "templates/editpage.html",
		   "MESSAGE" =>   "templates/message.html");

/* WIKI_PGSRC -- specifies the source for the initial page contents
 * of the Wiki.  The setting of WIKI_PGSRC only has effect when
 * the wiki is accessed for the first time (or after clearing the
 * database.) WIKI_PGSRC can either name a directory or a zip file.
 * In either case WIKI_PGSRC is scanned for files --- one file per page.
 */
define('WIKI_PGSRC', "pgsrc"); // Default (old) behavior.
//define('WIKI_PGSRC', 'wiki.zip'); // New style.

// DEFAULT_WIKI_PGSRC is only used when the language is *not*
// the default (English) and when reading from a directory:
// in that case some English pages are inserted into the wiki as well
// DEFAULT_WIKI_PGSRC defines where the English pages reside 
// FIXME: is this really needed?  Can't we just copy
//  these pages into the localized pgsrc?
define('DEFAULT_WIKI_PGSRC', "pgsrc");
// These are the pages which will get loaded from DEFAULT_WIKI_PGSRC.	
$GenericPages = array("ReleaseNotes", "SteveWainstead", "TestPage");

/////////////////////////////////////////////////////////////////////
//
// Part four:
// Mark-up options.
// 
/////////////////////////////////////////////////////////////////////

// allowed protocols for links - be careful not to allow "javascript:"
// URL of these types will be automatically linked.
// within a named link [name|uri] one more protocol is defined: phpwiki
$AllowedProtocols = "http|https|mailto|ftp|news|gopher";

// URLs ending with the following extension should be inlined as images
$InlineImages = "png|jpg|gif";

// Perl regexp for WikiNames ("bumpy words")
// (?<!..) & (?!...) used instead of '\b' because \b matches '_' as well
$WikiNameRegexp = "(?<![[:alnum:]])([[:upper:]][[:lower:]]+){2,}(?![[:alnum:]])";

// InterWiki linking -- wiki-style links to other wikis on the web
//
// Intermap file for InterWikiLinks -- define other wikis there
// Leave this undefined to disable InterWiki linking.
define('INTERWIKI_MAP_FILE', "lib/interwiki.map");

/////////////////////////////////////////////////////////////////////
//
// Part five:
// URL options -- you can probably skip this section.
//
/////////////////////////////////////////////////////////////////////
/******************************************************************
 *
 * The following section contains settings which you can use to tailor
 * the URLs which PhpWiki generates. 
 *
 * Any of these parameters which are left undefined will be
 * deduced automatically.  You need only set them explicitly
 * if the auto-detected values prove to be incorrect.
 *
 * In most cases the auto-detected values should work fine,
 * so hopefully you don't need to mess with this section.
 *
 ******************************************************************/

/*
 * Canonical name and httpd port of the server on which this
 * PhpWiki resides.
 */
//define('SERVER_NAME', 'some.host.com');
//define('SERVER_PORT', 80);

/*
 * Absolute URL (from the server root) of the PhpWiki
 * script.
 */
//define('SCRIPT_NAME', '/some/where/index.php');

/*
 * Absolute URL (from the server root) of the directory
 * in which relative URL's for images and other support files
 * are interpreted.
 */
//define('DATA_PATH', '/some/where');

/*
 * Define to 'true' to use PATH_INFO to pass the pagename's.
 * e.g. http://www.some.where/index.php/HomePage instead
 * of http://www.some.where/index.php?pagename=HomePage
 * FIXME: more docs (maybe in README).
 */
//define('USE_PATH_INFO', false);

/*
 * VIRTUAL_PATH is the canonical URL path under which your
 * your wiki appears.  Normally this is the same as
 * dirname(SCRIPT_NAME), however using, e.g. apaches mod_actions
 * (or mod_rewrite), you can make it something different.
 *
 * If you do this, you should set VIRTUAL_PATH here.
 *
 * E.g. your phpwiki might be installed at at /scripts/phpwiki/index.php,
 * but  * you've made it accessible through eg. /wiki/HomePage.
 *
 * One way to do this is to create a directory named 'wiki' in your
 * server root.  The directory contains only one file: an .htaccess
 * file which reads something like:
 *
 *    Action x-phpwiki-page /scripts/phpwiki/index.php
 *    SetHandler x-phpwiki-page
 *    DirectoryIndex /scripts/phpwiki/index.php
 *
 * In that case you should set VIRTUAL_PATH to '/wiki'.
 *
 * (VIRTUAL_PATH is only used if USE_PATH_INFO is true.)
 */
//define('VIRTUAL_PATH', '/SomeWiki');


////////////////////////////////////////////////////////////////
// Okay... fire up the code:
////////////////////////////////////////////////////////////////

include "lib/main.php";

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
