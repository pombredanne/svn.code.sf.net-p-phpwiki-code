<?php
   // display.php: fetch page or get default content
   // calls transform.php for actual transformation of wiki markup to HTML
   rcs_id('$Id: display.php,v 1.5 2000-12-30 21:09:13 ahollosi Exp $');
 
   // if we got GET data, the first item is always a page name
   // if it wasn't this file would not have been included

   if (!empty($argv[0])) {
      $pagename = rawurldecode($argv[0]);
   } else { 
      $pagename = gettext("FrontPage");

      // if there is no FrontPage, create a basic set of Wiki pages
      if (! IsWikiPage($dbi, $pagename)) {
         include "lib/setupwiki.php";
      }
   }

   $html = "";
   $enc_name = rawurlencode($pagename);
   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);

   // we render the page if it exists, else ask the user to write one.
   if (is_array($pagehash)) {
      // transform.php returns $html containing all the HTML markup
      include("lib/transform.php");
   } else {
      $html .= sprintf(gettext("Describe %s here."),
		       "$pagename<a href='$ScriptUrl?edit=$enc_name'>?</a>");
   }

   GeneratePage('BROWSE', $html, $pagename, $pagehash);
   flush();

   IncreaseHitCount($dbi, $pagename);
?>
