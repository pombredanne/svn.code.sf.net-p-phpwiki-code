<?php  rcs_id('$Id: db_filesystem.php,v 1.1 2000-10-08 17:33:26 wainstead Exp $');
   /*
      Database functions:

      OpenDataBase($table)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash)
      SaveCopyToArchive($dbi, $pagename, $pagehash) 
      IsWikiPage($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, $res)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, $res)
      IncreaseHitCount($dbi, $pagename)
      GetHitCount($dbi, $pagename)
      InitMostPopular($dbi, $limit)
      MostPopularNextMatch($dbi, $res)
   */


   // open a database and return the handle
   // loop until we get a handle; php has its own
   // locking mechanism, thank god.
   // Suppress ugly error message with @.

   function OpenDataBase($dbname) {
      global $WikiDB;

      ksort($WikiDB);
      reset($WikiDB);

      return $WikiDB;
   }


   function CloseDataBase($dbi) {
      return;
   }

   
   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename, $pagestore) {
      $filename = $dbi[$pagestore] . "/" . $pagename;
      if ($fd = @fopen($filename, "r")) {
         $locked = flock($fd, 1); # Read lock
         if (!$locked) { 
            print "Error: timeout obtaining lock. Please try again"; exit; 
         }
         if ($data = file($filename)) {
            // unserialize $data into a hash
            $pagehash = unserialize(join("\n", $data));
		 }	
		 fclose($fd);
		 if($data) {
		    return $pagehash;
		 }
      } else {
         return -1;
      }
   }


   // Either insert or replace a key/value (a page)
   function Filesystem_WritePage($dbi, $pagename, $pagehash) {
      global $WikiPageStore;
      $pagedata = serialize($pagehash);

      if (!is_dir($dbi)) {
	     $d = split("/", $dbi);
		 $dt = "";
		 while(list($key, $val) = each($d)) {
			$dt = $dt.$val."/";
		    @mkdir($dt, 0755);
		 }
	  }

      $filename = $dbi . "/" . $pagename;
      if($fd = fopen($filename, 'a')) { 
         $locked = flock($fd,2); #Exclusive blocking lock 
         if (!$locked) { 
            print "Error: timeout obtaining lock. Please try again"; exit; 
         } 

         #Second (actually used) filehandle 
         $fdsafe = fopen($filename, 'w'); 
         fwrite($fdsafe, $pagedata); 
         fclose($fdsafe); 
         fclose($fd);
      } else {
         echo "error writing value";
         exit();
	  }
   }

   function InsertPage($dbi, $pagename, $pagehash) {
      return Filesystem_WritePage($dbi['wiki'], $pagename, $pagehash);
   }

   // for archiving pages to a seperate dbm
   function SaveCopyToArchive($dbi, $pagename, $pagehash) {
      global $ArchivePageStore;
      return Filesystem_WritePage($dbi[$ArchivePageStore], $pagename, $pagehash);
   }


   function IsWikiPage($dbi, $pagename) {
      return is_file($dbi['wiki'] . "/" . $pagename);
   }


   function IsInArchive($dbi, $pagename) {
      return is_file($dbi['archive'] . "/" . $pagename);
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) { 
      $pos['search'] = $search;
      $pos['data'] = GetAllWikiPageNames($dbi['wiki']);

      return $pos;
   }

   // iterating through database
   function TitleSearchNextMatch($dbi, &$pos) { 
      while (list($key, $page) = each($pos['data'])) {
         if (eregi($pos['search'], $page)) {
            return $page;
         }
      }
      return 0;
   }

   // setup for full-text search
   function InitFullSearch($dbi, $search) { 
      return InitTitleSearch($dbi, $search);
   }

   //iterating through database
   function FullSearchNextMatch($dbi, &$pos) { 
      global $WikiPageStore;
      while (list($key, $page) = each($pos['data'])) {
         $pagedata = RetrievePage($dbi, $page, $WikiPageStore);
         if (eregi($pos['search'], serialize($pagedata))) {
	        return $pagedata;
		 }
	  }
      return 0;
   }

   ////////////////////////
   // new database features

   function IncreaseHitCount($dbi, $pagename) {
      return;
return;
      // kluge: we ignore the $dbi for hit counting
      global $WikiDB;

      $hcdb = OpenDataBase($WikiDB['hitcount']);

      if (dbmexists($hcdb['active'], $pagename)) {
         // increase the hit count
         $count = dbmfetch($hcdb['active'], $pagename);
         $count++;
         dbmreplace($hcdb['active'], $pagename, $count);
      } else {
         // add it, set the hit count to one
         $count = 1;
         dbminsert($hcdb['active'], $pagename, $count);
      }

      CloseDataBase($hcdb);
   }

   function GetHitCount($dbi, $pagename) {
      return;
      // kluge: we ignore the $dbi for hit counting
      global $WikiDB;

      $hcdb = OpenDataBase($WikiDB['hitcount']);
      if (dbmexists($hcdb['active'], $pagename)) {
         // increase the hit count
         $count = dbmfetch($hcdb['active'], $pagename);
         return $count;
      } else {
         return 0;
      }

      CloseDataBase($hcdb);
   }


   function InitMostPopular($dbi, $limit) {
     return;
      $pagename = dbmfirstkey($dbi['hitcount']);
      $res[$pagename] = dbmfetch($dbi['hitcount'], $pagename);
      while ($pagename = dbmnextkey($dbi['hitcount'], $pagename)) {
         $res[$pagename] = dbmfetch($dbi['hitcount'], $pagename);
         echo "got $pagename with value " . $res[$pagename] . "<br>\n";
      }

      rsort($res);
      reset($res);
      return($res);
   }

   function MostPopularNextMatch($dbi, $res) {
      return;
      // the return result is a two element array with 'hits'
      // and 'pagename' as the keys

      if (list($index1, $index2, $pagename, $hits) = each($res)) {
         echo "most popular next match called<br>\n";
         echo "got $pagename, $hits back<br>\n";
         $nextpage = array(
            "hits" => $hits,
            "pagename" => $pagename
         );
         return $nextpage;
      } else {
         return 0;
      }
   } 

   function GetAllWikiPagenames($dbi) {
      $namelist = array();
	  $d = opendir($dbi);
	  $curr = 0;
	  while($entry = readdir($d)) {
         $namelist[$curr++] = $entry;
	  }

      return $namelist;
   }
?>
