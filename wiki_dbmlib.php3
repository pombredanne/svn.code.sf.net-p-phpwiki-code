<?
   /*
      Database functions:
      OpenDataBase($dbname)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename)
      InsertPage($dbi, $pagename, $pagehash)
      UpdateRecentChanges($dbi, $pagename) 
      IsWikiPage($dbi, $pagename)
      SaveCopyToArchive($pagename, $pagehash) 
      PageExists($dbi, $pagename)
   */


   // open a database and return the handle
   // loop until we get a handle; php has its own
   // locking mechanism, thank god. This prints
   // an ugly error message. Cannot prevent.

   function OpenDataBase($dbname) {
      while (($dbi = dbmopen($dbname, "c")) < 1) {
         if ($numattempts > MAX_DBM_ATTEMPTS) {
            echo "Cannot open database, giving up.";
            exit();
         }
         $numattempts++;
         sleep(1);
      }
      return $dbi;
   }


   function CloseDataBase($dbi) {
      return dbmclose($dbi);
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename) {
      if ($data = dbmfetch($dbi, $pagename)) {
         // unserialize $data into a hash
         $pagehash = unserialize($data);
         return $pagehash;
      } else {
         return -1;
      }
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {
      $pagedata = serialize($pagehash);

      if (dbminsert($dbi, $pagename, $pagedata)) {
         if (dbmreplace($dbi, $pagename, $pagedata)) {
            echo "error writing value";
            exit();
         }
      } 
   }

   // The Recent Changes file is solely handled here
   function UpdateRecentChanges($dbi, $pagename) {
      global $remoteuser;

      $recentchanges = RetrievePage($dbi, "RecentChanges");

      if ($recentchanges == -1) {
         $recentchanges = array(); // First-time user, eh? :-)
      }

      $recentchanges["text"] = preg_replace("/.*$pagename.*/",
                                            "",
                                            $recentchanges["text"]);

      $numlines = sizeof($recentchanges["text"]);
      $currentdate = GetCurrentDate();

      if ($recentchanges["date"] != $currentdate) {
         $recentchanges["text"][$numlines++] = "$currentdate";
         $recentchanges["text"][$numlines++] = "\n";
         $recentchanges["date"] = "$currentdate";
      }


      $recentchanges["text"][$numlines] = "\t*$pagename .....  $remoteuser";

      // Clear out blank lines (they are size zero, not even \n)
      $k = 0;
      for ($i = 0; $i < ($numlines + 1); $i++) {
         if (strlen($recentchanges["text"][$i]) != 0) {
            $newpage[$k++] = $recentchanges["text"][$i];
         }
      }
      $recentchanges["text"] = $newpage;

      InsertPage($dbi, "RecentChanges", $recentchanges);
   }


   function IsWikiPage($dbi, $pagename) {
      return dbmexists($dbi, $pagename);
   }


   // for archiving pages to a seperate dbm
   function SaveCopyToArchive($pagename, $pagehash) {
      global $ArchiveDataBase;
      $adbi = OpenDataBase($ArchiveDataBase);
      $newpagename = $pagename;
      InsertPage($adbi, $newpagename, $pagehash);
      dbmclose($adbi);
   }


?>
