<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<!-- $Id: index.php3,v 1.7.2.1 2000-07-21 18:29:07 dairiki Exp $ -->
<?
   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */

   require "wiki_config.php3";
   require "wiki_stdlib.php3";


   function ParseArgs () {
     global $pagename, $action, $PATH_INFO;
     
     if ($pagename || preg_match(':^/(.*)$:', $PATH_INFO, $match)) {
       // New style args PATH_INFO
       $pagename = $pagename ? strip_magic_quotes_gpc($pagename) : $match[1];
       return array($action ? $action : 'browse', $pagename);
     }
  
     // Convert old style args to $action and $pagename
     $actions = array('edit', 'copy', 'links', 'post', 'info', 'diff',
		      'backlinks', 'search', 'full');

     for(reset($actions); $action = current($actions); next($actions))
	 if ($pagename = $GLOBALS[$action])
	     return array($action, strip_magic_quotes_gpc($pagename));

     // No args.
     global $argv;
     $pagename = $argv[0] ? rawurldecode($argv[0]) : 'FrontPage';
     return array('browse', $pagename);
   }

   list ($action, $pagename) = ParseArgs();


   // All requests require the database
   if ($action == 'copy')
      $dbi = OpenDataBase($ArchiveDataBase);
   else {
      $dbi = OpenDataBase($WikiDataBase);
      // if there is no FrontPage, create a basic set of Wiki pages
      if (! IsWikiPage($dbi, 'FrontPage')) {
	 include "wiki_setupwiki.php3";
      }
   }
              
   switch ($action) {
   case 'browse':
      include "wiki_display.php3";
      break;
   case 'copy':
   case 'edit':
      $admin_edit = 0;
      include "wiki_editpage.php3";
      break;
   case 'links':
      include "wiki_editlinks.php3";
      break;
   case 'search':
      $search_term = $pagename;
      include "wiki_search.php3";
      break;
   case 'full':
      $search_term = $pagename;
      include "wiki_fullsearch.php3";
      break;
   case 'post':
      include "wiki_savepage.php3";
      break;
   case 'info':
      include "wiki_pageinfo.php3";
      break;
   case 'diff':
      include "wiki_diff.php3";
      break;
   case 'backlinks':
      $search_term = $pagename;
      include "wiki_fullsearch.php3";
      break;
   default:
      die(sprintf("Bad action (%s)", htmlspecialchars($action)));
      break;
   }

   CloseDataBase($dbi);
?>
