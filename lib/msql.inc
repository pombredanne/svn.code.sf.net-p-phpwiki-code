<!-- $Id: msql.inc,v 1.1 2000-09-18 04:36:04 wainstead Exp $ -->
<?php

   /*
      Database functions:
      MakePageHash($dbhash)
      MakeDBHash($pagename, $pagehash)
      OpenDataBase($dbname)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash)xc
      SaveCopyToArchive($dbi, $pagename, $pagehash) 
      IsWikiPage($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, &$pos)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, &$pos)
      GetAllWikiPageNames($dbi)
   */


   // open a database and return the handle
   // ignores MAX_DBM_ATTEMPTS

   function OpenDataBase($dbinfo) {
      global $msql_db;

      if (! ($dbc = msql_connect())) {
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
      return $dbi;
   }


   function CloseDataBase($dbi) {
      // I found msql_pconnect unstable so we go the slow route.
      return msql_close($dbi['dbc']);
   }


   // This should receive the full text of the page in one string
   // It will break the page text into an array of strings
   // of length MSQL_MAX_LINE_LENGTH which should match the length
   // of the columns wikipages.LINE, archivepages.LINE in schema.minisql

   function msqlDecomposeString($string) {
      $ret_arr = array();
      $el = 0;
   
      // zero, one, infinity
      // account for the small case
      if (strlen($string) < MSQL_MAX_LINE_LENGTH) { 
         $ret_arr[$el] = $string;
         return $ret_arr;
      }
   
      $words = array();
      $line = $string2 = "";
   
      // split on single spaces
      $words = preg_split("/ /", $string);
      $num_words = count($words);
   
      reset($words);
      $ret_arr[0] = $words[0];
      $line = " $words[1]";
   
      // for all words, build up lines < MSQL_MAX_LINE_LENGTH in $ret_arr
      for ($x = 2; $x < $num_words; $x++) {
         $length = strlen($line) + strlen($words[$x]) 
                   + strlen($ret_arr[$el]) + 1;

         if ($length < MSQL_MAX_LINE_LENGTH) {
            $line .= " " .  $words[$x];
         } else {
            // put this line in the return array, reset, continue
            $ret_arr[$el++] .= $line;
            $line = " $words[$x]"; // reset 	
         }
      }
      $ret_arr[$el] = $line;
      return $ret_arr;
   }


   // Take form data and prepare it for the db
   function MakeDBHash($pagename, $pagehash)
   {
      $pagehash["pagename"] = addslashes($pagename);
      if (!isset($pagehash["flags"]))
         $pagehash["flags"] = 0;
      if (!isset($pagehash["content"])) {
         $pagehash["content"] = array();
      } else {
         $pagehash["content"] = implode("\n", $pagehash["content"]);
         $pagehash["content"] = msqlDecomposeString($pagehash["content"]);
      }
      $pagehash["author"] = addslashes($pagehash["author"]);
      $pagehash["refs"] = serialize($pagehash["refs"]);

      return $pagehash;
   }


   // Take db data and prepare it for display
   function MakePageHash($dbhash)
   {
      // unserialize/explode content
      $dbhash['refs'] = unserialize($dbhash['refs']);
      return $dbhash;
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename, $pagestore) {
      $pagename = addslashes($pagename);
      $table = $pagestore['table'];
      $pagetable = $pagestore['page_table'];

      $query = "select * from $table where pagename='$pagename'";
      // echo "<p>query: $query<p>";
      $res = msql_query($query, $dbi['dbc']);
      if (msql_num_rows($res)) {
         $dbhash = msql_fetch_array($res);

         $query = "select lineno,line from $pagetable " .
                  "where pagename='$pagename' " .
                  "order by lineno";

         if ($res = msql_query($query, $dbi['dbc'])) {
            $dbhash["content"] = array();
            while ($row = msql_fetch_array($res)) {
		$msql_content .= $row["line"];
            }
            $dbhash["content"] = explode("\n", $msql_content);
         }

         return MakePageHash($dbhash);
      }
      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {

      $pagehash = MakeDBHash($pagename, $pagehash);
      // $pagehash["content"] is now an array of strings 
      // of MSQL_MAX_LINE_LENGTH

      // record the time of modification
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

      // echo "<p>Query: $query<p>\n";

      // first, insert the metadata
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) 
         echo "Insert/update failed: ", msql_error(), "<br>\n";


      // second, insert the page data
      // remove old data from page_table
      $query = "delete from $dbi[page_table] where pagename='$pagename'";
      // echo "Delete query: $query<br>\n";
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
         // echo "Page line insert query: $query<br>\n";
         $retval = msql_query($query, $dbi['dbc']);
         if ($retval == false) 
            echo "Insert into $dbi[page_table] failed: ", 
                  msql_error(), "<br>\n";
         
      }

   }



   // for archiving pages to a separate table
   function SaveCopyToArchive($dbi, $pagename, $pagehash) {
      global $ArchivePageStore;

      $pagehash = MakeDBHash($pagename, $pagehash);
      // $pagehash["content"] is now an array of strings 
      // of MSQL_MAX_LINE_LENGTH

      if (IsInArchive($dbi, $pagename)) {

         $PAIRS = "author='$pagehash[author]'," .
                  "created=$pagehash[created]," .
                  "flags=$pagehash[flags]," .
                  "lastmodified=$pagehash[lastmodified]," .
                  "pagename='$pagehash[pagename]'," .
                  "refs='$pagehash[refs]'," .
                  "version=$pagehash[version]";

         $query  = "UPDATE $ArchivePageStore[table] SET $PAIRS WHERE pagename='$pagename'";

      } else {
         // do an insert
         // build up the column names and values for the query

         $COLUMNS = "author, created, flags, lastmodified, " .
                    "pagename, refs, version";

         $VALUES =  "'$pagehash[author]', " .
                    "$pagehash[created], $pagehash[flags], " .
                    "$pagehash[lastmodified], '$pagehash[pagename]', " .
                    "'$pagehash[refs]', $pagehash[version]";


         $query = "INSERT INTO archive ($COLUMNS) VALUES($VALUES)";
      }

      // echo "<p>Query: $query<p>\n";

      // first, insert the metadata
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) 
         echo "Insert/update failed: ", msql_error(), "<br>\n";


      // second, insert the page data
      // remove old data from page_table
      $query = "delete from $ArchivePageStore[page_table] where pagename='$pagename'";
      // echo "Delete query: $query<br>\n";
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) 
         echo "Delete on $ArchivePageStore[page_table] failed: ", msql_error(), "<br>\n";

      // insert the new lines
      reset($pagehash["content"]);

      for ($x = 0; $x < count($pagehash["content"]); $x++) {
         $line = addslashes($pagehash["content"][$x]);
         $query = "INSERT INTO $ArchivePageStore[page_table] " .
                  "(pagename, lineno, line) " .
                  "VALUES('$pagename', $x, '$line')";
         // echo "Page line insert query: $query<br>\n";
         $retval = msql_query($query, $dbi['dbc']);
         if ($retval == false) 
            echo "Insert into $ArchivePageStore[page_table] failed: ", 
                  msql_error(), "<br>\n";
         
      }


   }


   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select pagename from wiki where pagename='$pagename'";
      // echo "Query: $query<br>\n";
      if ($res = msql_query($query, $dbi['dbc'])) {
         return(msql_affected_rows($res));
      }
   }


   function IsInArchive($dbi, $pagename) {
      $pagename = addslashes($pagename);
      $query = "select pagename from archive where pagename='$pagename'";
      // echo "Query: $query<br>\n";
      if ($res = msql_query($query, $dbi['dbc'])) {
         return(msql_affected_rows($res));
      }
   }



   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $search = addslashes($search);
      $query = "select pagename from $dbi[table] " .
               "where pagename clike '%$search%' order by pagename";
      $res = msql_query($query, $dbi['dbc']);

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
      // select unique page names from wikipages, and then 
      // retrieve all pages that come back.
      $search = addslashes($search);
      $query = "select distinct pagename from $dbi[page_table] " .
               "where line clike '%$search%' " .
               "order by pagename";
      $res = msql_query($query, $dbi['dbc']);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      global $WikiPageStore;
      if ($row = msql_fetch_row($res)) {
	return RetrievePage($dbi, $row[0], $WikiPageStore);
      } else {
	return 0;
      }
   }

   ////////////////////////
   // new database features


   function IncreaseHitCount($dbi, $pagename) {

      $query = "select hits from hitcount where pagename='$pagename'";
      $res = msql_query($query, $dbi['dbc']);
      if (msql_num_rows($res)) {
         $hits = msql_result($res, 0, 'hits');
         $hits++;
         $query = "update hitcount set hits=$hits where pagename='$pagename'";
         $res = msql_query($query, $dbi['dbc']);

      } else {
         $query = "insert into hitcount (pagename, hits) " .
                  "values ('$pagename', 1)";
	 $res = msql_query($query, $dbi['dbc']);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename) {

      $query = "select hits from hitcount where pagename='$pagename'";
      $res = msql_query($query, $dbi['dbc']);
      if (msql_num_rows($res)) {
         $hits = msql_result($res, 0, 'hits');
      } else {
         $hits = "0";
      }

      return $hits;
   }



   function InitMostPopular($dbi, $limit) {

      $query = "select * from hitcount " .
               "order by hits desc, pagename limit $limit";

      $res = msql_query($query, $dbi['dbc']);
      
      return $res;
   }

   function MostPopularNextMatch($dbi, $res) {

      if ($hits = msql_fetch_array($res)) {
	 return $hits;
      } else {
         return 0;
      }
   }

   function GetAllWikiPageNames($dbi_) {
      $res = msql_query("select pagename from wiki", $dbi['dbc']);
      $rows = msql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $pages[$i] = msql_result($res, $i, 'pagename');
      }
      return $pages;
   }


?>
