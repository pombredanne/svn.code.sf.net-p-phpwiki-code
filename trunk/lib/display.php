<!-- $Id: display.php,v 1.2 2000-10-08 18:12:14 wainstead Exp $ -->
<?php
   /*
      display.php: render a page. This has all the display 
      logic in it, except for the search boxes.
   */
 
   // if we got GET data, the first item is always a page name
   // if it wasn't this file would not have been included

   if ($argv[0]) {
      $pagename = rawurldecode($argv[0]);
   } else { 
      $pagename = "FrontPage"; 

      // if there is no FrontPage, create a basic set of Wiki pages
      if (! IsWikiPage($dbi, $pagename)) {
         include "lib/setupwiki.php";
      }
   }

   $html = "";
   $enc_name = rawurlencode($pagename);
   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);

   if (is_array($pagehash)) {
      // we render the page if it's a hash, else ask the user to write one.
      // This file returns a variable $html containing all the HTML markup
      include("lib/transform.php");
   } else {
      $html .= "Describe $pagename<a href='$ScriptUrl?edit=$enc_name'>?</a> here.\n";
   }

   GeneratePage('BROWSE', $html, $pagename, $pagehash);
   flush();

   IncreaseHitCount($dbi, $pagename);
?>
