<!-- $Id: wiki_dbmlib.php3,v 1.6 2000-06-28 22:22:05 wainstead Exp $ -->
<?
   /*
      Database functions:
      OpenDataBase($dbname)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename)
      InsertPage($dbi, $pagename, $pagehash)
      IsWikiPage($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, &$pos)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, &$pos)
   */


   // open a database and return the handle
   // loop until we get a handle; php has its own
   // locking mechanism, thank god.
   // Suppress ugly error message with @.

   function OpenDataBase($dbname) {
      while (($dbi = @dbmopen($dbname, "c")) < 1) {
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


   function IsWikiPage($dbi, $pagename) {
      return dbmexists($dbi, $pagename);
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $pos['search'] = $search;
      $pos['key'] = dbmfirstkey($dbi);

      return $pos;
   }

   // iterating through database
   function TitleSearchNextMatch($dbi, &$pos) {
      while ($pos['key']) {
         $page = $pos['key'];
         $pos['key'] = dbmnextkey($dbi, $pos['key']);

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
         $pos['key'] = dbmnextkey($dbi, $pos['key']);

         $pagedata = dbmfetch($dbi, $key);
         // test the serialized data
         if (eregi($pos['search'], $pagedata)) {
	    $page['pagename'] = $key;
            $pagedata = unserialize($pagedata);
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
      $query = "update hitcount set hits=hits+1 where pagename='$pagename'";
      $res = mysql_query($query, $dbi['dbc']);

      if (!mysql_affected_rows($dbi['dbc'])) {
         $query = "insert into hitcount (pagename, hits) " .
                  "values ('$pagename', 1)";
	      $res = mysql_query($query, $dbi['dbc']);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename) {
      return;
      $query = "select hits from hitcount where pagename='$pagename'";
      $res = mysql_query($query, $dbi['dbc']);
      if (mysql_num_rows($res)) {
         $hits = mysql_result($res, 0);
      } else {
         $hits = "0";
      }

      return $hits;
   }



   function InitMostPopular($dbi, $limit) {
      return;
      $query = "select * from hitcount " .
               "order by hits desc, pagename limit $limit";

      $res = mysql_query($query);
      
      return $res;
   }

   function MostPopularNextMatch($dbi, $res) {
      return;
      if ($hits = mysql_fetch_array($res)) {
	 return $hits;
      } else {
         return 0;
      }
   }

?>
