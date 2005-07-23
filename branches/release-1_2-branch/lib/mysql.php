<?php rcs_id('$Id: mysql.php,v 1.10.2.8 2005-07-23 11:15:48 rurban Exp $');

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

      // smaller servers might benefit from mysql_pconnect, but larger ones 
      // may run out of connections
      if (!($dbc = mysql_connect($mysql_server, $mysql_user, $mysql_pwd))) {
         $msg = gettext ("Cannot establish connection to database, giving up.");
	 $msg .= "<br />";
	 $msg .= sprintf(gettext ("MySQL error: %s"), mysql_error());
	 ExitWiki($msg);
      }
      if (!mysql_select_db($mysql_db, $dbc)) {
         $msg =  sprintf(gettext ("Cannot open database %s, giving up."), $mysql_db);
	 $msg .= "<br />";
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
      if ($res = mysql_query("SELECT * FROM $pagestore WHERE pagename='$pagename'", $dbi['dbc'])) {
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

      if (!mysql_query("REPLACE INTO ".$dbi['table']." ($COLUMNS) VALUES ($VALUES)",
      			$dbi['dbc'])) {
            $msg = sprintf(gettext ("Error writing page '%s'"), $pagename);
	    $msg .= "<br />";
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
      if ($res = mysql_query("SELECT COUNT(*) FROM ".$dbi['table']." WHERE pagename='$pagename'", 
                             $dbi['dbc'])) {
         return(mysql_result($res, 0));
      }
      return 0;
   }

   function IsInArchive($dbi, $pagename) {
      global $ArchivePageStore;

      $pagename = addslashes($pagename);
      if ($res = mysql_query("SELECT COUNT(*) FROM $ArchivePageStore WHERE pagename='$pagename'", 
                             $dbi['dbc'])) {
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

      if (!mysql_query("DELETE FROM $WikiPageStore WHERE pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiPageStore, mysql_error()));

      if (!mysql_query("DELETE FROM $ArchivePageStore WHERE pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $ArchivePageStore, mysql_error()));

      if (!mysql_query("DELETE FROM $WikiLinksStore WHERE frompage='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiLinksStore, mysql_error()));

      if (!mysql_query("DELETE FROM $HitCountStore WHERE pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $HitCountStore, mysql_error()));

      if (!mysql_query("DELETE FROM $WikiScoreStore WHERE pagename='$pagename'", $dbi['dbc']))
         ExitWiki(sprintf($msg, $pagename, $WikiScoreStore, mysql_error()));
   }


   function IncreaseHitCount($dbi, $pagename)
   {
      global $HitCountStore;

      $qpagename = addslashes($pagename);
      $res = mysql_query("UPDATE $HitCountStore SET hits=hits+1"
                         . " WHERE pagename='$qpagename'",
                         $dbi['dbc']);

      if (!mysql_affected_rows($dbi['dbc'])) {
	 $res = mysql_query("INSERT INTO $HitCountStore (pagename, hits)"
                            . " VALUES ('$qpagename', 1)",
                            $dbi['dbc']);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename)
   {
      global $HitCountStore;

      $qpagename = addslashes($pagename);
      $res = mysql_query("SELECT hits FROM $HitCountStore"
                         . " WHERE pagename='$qpagename'",
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
	    $clause .= "NOT (LCASE($column) LIKE '%$word%') ";
	 } else {
	    $clause .= "(LCASE($column) LIKE '%$word%') ";
	 }
	 if ($term = strtok(' '))
	    $clause .= 'AND ';
      }
      
      return $clause;
   }

   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $clause = MakeSQLSearchClause($search, 'pagename');
      $res = mysql_query("SELECT pagename FROM ".$dbi['table']." WHERE $clause ORDER BY pagename", 
                         $dbi["dbc"]);
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
      $res = mysql_query("SELECT * FROM ".$dbi['table']." WHERE $clause", $dbi["dbc"]);

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
      $res = mysql_query("SELECT * FROM $HitCountStore ORDER BY hits desc, pagename LIMIT $limit", 
                         $dbi["dbc"]);
      
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
      $res = mysql_query("SELECT pagename FROM $WikiPageStore", $dbi["dbc"]);
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
      $res = mysql_query("SELECT topage, score FROM $WikiLinksStore, $WikiScoreStore"
                         ." WHERE topage=pagename AND frompage='$pagename'"
                         ." ORDER BY score DESC, topage");
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mysql_fetch_array($res);
	 $links['out'][] = array($out['topage'], $out['score']);
      }

      $res = mysql_query("SELECT frompage, score FROM $WikiLinksStore, $WikiScoreStore"
                         ." WHERE frompage=pagename AND topage='$pagename'"
                         ." ORDER BY score DESC, frompage");
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mysql_fetch_array($res);
	 $links['in'][] = array($out['frompage'], $out['score']);
      }

      $res = mysql_query("SELECT DISTINCT pagename, hits FROM $WikiLinksStore, $HitCountStore"
                         ." WHERE (frompage=pagename AND topage='$pagename')"
                         ." OR (topage=pagename and frompage='$pagename')"
                         ." ORDER BY hits DESC, pagename");
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
      mysql_query("DELETE FROM $WikiLinksStore WHERE frompage='$frompage'",
		$dbi["dbc"]);

      // the page may not have links, return if not
      if (! count($linklist))
         return;
      // now insert the new list of links
      while (list($topage, $count) = each($linklist)) {
         $topage = addslashes($topage);
	 if($topage != $frompage) {
            mysql_query("INSERT INTO $WikiLinksStore (frompage, topage)"
                        ." VALUES ('$frompage', '$topage')", $dbi["dbc"]);
	 }
      }

      // update pagescore
      mysql_query("DELETE FROM $WikiScoreStore", $dbi["dbc"]);
      mysql_query("INSERT INTO $WikiScoreStore"
                  ." SELECT w1.topage, COUNT(*) FROM $WikiLinksStore AS w1, $WikiLinksStore AS w2"
                  ." WHERE w2.topage=w1.frompage GROUP BY w1.topage", $dbi["dbc"]);
   }

/* more mysql queries:

orphans:
select pagename from wiki left join wikilinks on pagename=topage where topage is NULL;
*/
?>