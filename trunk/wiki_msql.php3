<!-- $Id: wiki_msql.php3,v 1.3 2000-06-25 19:38:55 wainstead Exp $ -->
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

   function OpenDataBase($dbinfo) {
      global $msql_db;

      if (! ($dbc = msql_pconnect())) {
         echo "Cannot establish connection to database, giving up.";
         echo "Error message: ", msql_error(), "<br>\n";
         exit();
      }
      if (!msql_select_db($msql_db, $dbc)) {
         echo "Cannot open database $msql_db, giving up.";
         echo "Error message: ", msql_error(), "<br>\n";
         exit();
      }

      $dbi['dbc'] = $dbc;
      $dbi['table'] = $dbinfo['table'];           // page metadata
      $dbi['page_table'] = $dbinfo['page_table']; // page content
//      echo "dbi[page_table]: $dbi[page_table], dbinfo[page_table]: $dbinfo[page_table]<br>\n";
      return $dbi;
   }


   function CloseDataBase($dbi) {
      // NOP function
      // msql connections are established as persistant
      // they cannot be closed through msql_close()
   }


   // Take form data and prepare it for the db
   function MakeDBHash($pagename, $pagehash)
   {
      $pagehash["pagename"] = addslashes($pagename);
      if (!isset($pagehash["flags"]))
         $pagehash["flags"] = 0;
      $pagehash["author"] = addslashes($pagehash["author"]);
      $pagehash["refs"] = serialize($pagehash["refs"]);

      return $pagehash;
   }


   // Take db data and prepare it for display
   function MakePageHash($dbhash)
   {
      // unserialize/explode content
      $dbhash['refs'] = unserialize($dbhash['refs']);

      // retrieve page lines from other table
//      $dbhash['content'] = explode("\n", $dbhash['content']);
      return $dbhash;
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select * from $dbi[table] where pagename='$pagename'";
      if ($res = msql_query($query, $dbi['dbc'])) {
         $dbhash = msql_fetch_array($res);
         $query = "select lineno,line from $dbi[page_table] " .
                  "where pagename='$pagename' " .
                  "order by lineno";
         if ($res = msql_query($query, $dbi[dbc])) {
            $dbhash["content"] = array();
            while ($row = msql_fetch_array($res)) {
               $dbhash["content"][ $row["lineno"] ] = $row["line"];
            }
         }

         return MakePageHash($dbhash);
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
                  "created=$pagehash[created]," .
                  "flags=$pagehash[flags]," .
                  "lastmodified=$pagehash[lastmodified]," .
                  "pagename='$pagehash[pagename]'," .
                  "refs='$pagehash[refs]'," .
                  "version=$pagehash[version]";

         $query  = "UPDATE $dbi[table] SET $PAIRS WHERE pagename='$pagename'";

      } else {
         // do an insert
         // build up the column names and values for the query

         $COLUMNS = "author, created, flags, lastmodified, " .
                    "pagename, refs, version";

         $VALUES =  "'$pagehash[author]', " .
                    "$pagehash[created], $pagehash[flags], " .
                    "$pagehash[lastmodified], '$pagehash[pagename]', " .
                    "'$pagehash[refs]', $pagehash[version]";


         $query = "INSERT INTO $dbi[table] ($COLUMNS) VALUES($VALUES)";
      }

//      echo "<p>Query: $query<p>\n";

      // first, insert the metadata
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) 
         echo "Insert/update failed: ", msql_error(), "<br>\n";

      // second, insert the page data
      // remove old data from page_table
      $query = "delete from $dbi[page_table] where pagename='$pagename'";
      //echo "Delete query: $query<br>\n";
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) 
         echo "Delete on $dbi[page_table] failed: ", msql_error(), "<br>\n";

      // insert the new lines
      reset($pagehash["content"]);

      for ($x = 0; $x < count($pagehash["content"]); $x++) {
         $line = addslashes($pagehash["content"][$x]);
         $query = "INSERT INTO $dbi[page_table] " .
                  "(pagename, lineno, line) " .
                  "VALUES('$pagename', $x, '$line')";
         //echo "Page line insert query: $query<br>\n";
         $retval = msql_query($query, $dbi['dbc']);
         if ($retval == false) 
            echo "Insert into $dbi[page_table] failed: ", msql_error(), "<br>\n";;
         
      }

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
      $query = "select pagename from $dbi[table] " .
               "where pagename clike '%$search%' order by pagename";
      $res = msql_query($query, $dbi["dbc"]);

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
      $query = "select * from $dbi[table] where searchterms clike '%$search%'";
      $res = msql_query($query, $dbi["dbc"]);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      if($hash = msql_fetch_array($res)) {
         return MakePageHash($hash);
      } else {
         return 0;
      }
   }


?>
