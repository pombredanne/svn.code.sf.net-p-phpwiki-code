<!-- $Id: wiki_pgsql.php3,v 1.1 2000-06-12 04:13:57 wainstead Exp $ -->
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

////////////////
// remove after testing
///////////////
   $pg_database = "wiki";
   $pg_dbhost   = "localhost";
   $pg_dbport   = "5432";
////////////////
// remove after testing
///////////////


   // open a database and return a hash

   function OpenDataBase($table) {
      global $pg_database, $pg_dbhost, $pg_dbport;

      $connectstring = "host=$pg_dbhost port=$pg_dbport dbname=$pg_database";

      if (!($dbc = pg_connect($connectstring))) {
         echo "Cannot establish connection to database, giving up.";
         exit();
      }

      $dbi['dbc'] = $dbc;
      $dbi['table'] = $table;
      return $dbi;
   }


   function CloseDataBase($dbi) {
      return pg_close($dbi['dbc']);
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      if ($res = mysql_query("select hash from $dbi[table] where page='$pagename'", $dbi['dbc'])) {
         if ($o = mysql_fetch_object($res)) {
            // unserialize data into a hash
            $pagehash = unserialize($o->hash);
            return $pagehash;
         }
      }

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
      if ($res = mysql_query("select count(*) from $dbi[table] where page='$pagename'", $dbi['dbc'])) {
         return(mysql_result($res, 0));
      }
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
