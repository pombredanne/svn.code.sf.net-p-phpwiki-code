<!-- $Id: wiki_display.php3,v 1.4 2000-06-09 10:25:12 ahollosi Exp $ -->
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

   $enc_name = rawurlencode($pagename);

   $html = WikiHeader($pagename);
   $html .= "<h1>$LogoImage ";
   $html .= "<a href=\"$ScriptUrl?full=$enc_name\">$pagename</a></h1>\n";

   $pagehash = RetrievePage($dbi, $pagename);
   if (is_array($pagehash)) {
      // we render the page if it's a hash, else ask the user to write one.
      // This file returns a variable $html containing all the HTML markup
      include("wiki_transform.php3");
   } else {
      $html .= "Describe $pagename<a href='$ScriptUrl?edit=$enc_name'>?</a> here.\n";
   }
   $html .= WikiToolBar();
   $html .= WikiFooter();

   echo $html;
?>
