<?php
rcs_id('$Id: config.php,v 1.30 2001-02-12 01:43:10 dairiki Exp $');
/*
 * NOTE: the settings here should probably not need to be changed.
 *
 *
 * (The user-configurable settings have been moved to index.php.)
 */

// essential internal stuff

set_magic_quotes_runtime(0);

// Some constants.

// "\x80"-"\x9f" (and "\x00" - "\x1f") are non-printing control
// chars in iso-8859-*
// $FieldSeparator = "\263"; //this is a superscript 3 in ISO-8859-1.
$FieldSeparator = "\x81";


// constants for flags in $pagehash
define("FLAG_PAGE_LOCKED", 1);

//////////////////////////////////////////////////////////////////
//
// Set up localization
//
if (!function_exists ('gettext'))
{
   $locale = array();

   function gettext ($text) { 
      global $locale;
      if (!empty ($locale[$text]))
	 return $locale[$text];
      return $text;
   }

   if ( ($lcfile = SearchPath("LC_MESSAGES/phpwiki.php", 'missing_ok')) )
   {
      include($lcfile);
   }
}
else
{
   putenv ("LANG=$LANG");
   bindtextdomain ("phpwiki", "./locale");
   textdomain ("phpwiki");
}

//////////////////////////////////////////////////////////////////
// Autodetect URL settings:
//
if (!defined('SERVER_NAME')) define('SERVER_NAME', $SERVER_NAME);
if (!defined('SERVER_PORT')) define('SERVER_PORT', $SERVER_PORT);
if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', $SCRIPT_NAME);
if (!defined('DATA_PATH'))
   define('DATA_PATH', dirname(SCRIPT_NAME));
if (!defined('USE_PATH_INFO'))
{
   /*
    * If SCRIPT_NAME does not look like php source file,
    * or user cgi we assume that php is getting run by an
    * action handler in /cgi-bin.  In this case,
    * I think there is no way to get Apache to pass
    * useful PATH_INFO to the php script (PATH_INFO
    * is used to the the php interpreter where the
    * php script is...)
    */
   define('USE_PATH_INFO', ereg('\.(php3?|cgi)$', $SCRIPT_NAME));
}
if (!defined('VIRTUAL_PATH'))
{
   if (USE_PATH_INFO and isset($REDIRECT_URL))
   {
      // FIXME: This is a hack, and won't work if the requested
      // pagename has a slash in it.
      define('VIRTUAL_PATH', dirname($REDIRECT_URL . 'x'));
   }
   else
      define('VIRTUAL_PATH', SCRIPT_NAME);
}

if (SERVER_PORT && SERVER_PORT != 80)
   define('SERVER_URL',
	  "http://" . SERVER_NAME . ':' . SERVER_PORT);
else
   define('SERVER_URL',
	  "http://" . SERVER_NAME);

if (VIRTUAL_PATH != SCRIPT_NAME)
{
   // Apache action handlers are used.
   define('PATH_INFO_PREFIX', VIRTUAL_PATH . "/");
}
else
   define("PATH_INFO_PREFIX", '/');


//////////////////////////////////////////////////////////////////
// Select database
//
if (!defined('DBTYPE'))
{
   if ( floor(phpversion()) == 3) {
      define('DBTYPE', 'dbm');
   } else {
      define('DBTYPE', 'dba');
   }
}

switch (DBTYPE) 
{
   case 'dbm':
      include 'lib/dbmlib.php';
      break;
   case 'dba':
      include 'lib/dbalib.php';
      break;
   case 'mysql':
      include 'lib/mysql.php';
      break;
   case 'pgsql':
      include 'lib/pgsql.php';
      break;
   case 'msql':
      include 'lib/msql.php';
      break;
   case 'file':
      include "lib/db_filesystem.php";
      break;
   default:
      die(DBTYPE . ": unknown DBTYPE");
}

// InterWiki linking -- wiki-style links to other wikis on the web
//
if (defined('INTERWIKI_MAP_FILE'))
{
   include ('lib/interwiki.php');
}

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
