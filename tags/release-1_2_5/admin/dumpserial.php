<!-- $Id: dumpserial.php,v 1.1.2.1.2.1 2005-01-07 13:48:42 rurban Exp $ -->

<?php
   /*
      Write out all pages from the database to a user-specified
      directory as serialized data structures.
   */
   if (!defined('WIKI_ADMIN'))
      die("You must be logged in as the administrator to dump serialized pages.");
  
   $directory = $dumpserial;
   $pages = GetAllWikiPagenames($dbi);

   // see if we can access the directory the user wants us to use
   if (! file_exists($directory)) {
      if (! mkdir($directory, 0755))
         ExitWiki("Cannot create directory '$directory'<br>\n");
      else
         $html = "Created directory '$directory' for the page dump...<br>\n";
   } else {
      $html = "Using directory '$directory'<br>\n";
   }

   $numpages = count($pages);
   for ($x = 0; $x < $numpages; $x++) {
      $pagename = htmlspecialchars($pages[$x]);
      $filename = preg_replace('/^\./', '%2e', rawurlencode($pages[$x]));
      $html .= "<br>$pagename ... ";
      if($pagename != $filename)
         $html .= "<small>saved as $filename</small> ... ";

      $data = serialize(RetrievePage($dbi, $pages[$x], $WikiPageStore));
      if ($fd = fopen("$directory/$filename", "w")) {
         $num = fwrite($fd, $data, strlen($data));
         $html .= "<small>$num bytes written</small>\n";
      } else {
         ExitWiki("<b>couldn't open file '$directory/$filename' for writing</b>\n");
      }
   }

   $html .= "<p><b>Dump complete.</b>";
   GeneratePage('MESSAGE', $html, 'Dump serialized pages', 0);
   ExitWiki('');
?>
