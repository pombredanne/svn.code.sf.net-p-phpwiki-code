<!-- $Id: wiki_display.php3,v 1.3 2000-06-05 21:46:50 wainstead Exp $ -->
<?
   /*
      display.php3: render a page. This has all the display 
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
         include "wiki_setupwiki.php3";
      }
   }

   // This file returns a variable $html containing all the HTML markup
   include("wiki_transform.php3");
   echo $html;

?>

