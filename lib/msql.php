<?php rcs_id('$Id: msql.php,v 1.6.2.9 2005-01-07 14:02:28 rurban Exp $');

   /*
      Database functions:
      MakePageHash($dbhash)
      MakeDBHash($pagename, $pagehash)
      OpenDataBase($dbname)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash)
      SaveCopyToArchive($dbi, $pagename, $pagehash) 
      IsWikiPage($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, &$pos)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, &$pos)
      MakeBackLinkSearchRegexp($pagename)
      InitBackLinkSearch($dbi, $pagename) 
      BackLinkSearchNextMatch($dbi, &$pos) 
      GetAllWikiPageNames($dbi)
   */


   // open a database and return the handle
   // ignores MAX_DBM_ATTEMPTS

   function OpenDataBase($dbinfo) {
      global $msql_db;

      if (! ($dbc = msql_connect())) {
         $msg = gettext ("Cannot establish connection to database, giving up.");
	 $msg .= "<BR>";
	 $msg .= sprintf(gettext ("Error message: %s"), msql_error());
	 ExitWiki($msg);
      }
      if (!msql_select_db($msql_db, $dbc)) {
         $msg = gettext ("Cannot open database %s, giving up.");
	 $msg .= "<BR>";
	 $msg .= sprintf(gettext ("Error message: %s"), msql_error());
	 ExitWiki($msg);
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

      // initialize the array to satisfy E_NOTICE
      for ($i = 0; $i < MSQL_MAX_LINE_LENGTH; $i++) {
         $ret_arr[$i] = "";
      }
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
      if (empty($pagehash["refs"])) {
         $pagehash["refs"] = "";
      } else {
         $pagehash["refs"] = serialize($pagehash["refs"]);
      }

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

         $msql_content = "";
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
      if ($retval == false) {
         printf(gettext ("Insert/update to table 'wiki' failed: %s"), msql_error());
         print "<br>\n";
      }


      // second, insert the page data
      // remove old data from page_table
      $query = "delete from $dbi[page_table] where pagename='$pagename'";
      // echo "Delete query: $query<br>\n";
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) {
         printf(gettext ("Delete on %s failed: %s"), $dbi[page_table],
            msql_error());
         print "<br>\n";
      }

      // insert the new lines
      reset($pagehash["content"]);

      for ($x = 0; $x < count($pagehash["content"]); $x++) {
         $line = addslashes($pagehash["content"][$x]);
         if ($line == '') continue; // why do we always have 127 lines?
         $esc_pagename = addslashes($pagename);
         $query = "INSERT INTO $dbi[page_table] " .
                  "(pagename, lineno, line) " .
                  "VALUES('$esc_pagename', $x, '$line')";
         //echo "Page line insert query: $query<br>\n";
         $retval = msql_query($query, $dbi['dbc']);
         if ($retval == false) { 
            printf(gettext ("Insert into %s failed: %s"), $dbi[page_table],
               msql_error());
	    print "<br>\n";
	 }
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
      if ($retval == false) {
         printf(gettext ("Insert/update into table 'archive' failed: %s"), msql_error());
	 print "<br>\n";
      }

      // second, insert the page data
      // remove old data from page_table
      $query = "delete from $ArchivePageStore[page_table] where pagename='$pagename'";
      // echo "Delete query: $query<br>\n";
      $retval = msql_query($query, $dbi['dbc']);
      if ($retval == false) {
         printf(gettext ("Delete on %s failed: %s"),
          $ArchivePageStore[page_table], msql_error());
         print "<br>\n";
      }

      // insert the new lines
      reset($pagehash["content"]);

      for ($x = 0; $x < count($pagehash["content"]); $x++) {
         $line = addslashes($pagehash["content"][$x]);
         $query = "INSERT INTO $ArchivePageStore[page_table] " .
                  "(pagename, lineno, line) " .
                  "VALUES('$pagename', $x, '$line')";
         // echo "Page line insert query: $query<br>\n";
         $retval = msql_query($query, $dbi['dbc']);
         if ($retval == false) {
            printf(gettext ("Insert into %s failed: %s"),
              $ArchivePageStore[page_table], msql_error());
            print "<br>\n";
         }
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
      $search = preg_replace('/(?=[%_\\\\])/', "\\", $search);
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
      $search = preg_replace('/(?=[%_\\\\])/', "\\", $search);
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

   // Compute PCRE suitable for searching for links to the given page.
   function MakeBackLinkSearchRegexp($pagename) {
      global $WikiNameRegexp;

      // Note that in (at least some) PHP 3.x's, preg_quote only takes
      // (at most) one argument.  Also it doesn't quote '/'s.
      // It does quote '='s, so we'll use that for the delimeter.
      $quoted_pagename = preg_quote($pagename);
      if (preg_match("/^$WikiNameRegexp\$/", $pagename)) {
	 # FIXME: This may need modification for non-standard (non-english) $WikiNameRegexp.
	 return "=(?<![A-Za-z0-9!])$quoted_pagename(?![A-Za-z0-9])=";
      }
      else {
	 // Note from author: Sorry. :-/
	 return ( '='
		  . '(?<!\[)\[(?!\[)' // Single, isolated '['
		  . '([^]|]*\|)?'     // Optional stuff followed by '|'
	          . '\s*'             // Optional space
		  . $quoted_pagename  // Pagename
		  . '\s*\]=' );	      // Optional space, followed by ']'
	 // FIXME: the above regexp is still not quite right.
	 // Consider the text: " [ [ test page ]".  This is a link to a page
	 // named '[ test page'.  The above regexp will recognize this
	 // as a link either to '[ test page' (good) or to 'test page' (wrong).
      } 
   }

   // setup for back-link search
   function InitBackLinkSearch($dbi, $pagename) {
      global $WikiLinksStore;
     
      $topage = addslashes($pagename);
      $res[arr] = array();

      // FIXME: this is buggy.  If a [multiword link] is split accross
      // multiple lines int the page_table, we wont find it.
      // (Probably the best fix is to implement the link table, and use it.)
      $res['regexp'] = MakeBackLinkSearchRegexp($pagename);
      $query = "SELECT pagename, line FROM $dbi[page_table]"
           . " WHERE line LIKE '%$topage%'"
           . " ORDER BY pagename";
      
      //echo "<p>$query<p>\n";
      $res['res'] = msql_query($query, $dbi["dbc"]);

      $count = 0;
      $arr = array();

      // build an array of the results.
      while ($hash = msql_fetch_array($res[res]) ) {
          if ($arr[$count -1 ] == $hash[pagename])
              continue;
          $arr[$count] = $hash[pagename];
          $count++;
      }

      $res[count] = 0;
      reset($arr);
      $res[arr] = $arr;
      
      return $res;
   }


// iterating through database
function BackLinkSearchNextMatch($dbi, &$res) {

    if ($res[count] > count($res[arr]))
        return 0;

    $retval = $res[arr][$res[count]];
    $res[count]++;

    return $retval;
}

/*
            if ( ($o = msql_fetch_object($res['res'])) == FALSE ) {
            echo "returning zero<p>\n";
            echo "it's '$o' <p>\n";
            return 0;
            }
            if ( $res['lastpage'] == $o->pagename )
            continue;
            if ( ! preg_match($res['regexp'], $a->line) )
            continue;
            $res['lastpage'] = $o->pagename;
            return $o->pagename;
            }
          */


   function IncreaseHitCount($dbi, $pagename) {

      $qpagename = addslashes($pagename);
      $query = "select hits from hitcount where pagename='$qpagename'";
      $res = msql_query($query, $dbi['dbc']);
      if (msql_num_rows($res)) {
         $hits = msql_result($res, 0, 'hits');
         $hits++;
         $query = "update hitcount set hits=$hits where pagename='$qpagename'";
         $res = msql_query($query, $dbi['dbc']);

      } else {
         $query = "insert into hitcount (pagename, hits) " .
                  "values ('$qpagename', 1)";
	 $res = msql_query($query, $dbi['dbc']);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename) {

      $qpagename = addslashes($pagename);
      $query = "select hits from hitcount where pagename='$qpagename'";
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

   function GetAllWikiPageNames($dbi) {
      $res = msql_query("select pagename from wiki", $dbi['dbc']);
      $rows = msql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $pages[$i] = msql_result($res, $i, 'pagename');
      }
      return $pages;
   }

   ////////////////////////////////////////
   // functionality for the wikilinks table

   // takes a page name, returns array of scored incoming and outgoing links

/* Not implemented yet. The code below was copied from mysql.php...

   function GetWikiPageLinks($dbi, $pagename) {
      $links = array();
      $pagename = addslashes($pagename);
      $res = msql_query("select wikilinks.topage, wikiscore.score from wikilinks, wikiscore where wikilinks.topage=wikiscore.pagename and wikilinks.frompage='$pagename' order by score desc, topage", $dbi['dbc']);

      $rows = msql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = msql_fetch_array($res);
	 $links['out'][] = array($out['topage'], $out['score']);
      }

      $res = msql_query("select wikilinks.frompage, wikiscore.score from wikilinks, wikiscore where wikilinks.frompage=wikiscore.pagename and wikilinks.topage='$pagename' order by score desc, frompage", $dbi['dbc']);
      $rows = msql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = msql_fetch_array($res);
	 $links['in'][] = array($out['frompage'], $out['score']);
      }

      $res = msql_query("select distinct hitcount.pagename, hitcount.hits from wikilinks, hitcount where (wikilinks.frompage=hitcounts.pagename and wikilinks.topage='$pagename') or (wikilinks.topage=pagename and wikilinks.frompage='$pagename') order by hitcount.hits desc, wikilinks.pagename", $dbi['dbc']);
      $rows = msql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = msql_fetch_array($res);
	 $links['popular'][] = array($out['pagename'], $out['hits']);
      }

      return $links;
   }


   // takes page name, list of links it contains
   // the $linklist is an array where the keys are the page names
   function SetWikiPageLinks($dbi, $pagename, $linklist) {
      $frompage = addslashes($pagename);

      // first delete the old list of links
      msql_query("delete from wikilinks where frompage='$frompage'",
		$dbi["dbc"]);

      // the page may not have links, return if not
      if (! count($linklist))
         return;
      // now insert the new list of links
      while (list($topage, $count) = each($linklist)) {
         $topage = addslashes($topage);
	 if($topage != $frompage) {
            msql_query("insert into wikilinks (frompage, topage) " .
                     "values ('$frompage', '$topage')", $dbi["dbc"]);
	 }
      }

      msql_query("delete from wikiscore", $dbi["dbc"]);
      msql_query("insert into wikiscore select w1.topage, count(*) from wikilinks as w1, wikilinks as w2 where w2.topage=w1.frompage group by w1.topage", $dbi["dbc"]);
   }
*/

?>