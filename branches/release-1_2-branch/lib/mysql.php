<?php rcs_id('$Id: mysql.php,v 1.10.2.7 2005-01-07 14:23:05 rurban Exp $');

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
      InitBackLinkSearch($dbi, $pagename) 
      BackLinkSearchNextMatch($dbi, &$pos) 
      InitMostPopular($dbi, $limit)
      MostPopularNextMatch($dbi, $res)
      GetAllWikiPageNames($dbi)
      GetWikiPageLinks($dbi, $pagename)
      SetWikiPageLinks($dbi, $pagename, $linklist)
   */

   // open a database and return the handle
   // ignores MAX_DBM_ATTEMPTS

   function OpenDataBase($dbname) {
      global $mysql_server, $mysql_user, $mysql_pwd, $mysql_db;

      if (!($dbc = mysql_pconnect($mysql_server, $mysql_user, $mysql_pwd))) {
         $msg = gettext ("Cannot establish connection to database, giving up.");
	 $msg .= "<BR>";
	 $msg .= sprintf(gettext ("MySQL error: %s"), mysql_error());
	 ExitWiki($msg);
      }
      if (!mysql_select_db($mysql_db, $dbc)) {
         $msg =  sprintf(gettext ("Cannot open database %s, giving up."), $mysql_db);
	 $msg .= "<BR>";
	 $msg .= sprintf(gettext ("MySQL error: %s"), mysql_error());
	 ExitWiki($msg);
      }
      $dbi['dbc'] = $dbc;
      $dbi['table'] = $dbname;
      return $dbi;
   }


   function CloseDataBase($dbi) {
      // NOP function
      // mysql connections are established as persistant
      // they cannot be closed through mysql_close()
   }


   // prepare $pagehash for storing in mysql
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


   // convert mysql result $dbhash to $pagehash
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
      if ($res = mysql_query("select * from $pagestore where pagename='$pagename'", $dbi['dbc'])) {
         if ($dbhash = mysql_fetch_array($res)) {
            return MakePageHash($dbhash);
         }
      }
      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash)
   {
      global $WikiPageStore; // ugly hack

      if ($dbi['table'] == $WikiPageStore) { // HACK
         $linklist = ExtractWikiPageLinks($pagehash['content']);
	 SetWikiPageLinks($dbi, $pagename, $linklist);
      }

      $pagehash = MakeDBHash($pagename, $pagehash);

      $COLUMNS = "author, content, created, flags, " .
                 "lastmodified, pagename, refs, version";

      $VALUES =  "'$pagehash[author]', '$pagehash[content]', " .
                 "$pagehash[created], $pagehash[flags], " .
                 "$pagehash[lastmodified], '$pagehash[pagename]', " .
                 "'$pagehash[refs]', $pagehash[version]";

      if (!mysql_query("replace into $dbi[table] ($COLUMNS) values ($VALUES)",
      			$dbi['dbc'])) {
            $msg = sprintf(gettext ("Error writing page '%s'"), $pagename);
	    $msg .= "<BR>";
	    $msg .= sprintf(gettext ("MySQL error: %s"), mysql_error());
            ExitWiki($msg);
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
      if ($res = mysql_query("select count(*) from $dbi[table] where pagename='$pagename'", $dbi['dbc'])) {
         return(mysql_result($res, 0));
      }
      return 0;
   }

   function IsInArchive($dbi, $pagename) {
      global $ArchivePageStore;

      $pagename = addslashes($pagename);
      if ($res = mysql_query("select count(*) from $ArchivePageStore where pagename='$pagename'", $dbi['dbc'])) {
         return(mysql_result($res, 0));
      }
      return 0;
   }


   function RemovePage($dbi, $pagename) {
      global $WikiPageStore, $ArchivePageStore;
      global $WikiLinksStore, $HitCountStore, $WikiScoreStore;

      $pagename = addslashes($pagename);
      $msg = gettext ("Cannot delete '%s' from table '%s'");
      $msg .= "<br>\n";
      $msg .= gettext ("MySQL error: %s");

      if (!mysql_query("delete from $WikiPageStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiPageStore, mysql_error()));

      if (!mysql_query("delete from $ArchivePageStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $ArchivePageStore, mysql_error()));

      if (!mysql_query("delete from $WikiLinksStore where frompage='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiLinksStore, mysql_error()));

      if (!mysql_query("delete from $HitCountStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $HitCountStore, mysql_error()));

      if (!mysql_query("delete from $WikiScoreStore where pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiScoreStore, mysql_error()));
   }


   function IncreaseHitCount($dbi, $pagename)
   {
      global $HitCountStore;

      $qpagename = addslashes($pagename);
      $res = mysql_query("update $HitCountStore set hits=hits+1"
                         . " where pagename='$qpagename'",
                         $dbi['dbc']);

      if (!mysql_affected_rows($dbi['dbc'])) {
	 $res = mysql_query("insert into $HitCountStore (pagename, hits)"
                            . " values ('$qpagename', 1)",
                            $dbi['dbc']);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename)
   {
      global $HitCountStore;

      $qpagename = addslashes($pagename);
      $res = mysql_query("select hits from $HitCountStore"
                         . " where pagename='$qpagename'",
                         $dbi['dbc']);
      if (mysql_num_rows($res))
         $hits = mysql_result($res, 0);
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
         $word = strtolower("$term");
	 if ($word[0] == '-') {
	    $word = substr($word, 1);
	    $clause .= "not (LCASE($column) like '%$word%') ";
	 } else {
	    $clause .= "(LCASE($column) like '%$word%') ";
	 }
	 if ($term = strtok(' '))
	    $clause .= 'AND ';
      }
      
      return $clause;
   }

   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $clause = MakeSQLSearchClause($search, 'pagename');
      $res = mysql_query("select pagename from $dbi[table] where $clause order by pagename", $dbi["dbc"]);

      return $res;
   }


   // iterating through database
   function TitleSearchNextMatch($dbi, $res) {
      if($o = mysql_fetch_object($res)) {
         return $o->pagename;
      }
      else {
         return 0;
      }
   }


   // setup for full-text search
   function InitFullSearch($dbi, $search) {
      $clause = MakeSQLSearchClause($search, 'content');
      $res = mysql_query("select * from $dbi[table] where $clause", $dbi["dbc"]);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      if($hash = mysql_fetch_array($res)) {
         return MakePageHash($hash);
      }
      else {
         return 0;
      }
   }

   // setup for back-link search
   function InitBackLinkSearch($dbi, $pagename) {
      global $WikiLinksStore;
     
      $topage = addslashes($pagename);
      $res = mysql_query( "SELECT DISTINCT frompage FROM $WikiLinksStore"
			  . " WHERE topage='$topage'"
			  . " ORDER BY frompage",
			  $dbi["dbc"]);
      return $res;
   }


   // iterating through database
   function BackLinkSearchNextMatch($dbi, $res) {
      if($a = mysql_fetch_row($res)) {
         return $a[0];
      }
      else {
         return 0;
      }
   }


   function InitMostPopular($dbi, $limit) {
      global $HitCountStore;
      $res = mysql_query("select * from $HitCountStore order by hits desc, pagename limit $limit", $dbi["dbc"]);
      
      return $res;
   }

   function MostPopularNextMatch($dbi, $res) {
      if ($hits = mysql_fetch_array($res))
	 return $hits;
      else
         return 0;
   }

   function GetAllWikiPageNames($dbi) {
      global $WikiPageStore;
      $res = mysql_query("select pagename from $WikiPageStore", $dbi["dbc"]);
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $pages[$i] = mysql_result($res, $i);
      }
      return $pages;
   }
   
   
   ////////////////////////////////////////
   // functionality for the wikilinks table

   // takes a page name, returns array of scored incoming and outgoing links
   function GetWikiPageLinks($dbi, $pagename) {
      global $WikiLinksStore, $WikiScoreStore, $HitCountStore;

      $pagename = addslashes($pagename);
      $res = mysql_query("select topage, score from $WikiLinksStore, $WikiScoreStore where topage=pagename and frompage='$pagename' order by score desc, topage");
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mysql_fetch_array($res);
	 $links['out'][] = array($out['topage'], $out['score']);
      }

      $res = mysql_query("select frompage, score from $WikiLinksStore, $WikiScoreStore where frompage=pagename and topage='$pagename' order by score desc, frompage");
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mysql_fetch_array($res);
	 $links['in'][] = array($out['frompage'], $out['score']);
      }

      $res = mysql_query("select distinct pagename, hits from $WikiLinksStore, $HitCountStore where (frompage=pagename and topage='$pagename') or (topage=pagename and frompage='$pagename') order by hits desc, pagename");
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mysql_fetch_array($res);
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
      mysql_query("delete from $WikiLinksStore where frompage='$frompage'",
		$dbi["dbc"]);

      // the page may not have links, return if not
      if (! count($linklist))
         return;
      // now insert the new list of links
      while (list($topage, $count) = each($linklist)) {
         $topage = addslashes($topage);
	 if($topage != $frompage) {
            mysql_query("insert into $WikiLinksStore (frompage, topage) " .
                     "values ('$frompage', '$topage')", $dbi["dbc"]);
	 }
      }

      // update pagescore
      mysql_query("delete from $WikiScoreStore", $dbi["dbc"]);
      mysql_query("insert into $WikiScoreStore select w1.topage, count(*) from $WikiLinksStore as w1, $WikiLinksStore as w2 where w2.topage=w1.frompage group by w1.topage", $dbi["dbc"]);
   }

/* more mysql queries:

orphans:
select pagename from wiki left join wikilinks on pagename=topage where topage is NULL;
*/
?>