<!-- $Id: wiki_editlinks.php3,v 1.5.2.1 2000-07-21 18:29:07 dairiki Exp $ -->
<?
   // Thanks to Alister <alister@minotaur.nu> for this code.
   // This allows an arbitrary number of reference links.

   $pagehash = RetrievePage($dbi, $pagename);
   settype ($pagehash, 'array');

   GeneratePage('EDITLINKS', "", $pagename, $pagehash);
?>
