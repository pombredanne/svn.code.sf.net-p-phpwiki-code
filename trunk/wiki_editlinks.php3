<!-- $Id: wiki_editlinks.php3,v 1.5 2000-06-18 15:12:13 ahollosi Exp $ -->
<?
   // Thanks to Alister <alister@minotaur.nu> for this code.
   // This allows an arbitrary number of reference links.

   $pagename = rawurldecode($links);
   if (get_magic_quotes_gpc()) {
      $pagename = stripslashes($pagename);
   }
   $pagehash = RetrievePage($dbi, $pagename);
   settype ($pagehash, 'array');

   GeneratePage('EDITLINKS', "", $pagename, $pagehash);
?>
