<?

   /* Add the very first pages to a wiki */

   $page = array();
   $page["date"] = GetCurrentDate();


   $handle = opendir('./pgsrc');

   // load default pages
   while ($file = readdir($handle)) {
      if (strlen($file) < 4) { continue; }

      $page["text"] = file("pgsrc/$file");
/*
      for ($x = 0; $page["text"][$x]; $x++) {
         if (strlen($page["text"][$x]) > 1) {
            $page["text"][$x] = chop($page["text"][$x]);
         }
      }
      reset($page["text"]);
*/
      InsertPage($dbi, $file, $page);
   }
   closedir($handle); 
    
?>
