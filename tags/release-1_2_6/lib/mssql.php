<?php rcs_id('$Id: mssql.php,v 1.1.2.6 2005-01-07 13:59:58 rurban Exp $');

   /* Microsoft SQL-Server library for PHPWiki
      Author: Andrew K. Pearson
	  Date:   01 May 2001
	*/

   /*
      Database functions:
      OpenDataBase($dbname)
      CloseDataBase($dbi)
      MakeDBHash($pagename, $pagehash)
      MakePageHash($dbhash)
      RetrievePage($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash)
      SaveCopyToArchive($dbi, $pagename, $pagehash)
      IsWikiPage($dbi, $pagename)
      IsInArchive($dbi, $pagename)
      RemovePage($dbi, $pagename)
      IncreaseHitCount($dbi, $pagename)
      GetHitCount($dbi, $pagename)
      MakeSQLSearchClause($search, $column)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, $res)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, $res)
      InitMostPopular($dbi, $limit)
      MostPopularNextMatch($dbi, $res)
      GetAllWikiPageNames($dbi)
      GetWikiPageLinks($dbi, $pagename)
      SetWikiPageLinks($dbi, $pagename, $linklist)
   */

   // open a database and return the handle
   // ignores MAX_DBM_ATTEMPTS

   function OpenDataBase($dbname) {
      global $mssql_server, $mssql_user, $mssql_pwd, $mssql_db;

      if (!($dbc = mssql_pconnect($mssql_server, $mssql_user, $mssql_pwd))) {
         $msg = gettext ("Cannot establish connection to database, giving up.");
	     $msg .= "<BR>";
	     $msg .= sprintf(gettext ("MSSQL error: %s"), mssql_get_last_message());
	     ExitWiki($msg);
      }
	  // flush message
	  mssql_get_last_message();

      if (!mssql_select_db($mssql_db, $dbc)) {
         $msg =  sprintf(gettext ("Cannot open database %s, giving up."), $mssql_db);
	     $msg .= "<BR>";
	     $msg .= sprintf(gettext ("MSSQL error: %s"), mssql_get_last_message());
	     ExitWiki($msg);
      }
	  // flush message
	  mssql_get_last_message();

      $dbi['dbc'] = $dbc;
      $dbi['table'] = $dbname;
      return $dbi;
   }


   function CloseDataBase($dbi) {
      // NOP function
      // mssql connections are established as persistant
      // they cannot be closed through mssql_close()
   }


   // prepare $pagehash for storing in mssql
   function MakeDBHash($pagename, $pagehash)
   {
      $pagehash["pagename"] = addslashes($pagename);
      if (!isset($pagehash["flags"]))
         $pagehash["flags"] = 0;
      $pagehash["author"] = addslashes($pagehash["author"]);
      $pagehash["content"] = implode("\n", $pagehash["content"]);
      $pagehash["content"] = addslashes($pagehash["content"]);
      if (!isset($pagehash["refs"]))
         $pagehash["refs"] = array();
      $pagehash["refs"] = serialize($pagehash["refs"]);
 
      return $pagehash;
   }


   // convert mssql result $dbhash to $pagehash
   function MakePageHash($dbhash)
   {
      // unserialize/explode content
      $dbhash['refs'] = unserialize($dbhash['refs']);
      $dbhash['content'] = explode("\n", $dbhash['content']);
      return $dbhash;
   }


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename, $pagestore) {
      $pagename = addslashes($pagename);
      if ($res = mssql_query("select * from $pagestore where pagename='$pagename'", $dbi['dbc'])) {
         if ($dbhash = mssql_fetch_array($res)) {
            return MakePageHash($dbhash);
         }
      }
      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {

      global $WikiPageStore; // ugly hack
      if ($dbi['table'] == $WikiPageStore) 
      { // HACK
         $linklist = ExtractWikiPageLinks($pagehash['content']);
	 	 SetWikiPageLinks($dbi, $pagename, $linklist);
      }

      $pagehash = MakeDBHash($pagename, $pagehash);

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

         $query  = "UPDATE $dbi[table] SET $PAIRS WHERE pagename='$pagename'";

      } else {
         // do an insert
         // build up the column names and values for the query

         $COLUMNS = "author, content, created, flags, lastmodified, " .
                    "pagename, refs, version";

         $VALUES =  "'$pagehash[author]', '$pagehash[content]', " .
                    "$pagehash[created], $pagehash[flags], " .
                    "$pagehash[lastmodified], '$pagehash[pagename]', " .
                    "'$pagehash[refs]', $pagehash[version]";


         $query = "INSERT INTO $dbi[table] ($COLUMNS) VALUES($VALUES)";
      }

      //echo "<p>Insert/Update Query: $query<p>\n";

      $retval = mssql_query($query);
      if ($retval == false) {
	     printf(gettext ("Insert/Update failed: %s <br>\n"), mssql_get_last_message());
      }
   }


   // for archiving pages to a seperate dbm
   function SaveCopyToArchive($dbi, $pagename, $pagehash) {
      global $ArchivePageStore;
      $adbi = OpenDataBase($ArchivePageStore);
      InsertPage($adbi, $pagename, $pagehash);
   }


   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      if ($res = mssql_query("select count(*) from $dbi[table] where pagename='$pagename'", $dbi['dbc'])) {
         return(mssql_result($res, 0, 0));
      }
      return 0;
   }

   function IsInArchive($dbi, $pagename) {
      global $ArchivePageStore;

      $pagename = addslashes($pagename);
      if ($res = mssql_query("select count(*) from $ArchivePageStore where pagename='$pagename'", $dbi['dbc'])) {
         return(mssql_result($res, 0, 0));
      }
      return 0;
   }


   function RemovePage($dbi, $pagename) {
      global $WikiPageStore, $ArchivePageStore;
      global $WikiLinksStore, $HitCountStore, $WikiScoreStore;

      $pagename = addslashes($pagename);
      $msg = gettext ("Cannot delete '%s' from table '%s'");
      $msg .= "<br>\n";
      $msg .= gettext ("MSSQL error: %s");

      if (!mssql_query("delete from $WikiPageStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiPageStore, mssql_get_last_message()));

      if (!mssql_query("delete from $ArchivePageStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $ArchivePageStore, mssql_get_last_message()));

      if (!mssql_query("delete from $WikiLinksStore where frompage='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiLinksStore, mssql_get_last_message()));

      if (!mssql_query("delete from $HitCountStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $HitCountStore, mssql_get_last_message()));

      if (!mssql_query("delete from $WikiScoreStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiScoreStore, mssql_get_last_message()));
   }


   function IncreaseHitCount($dbi, $pagename)
   {
      global $HitCountStore;

      $qpagename = addslashes($pagename);
      $rowexists = 0;
      if ($res = mssql_query("select count(*) from $dbi[table] where pagename='$qpagename'", $dbi['dbc'])) {
         $rowexists = (mssql_result($res, 0, 0));
      }

      if ($rowexists)
         $res = mssql_query("update $HitCountStore set hits=hits+1 where pagename='$qpagename'", $dbi['dbc']);
      else
	     $res = mssql_query("insert into $HitCountStore (pagename, hits) values ('$qpagename', 1)", $dbi['dbc']);

      return $res;
   }

   function GetHitCount($dbi, $pagename)
   {
      global $HitCountStore;

      $qpagename = addslashes($pagename);
      $res = mssql_query("select hits from $HitCountStore where pagename='$qpagename'", $dbi['dbc']);
      if (mssql_num_rows($res))
         $hits = mssql_result($res, 0, 0);
      else
         $hits = "0";

      return $hits;
   }

   function MakeSQLSearchClause($search, $column)
   {
      $search = preg_replace("/\s+/", " ", trim($search));
      $search = preg_replace('/(?=[%_\\\\])/', "\\", $search);
      $search = addslashes($search);

      $term = strtok($search, ' ');
      $clause = '';
      while($term) {
         $word = "$term";
	 if ($word[0] == '-') {
	    $word = substr($word, 1);
	    $clause .= "not ($column like '%$word%') ";
	 } else {
	    $clause .= "($column like '%$word%') ";
	 }
	 if ($term = strtok(' '))
	    $clause .= 'and ';
      }
      return $clause;
   }

   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $clause = MakeSQLSearchClause($search, 'pagename');
      $res = mssql_query("select pagename from $dbi[table] where $clause order by pagename", $dbi["dbc"]);

      return $res;
   }


   // iterating through database
   function TitleSearchNextMatch($dbi, $res) {
      if($o = mssql_fetch_object($res)) {
         return $o->pagename;
      }
      else {
         return 0;
      }
   }


   // setup for full-text search
   function InitFullSearch($dbi, $search) {
      $clause = MakeSQLSearchClause($search, 'content');
      $res = mssql_query("select * from $dbi[table] where $clause", $dbi["dbc"]);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      if($hash = mssql_fetch_array($res)) {
         return MakePageHash($hash);
      }
      else {
         return 0;
      }
   }

   function InitMostPopular($dbi, $limit) {
      global $HitCountStore;
      $res = mssql_query("select top $limit * from $HitCountStore order by hits desc, pagename", $dbi["dbc"]);
      
      return $res;
   }

   function MostPopularNextMatch($dbi, $res) {
      if ($hits = mssql_fetch_array($res))
	 return $hits;
      else
         return 0;
   }

   function GetAllWikiPageNames($dbi) {
      global $WikiPageStore;
      $res = mssql_query("select pagename from $WikiPageStore", $dbi["dbc"]);
      $rows = mssql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $pages[$i] = mssql_result($res, $i, 0);
      }
      return $pages;
   }
   
   
   ////////////////////////////////////////
   // functionality for the wikilinks table

   // takes a page name, returns array of scored incoming and outgoing links
   function GetWikiPageLinks($dbi, $pagename) {
      global $WikiLinksStore, $WikiScoreStore, $HitCountStore;

      $pagename = addslashes($pagename);
      $res = mssql_query("select topage, score from $WikiLinksStore, $WikiScoreStore where topage=pagename and frompage='$pagename' order by score desc, topage");
      $rows = mssql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mssql_fetch_array($res);
	 $links['out'][] = array($out['topage'], $out['score']);
      }

      $res = mssql_query("select frompage, score from $WikiLinksStore, $WikiScoreStore where frompage=pagename and topage='$pagename' order by score desc, frompage");
      $rows = mssql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mssql_fetch_array($res);
	 $links['in'][] = array($out['frompage'], $out['score']);
      }

      $res = mssql_query("select distinct pagename, hits from $WikiLinksStore, $HitCountStore where (frompage=pagename and topage='$pagename') or (topage=pagename and frompage='$pagename') order by hits desc, pagename");
      $rows = mssql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mssql_fetch_array($res);
	 $links['popular'][] = array($out['pagename'], $out['hits']);
      }

      return $links;
   }


   // takes page name, list of links it contains
   // the $linklist is an array where the keys are the page names
   function SetWikiPageLinks($dbi, $pagename, $linklist) {
      global $WikiLinksStore, $WikiScoreStore;

      $frompage = addslashes($pagename);

      // first delete the old list of links
      mssql_query("delete from $WikiLinksStore where frompage='$frompage'",
		$dbi["dbc"]);

      // the page may not have links, return if not
      if (! count($linklist))
         return;
      // now insert the new list of links
      while (list($topage, $count) = each($linklist)) {
         $topage = addslashes($topage);
	 if($topage != $frompage) {
            mssql_query("insert into $WikiLinksStore (frompage, topage) " .
                     "values ('$frompage', '$topage')", $dbi["dbc"]);
	 }
      }

      // update pagescore
      mssql_query("delete from $WikiScoreStore", $dbi["dbc"]);
      mssql_query("insert into $WikiScoreStore select w1.topage, count(*) from $WikiLinksStore as w1, $WikiLinksStore as w2 where w2.topage=w1.frompage group by w1.topage", $dbi["dbc"]);
   }

/* more mssql queries:

orphans:
select pagename from wiki left join wikilinks on pagename=topage where topage is NULL;
*/
?>