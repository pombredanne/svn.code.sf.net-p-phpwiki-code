<?php
rcs_id('$Id: config.php,v 1.34 2001-02-16 04:43:07 dairiki Exp $');
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
if (empty($LANG))
   $LANG = "C";

   
// Search PHP's include_path to find file or directory.
function FindFile ($file, $missing_okay = false)
{
   // FIXME: This wont work for DOS filenames.
   if (ereg('^/', $file))
   {
      // absolute path.
      if (file_exists($file))
	 return $file;
   }
   else
   {
      $include_path = ini_get('include_path');
      if (empty($include_path))
	 $include_path = '.';
      // FIXME: This wont work for DOS filenames.
      $path = explode(':', $include_path);
      while (list($i, $dir) = each ($path))
	 if (file_exists("$dir/$file"))
	    return "$dir/$file";
   }
   
   if (!$missing_okay)
      ExitWiki("$file: file not found");
   return false;
}

// Search PHP's include_path to find file or directory.
// Searches for "locale/$LANG/$file", then for "$file".
function FindLocalizedFile ($file, $missing_okay = false)
{
   global $LANG;
   
   // FIXME: This wont work for DOS filenames.
   if (!ereg('^/', $file))
   {
      if ( ($path = FindFile("locale/$LANG/$file", 'missing_is_okay')) )
	 return $path;
   }
   return FindFile($file, $missing_okay);
}

if (!function_exists ('gettext'))
{
   $locale = array();

   function gettext ($text) { 
      global $locale;
      if (!empty ($locale[$text]))
	 return $locale[$text];
      return $text;
   }

   if ( ($lcfile = FindLocalizedFile("LC_MESSAGES/phpwiki.php", 'missing_ok')) )
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
   if (php_sapi_name() == 'apache')
      define('USE_PATH_INFO', true);
   else
      define('USE_PATH_INFO', ereg('\.(php3?|cgi)$', $SCRIPT_NAME));
}


function IsProbablyRedirectToIndex () 
{
   // This might be a redirect to the DirectoryIndex,
   // e.g. REQUEST_URI = /dir/  got redirected
   // to SCRIPT_NAME = /dir/index.php
   
   // In this case, the proper virtual path is still
   // $SCRIPT_NAME, since pages appear at
   // e.g. /dir/index.php/FrontPage.
   
   global $REQUEST_URI, $SCRIPT_NAME;
   
   $requri = preg_quote($REQUEST_URI, '%');
   return preg_match("%^${requri}[^/]*$%", $SCRIPT_NAME);
}

   
if (!defined('VIRTUAL_PATH'))
{
   // We'd like to auto-detect when the cases where apaches
   // 'Action' directive (or similar means) is used to
   // redirect page requests to a cgi-handler.
   //
   // In cases like this, requests for e.g. /wiki/FrontPage
   // get redirected to a cgi-script called, say,
   // /path/to/wiki/index.php.  The script gets all
   // of /wiki/FrontPage as it's PATH_INFO.
   //
   // The problem is:
   //   How to detect when this has happened reliably?
   //   How to pick out the "virtual path" (in this case '/wiki')?
   //
   // (Another time an redirect might occur is to a DirectoryIndex
   // -- the requested URI is '/wikidir/', the request gets
   // passed to '/wikidir/index.php'.  In this case, the
   // proper VIRTUAL_PATH is '/wikidir/index.php', since the
   // pages will appear at e.g. '/wikidir/index.php/FrontPage'.
   //

   if (USE_PATH_INFO and isset($REDIRECT_URL)
       and ! IsProbablyRedirectToIndex())
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
if (empty($DBParams['dbtype']))
{
   if ( floor(phpversion()) == 3) {
      $DBParams['dbtype'] = 'dbm';
   } else {
      $DBParams['dbtype'] = 'dba';
   }
}

switch ($DBParams['dbtype']) 
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
      ExitWiki($DBParams['dbtype'] . ": unknown DBTYPE");
}

// InterWiki linking -- wiki-style links to other wikis on the web
//
if (defined('INTERWIKI_MAP_FILE'))
{
   include ('lib/interwiki.php');
}

// Access log
if (!defined('ACCESS_LOG'))
   define('ACCESS_LOG', '');
   
// Get remote host name, if apache hasn't done it for us
if (empty($REMOTE_HOST) && ENABLE_REVERSE_DNS)
   $REMOTE_HOST = gethostbyaddr($REMOTE_ADDR);

   
// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
