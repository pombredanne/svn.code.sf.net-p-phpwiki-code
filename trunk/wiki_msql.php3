<!-- $Id: wiki_msql.php3,v 1.1 2000-06-25 03:43:33 wainstead Exp $ -->
<?

   /*
      Database functions:
      MakePageHash($dbhash)
      MakeDBHash($pagename, $pagehash)
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
   // ignores MAX_DBM_ATTEMPTS

   function OpenDataBase($dbname) {
      global $msql_db;

      if (! ($dbc = msql_pconnect())) {
         echo "Cannot establish connection to database, giving up.";
         echo "Error message: ", msql_error(), "<br>\n";
         exit();
      }
      if (!msql_select_db($msql_db, $dbc)) {
         echo "Cannot open database, giving up.";
         echo "Error message: ", msql_error(), "<br>\n";
         exit();
      }

      $dbi['dbc'] = $dbc;
      $dbi['table'] = $dbname;
      return $dbi;
   }


   function CloseDataBase($dbi) {
      // NOP function
      // msql connections are established as persistant
      // they cannot be closed through msql_close()
   }


   function MakeDBHash($pagename, $pagehash)
   {
      $pagehash["pagename"] = addslashes($pagename);
      if (!isset($pagehash["flags"]))
         $pagehash["flags"] = 0;
      $pagehash["author"] = addslashes($pagehash["author"]);
      $pagehash["content"] = implode("\n", $pagehash["content"]);
      $pagehash["content"] = addslashes($pagehash["content"]);
      $pagehash["refs"] = serialize($pagehash["refs"]);
      $pagehash["searchterms"] = "foo bar bah bash"; 
      return $pagehash;
   }

   function MakePageHash($dbhash)
   {
      // unserialize/explode content
      $dbhash['refs'] = unserialize($dbhash['refs']);
      $dbhash['content'] = explode("\n", $dbhash['content']);
      return $dbhash;
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select * from $dbi[table] where pagename='$pagename'";
      if ($res = msql_query($query, $dbi['dbc'])) {
         if ($dbhash = msql_fetch_array($res)) {
            return MakePageHash($dbhash);
         }
      }
      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash)
   {
      $pagehash = MakeDBHash($pagename, $pagehash);


      // temporary hack until the time stuff is brought up to date
      $pagehash["created"] = time();
      $pagehash["lastmodified"] = time();

      if (IsWikiPage($dbi, $pagename)) {

         $PAIRS = "author='$pagehash[author]'," .
                  "content='$pagehash[content]'," .
                  "created=$pagehash[created]," .
                  "flags=$pagehash[flags]," .
                  "lastmodified=$pagehash[lastmodified]," .
                  "pagename='$pagehash[pagename]'," .
                  "refs='$pagehash[refs]'," .
                  "version=$pagehash[version]," .
                  "searchterms='$pagehash[searchterms]'";

         $query = "UPDATE $dbi[table] SET $PAIRS WHERE pagename='$pagename'";

      } else {
         // do an insert
         // build up the column names and values for the query

      $COLUMNS = "author, content, created, flags, lastmodified, " .
                 "pagename, refs, version, searchterms";

      $VALUES =  "'$pagehash[author]', '$pagehash[content]', " .
                 "$pagehash[created], $pagehash[flags], " .
                 "$pagehash[lastmodified], '$pagehash[pagename]', " .
                 "'$pagehash[refs]', $pagehash[version], " .
                 "'$pagehash[searchterms]'";


         $query = "INSERT INTO $dbi[table] ($COLUMNS) VALUES($VALUES)";
      }

//      echo "<p>Query: $query<p>\n";
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) 
         echo "Insert/update failed: " . msql_error();

   }


   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select pagename from $dbi[table] where pagename='$pagename'";
//      echo "Query: $query<br>\n";
      if ($res = msql_query($query, $dbi['dbc'])) {
         return(msql_affected_rows($res));
      }
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $search = addslashes($search);
      $res = msql_query("select pagename from $dbi[table] where pagename like '%$search%' order by pagename", $dbi["dbc"]);

      return $res;
   }


   // iterating through database
   function TitleSearchNextMatch($dbi, $res) {
      if($o = msql_fetch_object($res)) {
         return $o->pagename;
      }
      else {
         return 0;
      }
   }


   // setup for full-text search
   function InitFullSearch($dbi, $search) {
      $search = addslashes($search);
      $res = msql_query("select * from $dbi[table] where content like '%$search%'", $dbi["dbc"]);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      if($hash = msql_fetch_array($res)) {
         return MakePageHash($hash);
      }
      else {
         return 0;
      }
   }


?>
