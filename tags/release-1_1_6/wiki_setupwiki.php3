<!-- $Id: wiki_setupwiki.php3,v 1.9 2000-06-21 22:57:17 ahollosi Exp $ -->
<?

   /* Add the very first pages to a wiki */

   $page = array();
   $page["author"] = "The PhpWiki programming team";
   $page["created"] = time();
   $page["flags"] = 0;
   $page["lastmodified"] = time();
   $page["refs"] = array();
   $page["version"] = 1;

   $handle = opendir('./pgsrc');

   // load default pages
   while ($file = readdir($handle)) {
      if (strlen($file) < 4) { continue; }

      $page["pagename"] = $file;

      $content = implode("", file("pgsrc/$file"));
      $page["content"] = explode("\n", $content);

      InsertPage($dbi, $file, $page);
   }
   closedir($handle); 
    
?>