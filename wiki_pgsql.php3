<!-- $Id: wiki_pgsql.php3,v 1.15 2000-08-15 02:54:04 wainstead Exp $ -->
<?

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


   // open a database and return a hash

   function OpenDataBase($table) {
      global $WikiDataBase, $pg_dbhost, $pg_dbport;

      $connectstring = "host=$pg_dbhost port=$pg_dbport dbname=$WikiDataBase";

      if (!($dbc = pg_pconnect($connectstring))) {
         echo "Cannot establish connection to database, giving up.";
         exit();
      }

      $dbi['dbc'] = $dbc;
      $dbi['table'] = $table;
      // echo "<p>dbi after open: '$dbi' '$dbi[table]' '$dbi[dbc]'<p>\n";
      return $dbi;
   }


   function CloseDataBase($dbi) {
      // NOOP: we use persistent database connections
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename, $pagestore) {
      $pagename = addslashes($pagename);
      $query = "select * from $pagestore where pagename='$pagename'";
      // echo "<p>$query<p>";
      $res = pg_exec($dbi['dbc'], $query);

      if (pg_numrows($res)) {
         if ($array = pg_fetch_array($res, 0)) {
            while (list($key, $val) = each($array)) {
               // pg_fetch_array gives us all the values twice,
               // so we have to manually edit out the indices
               if (gettype($key) == "integer") {
                  continue;
               }
               $pagehash[$key] = $val;
            }

            // unserialize/explode content
            $pagehash['refs'] = unserialize($pagehash['refs']);
            $pagehash['content'] = explode("\n", $pagehash['content']);

            return $pagehash;
         }
      }

      // if we reach this the query failed
      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {
      $pagename = addslashes($pagename);
//      echo "<p>dbi in InsertPage: '$dbi' '$dbi[table]' '$dbi[dbc]'<p>";

      // prepare the content for storage
      if (!isset($pagehash["pagename"]))
         $pagehash["pagename"] = $pagename;
      if (!isset($pagehash["flags"]))
         $pagehash["flags"] = 0;
      $pagehash["author"] = addslashes($pagehash["author"]);
      $pagehash["content"] = implode("\n", $pagehash["content"]);
      $pagehash["content"] = addslashes($pagehash["content"]);
      $pagehash["pagename"] = addslashes($pagehash["pagename"]);
      $pagehash["refs"] = serialize($pagehash["refs"]);

      // record the time of modification
      $pagehash["lastmodified"] = time();

      if (IsWikiPage($dbi, $pagename)) {

         $PAIRS = "author='$pagehash[author]'," .
                  "content='$pagehash[content]'," .
                  "created=$pagehash[created]," .
                  "flags=$pagehash[flags]," .
                  "lastmodified=$pagehash[lastmodified]," .
                  "pagename='$pagehash[pagename]'," .
                  "refs='$pagehash[refs]'," .
                  "version=$pagehash[version]";

         $query = "UPDATE $dbi[table] SET $PAIRS WHERE pagename='$pagename'";

      } else {
         // do an insert
         // build up the column names and values for the query

         $COLUMNS = "author, content, created, flags, " .
                    "lastmodified, pagename, refs, version";

         $VALUES =  "'$pagehash[author]', '$pagehash[content]', " .
                    "$pagehash[created], $pagehash[flags], " .
                    "$pagehash[lastmodified], '$pagehash[pagename]', " .
                    "'$pagehash[refs]', $pagehash[version]";


         $query = "INSERT INTO $dbi[table] ($COLUMNS) VALUES($VALUES)";
      }

      // echo "<p>Query: $query<p>\n";
      $retval = pg_exec($dbi['dbc'], $query);
      if ($retval == false) 
         echo "Insert/update failed: " . pg_errormessage($dbi['dbc']);

   }


   function SaveCopyToArchive($dbi, $pagename, $pagehash) {
      global $ArchivePageStore;
      // echo "<p>save copy called<p>";

      $pagename = addslashes($pagename);
      // echo "<p>dbi in SaveCopyToArchive: '$dbi' '$ArchivePageStore' '$dbi[dbc]'<p>";

      // prepare the content for storage
      if (!isset($pagehash["pagename"]))
         $pagehash["pagename"] = $pagename;
      if (!isset($pagehash["flags"]))
         $pagehash["flags"] = 0;
      $pagehash["author"] = addslashes($pagehash["author"]);
      $pagehash["content"] = implode("\n", $pagehash["content"]);
      $pagehash["content"] = addslashes($pagehash["content"]);
      $pagehash["pagename"] = addslashes($pagehash["pagename"]);
      $pagehash["refs"] = serialize($pagehash["refs"]);

      if (IsInArchive($dbi, $pagename)) {

         $PAIRS = "author='$pagehash[author]'," .
                  "content='$pagehash[content]'," .
                  "created=$pagehash[created]," .
                  "flags=$pagehash[flags]," .
                  "lastmodified=$pagehash[lastmodified]," .
                  "pagename='$pagehash[pagename]'," .
                  "refs='$pagehash[refs]'," .
                  "version=$pagehash[version]";

         $query = "UPDATE $ArchivePageStore SET $PAIRS WHERE pagename='$pagename'";

      } else {
         // do an insert
         // build up the column names and values for the query

         $COLUMNS = "author, content, created, flags, " .
                    "lastmodified, pagename, refs, version";

         $VALUES =  "'$pagehash[author]', '$pagehash[content]', " .
                    "$pagehash[created], $pagehash[flags], " .
                    "$pagehash[lastmodified], '$pagehash[pagename]', " .
                    "'$pagehash[refs]', $pagehash[version]";


         $query = "INSERT INTO $ArchivePageStore ($COLUMNS) VALUES($VALUES)";
      }

      // echo "<p>Query: $query<p>\n";
      $retval = pg_exec($dbi['dbc'], $query);
      if ($retval == false) 
         echo "Insert/update failed: " . pg_errormessage($dbi['dbc']);


   }


   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select count(*) from wiki where pagename='$pagename'";
      $res = pg_exec($query);
      $array = pg_fetch_array($res, 0);
      return $array[0];
   }


   function IsInArchive($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select count(*) from archive where pagename='$pagename'";
      $res = pg_exec($query);
      $array = pg_fetch_array($res, 0);
      return $array[0];
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {

      global $search_counter;
      $search_counter = 0;

      $search = strtolower($search);
      $search = addslashes($search);
      $query = "select pagename from $dbi[table] where lower(pagename) " .
               "like '%$search%' order by pagename";
//      echo "search query: $query<br>\n";
      $res = pg_exec($dbi["dbc"], $query);

      return $res;
   }


   // iterating through database
   function TitleSearchNextMatch($dbi, $res) {
      global $search_counter;
      if($o = @pg_fetch_object($res, $search_counter)) {
         $search_counter++;
         return $o->pagename;
      } else {
         return 0;
      }
   }


   // setup for full-text search
   function InitFullSearch($dbi, $search) {
      global $search_counter;
      $search_counter = 0;
      $search = addslashes($search);
      $search = addslashes($search);
      $query = "select pagename,content from $dbi[table] " .
               "where lower(content) like '%$search%'";

      $res = pg_exec($dbi["dbc"], $query);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      global $search_counter;
      if ($hash = @pg_fetch_array($res, $search_counter)) {
         $search_counter++;
	 $page['pagename'] = $hash["pagename"];
	 $page['content'] = explode("\n", $hash["content"]);
         return $page;
      }
      else {
         return 0;
      }
   }


   ////////////////////////
   // new database features


   function IncreaseHitCount($dbi, $pagename) {

      $query = "update hitcount set hits=hits+1 where pagename='$pagename'";
      $res = pg_exec($dbi['dbc'], $query);

      if (!pg_cmdtuples($res)) {
         $query = "insert into hitcount (pagename, hits) " .
                  "values ('$pagename', 1)";
	 $res = pg_exec($dbi['dbc'], $query);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename) {

      $query = "select hits from hitcount where pagename='$pagename'";
      $res = pg_exec($dbi['dbc'], $query);
      if (pg_cmdtuples($res)) {
         $hits = pg_result($res, 0, "hits");
      } else {
         $hits = "0";
      }

      return $hits;
   }



   function InitMostPopular($dbi, $limit) {

      global $pg_most_pop_ctr;
      $pg_most_pop_ctr = 0;

      $query = "select * from hitcount " .
               "order by hits desc, pagename limit $limit";
      $res = pg_exec($dbi['dbc'], $query);
      return $res;
   }

   function MostPopularNextMatch($dbi, $res) {

      global $pg_most_pop_ctr;
      if ($hits = @pg_fetch_array($res, $pg_most_pop_ctr)) {
         $pg_most_pop_ctr++;
	 return $hits;
      } else {
         return 0;
      }
   }

   function GetAllWikiPageNames($dbi) {

      $res = pg_exec("select pagename from wiki");
      $rows = pg_numrows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $pages[$i] = mysql_result($res, $i, "pagename");
      }
      return $pages;
   }


?>
