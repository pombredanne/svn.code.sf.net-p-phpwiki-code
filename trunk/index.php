<?php
define ('PHPWIKI_VERSION', '1.3.0pre');
error_reporting(E_ALL /* ^ E_NOTICE */);

$RCS_IDS = array("SCRIPT_NAME='$SCRIPT_NAME'",
		 '$Id: index.php,v 1.7 2001-02-12 01:43:09 dairiki Exp $');

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

/////////////////////////////////////////////////////////////////////
//
// Part Two:
// Database Selection
//
/////////////////////////////////////////////////////////////////////

// Pick one of 'dbm', 'dba', 'mysql', 'pgsql', 'msql', or 'file'.
// (Or leaven DBTYPE undefined for default behavior (which is 'dba'
// if supported, else 'dbm').

//define("DBTYPE", 'mysql');

// 'dbm' and 'dba create files named "$directory/${database}{$prefix}*".
// 'file' creates files named "$directory/${database}/{$prefix}*/*".
// The sql types use tables named "{$prefix}*"

//
// This array holds the parameters which select the database to use.
//
// Not all of these parameters are used by any particular DB backend.
//
$DBParams = array(
   // Used by all DB types:
   'database' => 'wiki',
   'prefix' => '',	// prefix for filenames or table names
   
   // Used by 'dbm', 'dba', 'file'
   'directory' => "/tmp",

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

// Select your language - default language "C": English
// other languages available: Dutch "nl", Spanish "es", German "de",
// and Swedish "sv"
$LANG = "C";

// logo image (path relative to index.php)
$logo = "images/wikibase.png";

// Signature image which is shown after saving an edited page
// If this is left blank (or unset), the signature will be omitted.
//$SignatureImg = "images/signature.png";

// date & time formats used to display modification times, etc.
// formats are given as format strings to PHP date() function
// FIXME: these should have different defaults depending on locale.
$datetimeformat = "F j, Y";	// may contain time of day
$dateformat = "F j, Y";	// must not contain time

// this defines how many page names to list when displaying
// the MostPopular pages; the default is to show the 20 most popular pages
define("MOST_POPULAR_LIST_LENGTH", 20);

// this defines how many page names to list when displaying related pages
define("NUM_RELATED_PAGES", 5);

// This path is searched when trying to read WIKI_PGSRC
// or template files.
$DataPath = array(".", "locale/$LANG");

// Template files (filenames are relative to script position)
// (These filenames will be passed through gettext() before use.)
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
$WikiNameRegexp = "(?<![A-Za-z0-9])([A-Z][a-z]+){2,}(?![A-Za-z0-9])";

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
//define('PHPWIKI_SERVER_NAME', 'some.host.com');
//define('PHPWIKI_SERVER_PORT', 80);

/*
 * Absolute URL (from the server root) of the PhpWiki
 * script.
 */
//define('PHPWIKI_SCRIPT_NAME', '/some/where/index.php');

/*
 * Absolute URL (from the server root) of the directory
 * in which relative URL's for images and other support files
 * are interpreted.
 */
//define('PHPWIKI_DATA_PATH', '/some/where');

/*
 * Define to 'true' to use PATH_INFO to pass the pagename's.
 * e.g. http://www.some.where/index.php/FrontPage instead
 * of http://www.some.where/index.php?pagename=FrontPage
 * FIXME: more docs (maybe in README).
 */
//define('USE_PATH_INFO', false);

/*
 * FIXME: add docs
 * (Only used if USE_PATH_INFO is true.)
 */
//define('PHPWIKI_VIRTUAL_PATH', '/SomeWiki');


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
