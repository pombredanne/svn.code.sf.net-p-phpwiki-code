<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<!-- $Id: index.php,v 1.5.2.2 2005-01-07 13:59:57 rurban Exp $ -->
<?php
   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */

   if (!defined('WIKI_ADMIN')) { // index.php not included by admin.php?
      include "lib/config.php";
      include "lib/stdlib.php";

      // All requests require the database
      $dbi = OpenDataBase($WikiPageStore);
   }
   if (!ini_get('register_globals')) {
       import_request_variables('gps');
   }

   // Allow choice of submit buttons to determine type of search:
   if (isset($searchtype) && ($searchtype == 'full'))
      $full = $searchstring;
   elseif (isset($searchstring))     // default to title search
      $search = $searchstring;

   if (isset($edit)) {
      include "lib/editpage.php";
   } elseif (isset($links)) {
      include "lib/editlinks.php";
   } elseif (isset($copy)) {
      include "lib/editpage.php";
   } elseif (isset($search)) {
      include "lib/search.php";
   } elseif (isset($full)) {
      include "lib/fullsearch.php";
   } elseif (isset($refs)) {
      if (function_exists('InitBackLinkSearch')) {
	 include "lib/backlinks.php";
      }
      else {
	 $full = $refs;
	 include "lib/fullsearch.php";
      }
   } elseif (isset($post)) {
      include "lib/savepage.php";
   } elseif (isset($info)) {
      include "lib/pageinfo.php";
   } elseif (isset($diff)) {
      include "lib/diff.php";
   } else {
      include "lib/display.php"; // defaults to FrontPage
   }

   CloseDataBase($dbi);

?>