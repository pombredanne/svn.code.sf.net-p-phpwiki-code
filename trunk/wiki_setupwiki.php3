<!-- $Id: wiki_setupwiki.php3,v 1.5 2000-06-14 03:43:38 wainstead Exp $ -->
<?

   /* Add the very first pages to a wiki */

   $page = array();
   $page["date"] = GetCurrentDate();
   $page["version"] = 1;


   $handle = opendir('./pgsrc');

   // load default pages
   while ($file = readdir($handle)) {
      if (strlen($file) < 4) { continue; }

      $content = implode("", file("pgsrc/$file"));
      $page["content"] = explode("\n", $content);

      InsertPage($dbi, $file, $page);
   }
   closedir($handle); 
    
?>
