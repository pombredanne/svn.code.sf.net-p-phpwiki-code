<!-- $Id: editlinks.inc,v 1.2 2000-10-07 17:47:19 wainstead Exp $ -->
<?php
   // Thanks to Alister <alister@minotaur.nu> for this code.
   // This allows an arbitrary number of reference links.

   $pagename = rawurldecode($links);
   if (get_magic_quotes_gpc()) {
      $pagename = stripslashes($pagename);
   }
   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);
   settype ($pagehash, 'array');

   GeneratePage('EDITLINKS', "", $pagename, $pagehash);
?>
