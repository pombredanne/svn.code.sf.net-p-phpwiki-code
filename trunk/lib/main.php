<?php $RCS_IDS[] = '$Id: main.php,v 1.1 2001-02-12 01:43:10 dairiki Exp $';
function rcs_id($id) { $GLOBALS['RCS_IDS'][] = $id; }
include "lib/config.php";
include "lib/stdlib.php";
include "lib/userauth.php";


function DeducePagename () 
{
   global $pagename, $PATH_INFO;
   
   if (isset($pagename))
      return fix_magic_quotes_gpc($pagename);

   if (USE_PATH_INFO)
      if (ereg('^' . PATH_INFO_PREFIX . '(.*)$', $PATH_INFO, $m))
	 return $m[1];

   return gettext("FrontPage");
}

$pagename = DeducePagename();

if (!empty($action))
{
   $action = trim(fix_magic_quotes_gpc($action));
}
else if (isset($diff))
{
   // Fix for compatibility with very old diff links in RecentChanges.
   // (The [phpwiki:?diff=PageName] style links are fixed elsewhere.)
   $action = 'diff';
   $pagename = fix_magic_quotes_gpc($diff);
   unset($diff);
}
else
{
   $action = 'browse';
}


function IsSafeAction ($action)
{
   if (! ZIPDUMP_AUTH and $action == 'zip')
      return true;
   return in_array ( $action, array('browse',
				    'info', 'diff', 'search',
				    'edit', 'save',
				    'login', 'logout') );
}

function get_auth_mode ($action) 
{
   switch ($action) {
      case 'logout':
	 return  'LOGOUT';
      case 'login':
	 return 'LOGIN';
      default:
	 if (IsSafeAction($action))
	    return 'ANON_OK';
	 else
	    return 'REQUIRE_AUTH';
   }
}

$user = new WikiUser(get_auth_mode($action));

// All requests require the database
$dbi = OpenDataBase($WikiPageStore);

// if there is no FrontPage, create a basic set of Wiki pages
if ( ! IsWikiPage($dbi, gettext("FrontPage")) )
{
   include_once("lib/loadsave.php");
   SetupWiki($dbi);
   CloseDataBase($dbi);
   exit;
}

// FIXME: I think this is redundant.
if (!IsSafeAction($action))
   $user->must_be_admin($action);


switch ($action) {
   case 'edit':
      include "lib/editpage.php";
      break;

   case 'search':
      if (isset($searchtype) && ($searchtype == 'full')) {
	 include "lib/fullsearch.php";
      }
      else {
	 include "lib/search.php";
      }
      break;
      
   case 'save':
      include "lib/savepage.php";
      break;
   case 'info':
      include "lib/pageinfo.php";
      break;
   case 'diff':
      include "lib/diff.php";
      break;
      
   case 'zip':
      include_once("lib/loadsave.php");
      MakeWikiZip($dbi, isset($include) && $include == 'all');
      break;

   case 'upload':
      include_once("lib/loadsave.php");
      LoadPostFile($dbi, 'file');
      break;
   
   case 'dumpserial':
      if (empty($directory))
	 ExitWiki(gettext("You must specify a directory to dump to"));

      include_once("lib/loadsave.php");
      DumpToDir($dbi, fix_magic_quotes_gpc($directory));
      break;

   case 'loadfile':
      if (empty($source))
	 ExitWiki(gettext("You must specify a source to read from"));

      include_once("lib/loadsave.php");
      LoadFileOrDir($dbi, fix_magic_quotes_gpc($source));
      break;

   case 'remove':
      include 'admin/removepage.php';
      break;
    
   case 'lock':
   case 'unlock':
      include "admin/lockpage.php";
      include "lib/display.php";
      break;

   case 'browse':
   case 'login':
   case 'logout':
      include "lib/display.php"; // defaults to FrontPage
      break;

   default:
      echo QElement('p', sprintf("Bad action: '%s'", urlencode($action)));
      break;
}

CloseDataBase($dbi);
// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
