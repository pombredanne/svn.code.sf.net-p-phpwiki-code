<!-- $Id: wiki_setupwiki.php3,v 1.4 2000-06-09 03:10:19 wainstead Exp $ -->
<?

   /* Add the very first pages to a wiki */

   $page = array();
   $page["date"] = GetCurrentDate();
   $page["version"] = 1;


   $handle = opendir('./pgsrc');

   // load default pages
   while ($file = readdir($handle)) {
      if (strlen($file) < 4) { continue; }

      $text = implode("", file("pgsrc/$file"));
      $page["text"] = explode("\n", $text);

      InsertPage($dbi, $file, $page);
   }
   closedir($handle); 
    
?>
