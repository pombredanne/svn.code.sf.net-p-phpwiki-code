<!-- $Id: wiki_setupwiki.php3,v 1.6 2000-06-14 04:09:20 wainstead Exp $ -->
<?

   /* Add the very first pages to a wiki */

   $page = array();
   $page["version"] = 1;
   $page["flags"] = 0;
   $page["author"] = "The PhpWiki prgramming team";
   $page["lastmodified"] = GetCurrentDate();
   $page["created"] = GetCurrentDate();

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
