<?php  rcs_id('$Id: wiki_dbmlib.php3,v 1.12 2000-08-29 02:37:42 aredridel Exp $');
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
      global $WikiDB; // hash of all the DBM file names

      ksort($WikiDB);
      reset($WikiDB);

      while (list($key, $file) = each($WikiDB)) {
         while (($dbi[$key] = @dbmopen($file, "c")) < 1) {
            if ($numattempts > MAX_DBM_ATTEMPTS) {
               echo "Cannot open database '$key' : '$file', giving up.";
               exit();
            }
            $numattempts++;
            sleep(1);
         }
      }

      return $dbi;
   }


   function CloseDataBase($dbi) {

      ksort($dbi);
      reset($dbi);
      while (list($dbmfile, $dbihandle) = each($dbi)) {
         dbmclose($dbi[$dbihandle]);
      }
      return;
   }


   // take a serialized hash, return same padded out to
   // the next largest number bytes divisible by 500. This
   // is to save disk space in the long run, since DBM files
   // leak memory.
   function PadSerializedData($data) {
      // calculate the next largest number divisible by 500
      $nextincr = 500 * ceil(strlen($data) / 500);
      // pad with spaces
      $data = sprintf("%-${nextincr}s", $data);
      return $data;
   }

   // strip trailing whitespace from the serialized data 
   // structure.
   function UnPadSerializedData($data) {
      return chop($data);
   }



   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename, $pagestore) {
      if ($data = dbmfetch($dbi[$pagestore], $pagename)) {
         // unserialize $data into a hash
         $pagehash = unserialize(UnPadSerializedData($data));
         return $pagehash;
      } else {
         return -1;
      }
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {
      $pagedata = PadSerializedData(serialize($pagehash));

      if (dbminsert($dbi['wiki'], $pagename, $pagedata)) {
         if (dbmreplace($dbi['wiki'], $pagename, $pagedata)) {
            echo "error writing value";
            exit();
         }
      } 
   }


   // for archiving pages to a seperate dbm
   function SaveCopyToArchive($dbi, $pagename, $pagehash) {
      global $ArchivePageStore;

      $pagedata = PadSerializedData(serialize($pagehash));

      if (dbminsert($dbi[$ArchivePageStore], $pagename, $pagedata)) {
         if (dbmreplace($dbi['archive'], $pagename, $pagedata)) {
            echo "error writing value";
            exit();
         }
      } 
   }


   function IsWikiPage($dbi, $pagename) {
      return dbmexists($dbi['wiki'], $pagename);
   }


   function IsInArchive($dbi, $pagename) {
      return dbmexists($dbi['archive'], $pagename);
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $pos['search'] = $search;
      $pos['key'] = dbmfirstkey($dbi['wiki']);

      return $pos;
   }

   // iterating through database
   function TitleSearchNextMatch($dbi, &$pos) {
      while ($pos['key']) {
         $page = $pos['key'];
         $pos['key'] = dbmnextkey($dbi['wiki'], $pos['key']);

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
      while ($pos['key']) {
         $key = $pos['key'];
         $pos['key'] = dbmnextkey($dbi['wiki'], $pos['key']);

         $pagedata = dbmfetch($dbi['wiki'], $key);
         // test the serialized data
         if (eregi($pos['search'], $pagedata)) {
	    $page['pagename'] = $key;
            $pagedata = unserialize(UnPadSerializedData($pagedata));
	    $page['content'] = $pagedata['content'];
	    return $page;
	 }
      }
      return 0;
   }

   ////////////////////////
   // new database features


   function IncreaseHitCount($dbi, $pagename) {
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
      $ctr = 0;

      $namelist[$ctr] = $key = dbmfirstkey($dbi);
      while ($key = dbmnextkey($dbi, $key)) {
         $ctr++;
         $namelist[$ctr] = $key;
      }

      return $namelist;
   }

?>
