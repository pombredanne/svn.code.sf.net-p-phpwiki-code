<!-- $Id: wiki_display.php3,v 1.6.2.2 2000-07-22 22:25:29 dairiki Exp $ -->
<?
   /*
      display.php3: render a page. This has all the display 
      logic in it, except for the search boxes.
   */
 
   $html = "";
   $pagehash = RetrievePage($dbi, $pagename);

   if (is_array($pagehash)) {
      // we render the page if it's a hash, else ask the user to write one.
      // This file returns a variable $html containing all the HTML markup
      include("wiki_transform.php3");
   } else {
      $html .= sprintf("Describe %s here.\n", LinkUnknownWikiWord($pagename));
   }

   GeneratePage('BROWSE', $html, $pagename, $pagehash);
   flush();

   IncreaseHitCount($dbi, $pagename);
?>
