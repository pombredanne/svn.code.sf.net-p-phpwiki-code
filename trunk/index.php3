<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<!-- $Id: index.php3,v 1.8 2000-08-07 22:47:40 wainstead Exp $ -->
<?
   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */

   include "wiki_config.php3";
   include "wiki_stdlib.php3";

   // All requests require the database
   if ($copy) {
      // we are editing a copy and want the archive
      $dbi = OpenDataBase($ArchivePageStore);
      include "wiki_editpage.php3";
      CloseDataBase($dbi);
      exit();
   } else {
      // live database
      $dbi = OpenDataBase($WikiPageStore);
   }


   if ($edit) {
      $admin_edit = 0;
      include "wiki_editpage.php3";
   } elseif ($links) {
      include "wiki_editlinks.php3";
   } elseif ($search) {
      include "wiki_search.php3";
   } elseif ($full) {
      include "wiki_fullsearch.php3";
   } elseif ($post) {
      include "wiki_savepage.php3";
   } elseif ($info) {
      include "wiki_pageinfo.php3";
   } elseif ($diff) {
      include "wiki_diff.php3";
   } else {
      include "wiki_display.php3"; // defaults to FrontPage
   }

   CloseDataBase($dbi);

?>
