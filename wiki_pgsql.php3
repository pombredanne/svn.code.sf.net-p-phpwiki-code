<!-- $Id: wiki_pgsql.php3,v 1.5 2000-06-20 01:25:16 wainstead Exp $ -->
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
      echo "<p>dbi after open: '$dbi' '$dbi[table]' '$dbi[dbc]'<p>\n";
      return $dbi;
   }


   function CloseDataBase($dbi) {
      // NOOP: we use persistent database connections
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select * from $dbi[table] where pagename='$pagename'";

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

            // don't forget to unserialize the references
            if ($pagehash['refs']) {
               $pagehash['refs'] = unserialize($pagehash['refs']);
            }
            return $pagehash;
         }
      }

      // if we reach this the query failed
      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {
      $pagename = addslashes($pagename);
      echo "<p>dbi in InsertPage: '$dbi' '$dbi[table]' '$dbi[dbc]'<p>";
      reset($pagehash);

      if (IsWikiPage($dbi, $pagename)) {
         // do an update
         list($key, $val) = each($pagehash);
         $PAIRS = "$key='" . addslashes($val) . "'";
         while (list($key, $val) = each($pagehash)) {
            $PAIRS .= ",$key='" . addslashes($val) . "'";
         }

         $query = "UPDATE $dbi[table] SET $PAIRS WHERE pagename='$pagename'";

      } else {
         // do an insert
         // build up the column names and values for the query
         list($key, $val) = each($pagehash);
         $COLUMNS = "$key";
         $VALUES  = "'" . addslashes($val) . "'";

         while (list($key, $val) = each($pagehash)) {
            $COLUMNS .= ",$key";
            $VALUES  .= ",'" . addslashes($val) . "'";
         }

         $query = "INSERT INTO $dbi[table] ($COLUMNS) VALUES($VALUES)";
      }

      echo "<p>Query: $query<p>\n";

//      if (!mysql_query("replace into $dbi[table] (page, hash) values ('$pagename', '$pagedata')", $dbi['dbc'])) {
//            echo "error writing value";
//            exit();
//      }

   }



   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select count(*) from $dbi[table] where pagename='$pagename'";
      echo "<p>IsWikiPage query: $query<p>\n";
      $res = pg_exec($query);
      //return(pg_numrows($res));
      $array = pg_fetch_array($res, 0);
      return $array[0];
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $search = addslashes($search);
      $res = mysql_query("select page from $dbi[table] where page like '%$search%' order by page", $dbi["dbc"]);

      return $res;
   }


   // iterating through database
   function TitleSearchNextMatch($dbi, $res) {
      if($o = mysql_fetch_object($res)) {
         return $o->page;
      }
      else {
         return 0;
      }
   }


   // setup for full-text search
   function InitFullSearch($dbi, $search) {
      $search = addslashes($search);
      $res = mysql_query("select page,hash from $dbi[table] where hash like '%$search%'", $dbi["dbc"]);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      if($o = mysql_fetch_object($res)) {
	 $page['name'] = $o->page;
	 $page['hash'] = unserialize($o->hash);
         return $page;
      }
      else {
         return 0;
      }
   }


?>
