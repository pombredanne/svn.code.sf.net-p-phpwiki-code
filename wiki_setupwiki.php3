<!-- $Id: wiki_setupwiki.php3,v 1.7 2000-06-19 20:21:31 ahollosi Exp $ -->
<?

   /* Add the very first pages to a wiki */

   $page = array();
   $page["version"] = 1;
   $page["flags"] = 0;
   $page["author"] = "The PhpWiki programming team";
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
