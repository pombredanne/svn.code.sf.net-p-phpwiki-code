<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<!-- $Id: index.php,v 1.2 2000-10-11 13:57:47 ahollosi Exp $ -->
<?php
   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */

   include "lib/config.php";
   include "lib/stdlib.php";

   // All requests require the database
   $dbi = OpenDataBase($WikiPageStore);


   // Allow choice of submit buttons to determine type of search:
   if ($searchtype == 'full')
      $full = $searchstring;
   elseif ($searchstring)       // default to title search
      $search = $searchstring;

   if ($edit) {
      $admin_edit = 0;
      include "lib/editpage.php";
   } elseif ($links) {
      include "lib/editlinks.php";
   } elseif ($copy) {
      include "lib/editpage.php";
   } elseif ($search) {
      include "lib/search.php";
   } elseif ($full) {
      include "lib/fullsearch.php";
   } elseif ($post) {
      include "lib/savepage.php";
   } elseif ($info) {
      include "lib/pageinfo.php";
   } elseif ($diff) {
      include "lib/diff.php";
   } else {
      include "lib/display.php"; // defaults to FrontPage
   }

   CloseDataBase($dbi);

?>
