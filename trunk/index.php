<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<!-- $Id: index.php,v 1.1 2000-10-08 17:48:37 wainstead Exp $ -->
<?php
   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */

   include "lib/config.php";
   include "lib/stdlib.php";

   // All requests require the database
   $dbi = OpenDataBase($WikiPageStore);



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
