<!-- $Id: editlinks.php,v 1.1.2.3 2005-01-07 14:23:04 rurban Exp $ -->
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