<?php
$RCS_IDS = array('$Id: index.php,v 1.6 2001-02-10 22:15:07 dairiki Exp $');
function rcs_id($id)
{
   global $RCS_IDS;
   $RCS_IDS[] = $id;
}

include "lib/config.php";
include "lib/stdlib.php";
include "lib/userauth.php";


if (isset($pagename))
   $pagename = fix_magic_quotes_gpc($pagename);
else if (USE_PATH_INFO && !empty($PATH_INFO))
   $pagename = substr($PATH_INFO, 1);
else	 
   $pagename = gettext("FrontPage");

if (empty($action))
   $action = 'browse';
else
   fix_magic_quotes_gpc($action);

// Fix for compatibility with very old diff links in RecentChanges.
// (The [phpwiki:?diff=PageName] style links are fixed elsewhere.)
if (isset($diff))
{
   $action = 'diff';
   $pagename = fix_magic_quotes_gpc($diff);
   unset($diff);
}

function get_auth_mode ($action) 
{
   switch ($action) {

      case 'logout':
	 return  'LOGOUT';

      case 'login':
	 return 'REQUIRE_AUTH';

      case 'lock':
      case 'unlock':
      case 'remove':
      case 'dumpserial':
      case 'loadserial':
	 // Auto-login if user attempts one of these
	 return 'REQUIRE_AUTH';

      case 'zip':
	 // Auto-loing if necessary
	 return ZIPDUMP_AUTH ? 'REQUIRE_AUTH' : 'NORMAL';

      default:   
	 return 'NORMAL';
   }
}

$user = new WikiUser(get_auth_mode($action));

// All requests require the database
$dbi = OpenDataBase($WikiPageStore);

// if there is no FrontPage, create a basic set of Wiki pages
if ( ! IsWikiPage($dbi, gettext("FrontPage")) )
{
   include "lib/setupwiki.php";
}
  
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
      include "admin/zip.php";
      break;
      
   case 'dumpserial':
      include "admin/dumpserial.php";
      break;

   case 'loadserial':
      include "admin/loadserial.php";
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
   default:
      include "lib/display.php"; // defaults to FrontPage
      break;
}

CloseDataBase($dbi);
?>
