<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<!-- $Id: index.php,v 1.4 2000-11-01 11:31:40 ahollosi Exp $ -->
<?php
   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */

   set_magic_quotes_runtime(0);
   error_reporting(E_ALL ^ E_NOTICE);   // remove E_NOTICE for debugging

   include "lib/config.php";
   include "lib/stdlib.php";

   // All requests require the database
   $dbi = OpenDataBase($WikiPageStore);


   // Allow choice of submit buttons to determine type of search:
   if (isset($searchtype) && ($searchtype == 'full'))
      $full = $searchstring;
   elseif (isset($searchstring))     // default to title search
      $search = $searchstring;

   if (isset($edit)) {
      $admin_edit = 0;
      include "lib/editpage.php";
   } elseif (isset($links)) {
      include "lib/editlinks.php";
   } elseif (isset($copy)) {
      include "lib/editpage.php";
   } elseif (isset($search)) {
      include "lib/search.php";
   } elseif (isset($full)) {
      include "lib/fullsearch.php";
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
