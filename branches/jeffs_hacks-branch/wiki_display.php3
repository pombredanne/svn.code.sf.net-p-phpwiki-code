<!-- $Id: wiki_display.php3,v 1.6.2.1 2000-07-21 18:29:07 dairiki Exp $ -->
<?
   /*
      display.php3: render a page. This has all the display 
      logic in it, except for the search boxes.
   */
 
   $html = "";
   $enc_name = rawurlencode($pagename);
   $pagehash = RetrievePage($dbi, $pagename);

   if (is_array($pagehash)) {
      // we render the page if it's a hash, else ask the user to write one.
      // This file returns a variable $html containing all the HTML markup
      include("wiki_transform.php3");
   } else {
      $html .= "Describe $pagename<a href='$ScriptUrl?edit=$enc_name'>?</a> here.\n";
   }

   GeneratePage('BROWSE', $html, $pagename, $pagehash);
   flush();

   IncreaseHitCount($dbi, $pagename);
?>
