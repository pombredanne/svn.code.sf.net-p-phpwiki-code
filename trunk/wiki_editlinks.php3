<!-- $Id: wiki_editlinks.php3,v 1.6 2000-08-15 02:59:20 wainstead Exp $ -->
<?
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
