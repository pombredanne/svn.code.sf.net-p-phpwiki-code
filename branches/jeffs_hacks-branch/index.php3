<?
if (!defined('WIKI_ADMIN'))
{
  define('WIKI_ADMIN', 'no');
  require('wiki_config.php3');
}

rcs_id('$Id: index.php3,v 1.7.2.2 2000-07-29 00:36:45 dairiki Exp $');

   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */


// FIXME: move this
function wiki_lock ($action, $pagename)
{
  global $dbi;
  
  if (WIKI_ADMIN != 'yes')
      return wiki_message('ERROR', 'LockForAdminOnly');
  if (!($page = $dbi->retrievePage($pagename)))
      return wiki_message('ERROR', 'CantLockNonexistingPage');
      
  $flags = $page->flags();
  if ($action == 'unlock')
      $flags &= ~FLAG_PAGE_LOCKED;
  else
      $flags |= FLAG_PAGE_LOCKED;
  $dbi->setFlags($pagename, $flags);

  return true;
}

// FIXME: debug only
function utime() {
  $mtime = microtime();
  $mtime = explode(" ",$mtime);
  $mtime = $mtime[1] + $mtime[0];
  return $mtime;
}

   require "wiki_stdlib.php3";
   require "wiki_dblib.php3";

   function ParseArgs () {
     global $pagename, $action, $PATH_INFO;
     
     if ($pagename || preg_match(':^/(.*)$:', $PATH_INFO, $match) || $action) {
       // New style args PATH_INFO
       $pagename = $pagename ? strip_magic_quotes_gpc($pagename) : $match[1];
       return array($action ? $action : 'browse',
                    $pagename ? $pagename : 'FrontPage');
     }
  
     // Convert old style args to $action and $pagename
     $actions = array('edit', 'copy', 'links', 'post', 'info', 'diff',
		      'backlinks', 'search', 'full', 'history');

     for(reset($actions); $action = current($actions); next($actions))
	 if ($pagename = $GLOBALS[$action])
	     return array($action, strip_magic_quotes_gpc($pagename));

     // No args.
     global $argv;
     $pagename = $argv[0] ? rawurldecode($argv[0]) : 'FrontPage';
     return array('browse', $pagename);
   }

   list ($action, $pagename) = ParseArgs();
   SetToken('Page', new PageTokens($pagename, $version));

   if (WIKI_PAGENAME_IN_PATHINFO)
      SafeSetToken('BaseUrl', "$ScriptUrl/" . rawurlencode($pagename));
   else
      SafeSetToken('BaseUrl', $ScriptUrl);

   SafeSetToken($TemplateVars);

   $dbi = OpenDatabase($dbparams);
   // if there is no FrontPage, create a basic set of Wiki pages
   if ( ! $dbi->isWikiPage('FrontPage') ) {
     include("wiki_setupwiki.php3");
   }
  
   switch ($action) {
   case 'unlock':
   case 'lock':
      if (wiki_lock($action, $pagename)) {
	 include("wiki_display.php3");
      }
      break;
   case 'browse':
      include "wiki_display.php3";
      break;
   case 'history':
   case 'info':
      include "wiki_history.php3";
      break;
   case 'edit':
   case 'links':
      include "wiki_editpage.php3";
      break;
   case 'search':
   case 'full':
   case 'backlinks':
      $search_term = $pagename;
      include "wiki_search.php3";
      break;
   case 'post':
      include "wiki_savepage.php3";
      break;
   case 'diff':
      include "wiki_diff.php3";
      break;
   case 'zip':
      include("admin/wiki_zip.php3"); // FIXME:
      break;
   default:
      die(sprintf("Bad action (%s)", htmlspecialchars($action)));
      break;
   }

   SafeSetToken('PhpWikiVersion', $RcsIdentifiers);
   SafeSetToken('PlainTitle', strip_tags(GetToken('Title')));
   SetToken('DebugInfo', $DebugInfo);

   print Template('WRAPPER');

   $dbi->close();
?>
