<?php rcs_id('$Id: pgsql.php,v 1.4.2.9 2005-01-07 14:23:05 rurban Exp $');

   /*
      Database functions:

      OpenDataBase($table)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash)
      SaveCopyToArchive($dbi, $pagename, $pagehash) 
      IsWikiPage($dbi, $pagename)
      IsInArchive($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, $res)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, $res)
      InitBackLinkSearch($dbi, $pagename) 
      BackLinkSearchNextMatch($dbi, &$pos) 
      IncreaseHitCount($dbi, $pagename)
      GetHitCount($dbi, $pagename)
      InitMostPopular($dbi, $limit)
      MostPopularNextMatch($dbi, $res)
      GetAllWikiPageNames($dbi)
      GetWikiPageLinks($dbi, $pagename)
      SetWikiPageLinks($dbi, $pagename, $linklist)
   */


   // open a database and return a hash

   function OpenDataBase($table) {
       global $WikiDataBase, $pg_dbhost, $pg_dbport, $pg_dbuser, $pg_dbpass;

      $connectstring = $pg_dbhost?"host=$pg_dbhost ":"";
      $connectstring .= $pg_dbport?"port=$pg_dbport ":"";
      $connectstring .= $pg_dbuser?"user=$pg_dbuser ":"";
      $connectstring .= $pg_dbpass?"password=$pg_dbpass ":"";
      $connectstring .= $WikiDataBase?"dbname=$WikiDataBase":"";

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
      // update the wikilinks table
      $linklist = ExtractWikiPageLinks($pagehash['content']);
      SetWikiPageLinks($dbi, $pagename, $linklist);


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

	 // Check for empty variables which can cause a sql error
	 if(empty($pagehash["created"]))
	 	$pagehash["created"] = time();
	 if(empty($pagehash["version"]))
	 	$pagehash["version"] = 1;

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

         $query = "UPDATE $ArchivePageStore SET $PAIRS WHERE pagename='$pagehash[pagename]'";

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
      global $WikiPageStore;   	
      $pagename = addslashes($pagename);
      $query = "select count(*) from $WikiPageStore " .
               "where pagename='$pagename'";
      $res = pg_exec($query);
      $array = pg_fetch_array($res, 0);
      return $array[0];
   }


   function IsInArchive($dbi, $pagename) {
	 global $ArchivePageStore;
      $pagename = addslashes($pagename);
      $query = "select count(*) from $ArchivePageStore " .
               "where pagename='$pagename'";
      $res = pg_exec($query);
      $array = pg_fetch_array($res, 0);
      return $array[0];
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {

      global $search_counter;
      $search_counter = 0;

      $search = strtolower($search);
      $search = preg_replace('/(?=[%_\\\\])/', "\\", $search);
      $search = addslashes($search);
      $query = "select pagename from $dbi[table] where lower(pagename) " .
               "like '%$search%' order by pagename";
      //echo "search query: $query<br>\n";
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
      $search = strtolower($search);
      $search = preg_replace('/(?=[%_\\\\])/', "\\", $search);
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

   // setup for back-link search
   function InitBackLinkSearch($dbi, $pagename) {
      global $WikiLinksPageStore;
     
      $topage = addslashes($pagename);
      $query = "SELECT DISTINCT frompage FROM $WikiLinksPageStore"
           . " WHERE topage='$topage'"
           . " ORDER BY frompage";
      $res['res'] = pg_exec( $dbi["dbc"], $query);
      $res['row'] = 0;
      return $res;
   }


// iterating through database
function BackLinkSearchNextMatch($dbi, &$res) {
    if($a = @pg_fetch_row($res['res'], $res['row'])) {
        $res['row']++;
        return $a[0];
    }
    else {
        return 0;
    }
}


   function IncreaseHitCount($dbi, $pagename) {
      global $HitCountPageStore;

      $qpagename = addslashes($pagename);
      $query = "update $HitCountPageStore set hits=hits+1 where pagename='$qpagename'";
      $res = pg_exec($dbi['dbc'], $query);

      if (!pg_cmdtuples($res)) {
         $query = "insert into $HitCountPageStore (pagename, hits) " .
                  "values ('$qpagename', 1)";
	 $res = pg_exec($dbi['dbc'], $query);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename) {
      global $HitCountPageStore;
      $qpagename = addslashes($pagename);
      $query = "select hits from $HitCountPageStore where pagename='$qpagename'";
      $res = pg_exec($dbi['dbc'], $query);
      if (pg_cmdtuples($res)) {
         $hits = pg_result($res, 0, "hits");
      } else {
         $hits = "0";
      }

      return $hits;
   }

   function InitMostPopular($dbi, $limit) {

      global $pg_most_pop_ctr, $HitCountPageStore;
      $pg_most_pop_ctr = 0;

      $query = "select * from $HitCountPageStore " .
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
      global $WikiPageStore;
      $res = pg_exec($dbi['dbc'], "select pagename from $WikiPageStore");
      $rows = pg_numrows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $pages[$i] = pg_result($res, $i, "pagename");
      }
      return $pages;
   }

   ////////////////////////////////////////
   // functionality for the wikilinks table

   // takes a page name, returns array of links
   function GetWikiPageLinks($dbi, $pagename) {
      global $WikiLinksPageStore;
      $pagename = addslashes($pagename);

      $res = pg_exec("select topage, score from wikilinks, wikiscore where topage=pagename and frompage='$pagename' order by score desc, topage");
      $rows = pg_numrows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = pg_fetch_array($res, $i);
	 $links['out'][] = array($out['topage'], $out['score']);
      }

      $res = pg_exec("select frompage, score from wikilinks, wikiscore where frompage=pagename and topage='$pagename' order by score desc, frompage");
      $rows = pg_numrows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = pg_fetch_array($res, $i);
	 $links['in'][] = array($out['frompage'], $out['score']);
      }

      $res = pg_exec("select distinct pagename, hits from wikilinks, hitcount where (frompage=pagename and topage='$pagename') or (topage=pagename and frompage='$pagename') order by hits desc, pagename");
      $rows = pg_numrows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = pg_fetch_array($res, $i);
	 $links['popular'][] = array($out['pagename'], $out['hits']);
      }

      return $links;

   }


   // takes page name, list of links it contains
   // the $linklist is an array where the keys are the page names

   function SetWikiPageLinks($dbi, $pagename, $linklist) {
      global $WikiLinksPageStore;
      $frompage = addslashes($pagename);

      // first delete the old list of links
      $query = "delete from $WikiLinksPageStore where frompage='$frompage'";
      //echo "$query<br>\n";
      $res = pg_exec($dbi['dbc'], $query);

      // the page may not have links, return if not
      if (! count($linklist))
         return;

      // now insert the new list of links
      reset($linklist);
      while (list($topage, $count) = each($linklist)) {
         $topage = addslashes($topage);
         if ($topage != $frompage) {
            $query = "insert into $WikiLinksPageStore (frompage, topage) " .
                     "values ('$frompage', '$topage')";
            //echo "$query<br>\n";
            $res = pg_exec($dbi['dbc'], $query);
         }
      }
      // update pagescore
      pg_exec("delete from wikiscore");
      pg_exec("insert into wikiscore select w1.topage, count(*) from wikilinks as w1, wikilinks as w2 where w2.topage=w1.frompage group by w1.topage");

   }


?>