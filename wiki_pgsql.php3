<!-- $Id: wiki_pgsql.php3,v 1.4 2000-06-18 03:59:20 wainstead Exp $ -->
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
         if ($pagehash = pg_fetch_array($res, 0)) {
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
      $pagedata = addslashes(serialize($pagehash));

      if (!mysql_query("replace into $dbi[table] (page, hash) values ('$pagename', '$pagedata')", $dbi['dbc'])) {
            echo "error writing value";
            exit();
      }
   }



   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select count(*) from $dbi[table] where pagename='$pagename'";
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
