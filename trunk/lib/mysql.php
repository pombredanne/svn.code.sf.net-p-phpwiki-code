<?php rcs_id('$Id: mysql.php,v 1.1 2000-10-08 17:33:26 wainstead Exp $');

   /*
      Database functions:

      OpenDataBase($dbname)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash)
      SaveCopyToArchive($dbi, $pagename, $pagehash) 
      IsWikiPage($dbi, $pagename)
      IsInArchive($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, &$pos)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, &$pos)
      IncreaseHitCount($dbi, $pagename)  
      GetHitCount($dbi, $pagename)   
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
         echo "Cannot establish connection to database, giving up.";
	 echo "MySQL error: ", mysql_error(), "<br>\n";
         exit();
      }
      if (!mysql_select_db($mysql_db, $dbc)) {
         echo "Cannot open database, giving up.";
	 echo "MySQL error: ", mysql_error(), "<br>\n";
         exit();
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


   function MakeDBHash($pagename, $pagehash)
   {
      $pagehash["pagename"] = addslashes($pagename);
      if (!isset($pagehash["flags"]))
         $pagehash["flags"] = 0;
      $pagehash["author"] = addslashes($pagehash["author"]);
      $pagehash["content"] = implode("\n", $pagehash["content"]);
      $pagehash["content"] = addslashes($pagehash["content"]);
      $pagehash["refs"] = serialize($pagehash["refs"]);
 
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
            echo "error writing page '$pagename' ";
	    echo mysql_error();
            exit();
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


   function IncreaseHitCount($dbi, $pagename)
   {
      $res = mysql_query("update hitcount set hits=hits+1 where pagename='$pagename'", $dbi['dbc']);

      if (!mysql_affected_rows($dbi['dbc'])) {
	 $res = mysql_query("insert into hitcount (pagename, hits) values ('$pagename', 1)", $dbi['dbc']);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename)
   {
      $res = mysql_query("select hits from hitcount where pagename='$pagename'", $dbi['dbc']);
      if (mysql_num_rows($res))
         $hits = mysql_result($res, 0);
      else
         $hits = "0";

      return $hits;
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $search = addslashes($search);
      $res = mysql_query("select pagename from $dbi[table] where pagename like '%$search%' order by pagename", $dbi["dbc"]);

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
      $search = addslashes($search);
      $res = mysql_query("select * from $dbi[table] where content like '%$search%'", $dbi["dbc"]);

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

   function InitMostPopular($dbi, $limit) {
      $res = mysql_query("select * from hitcount order by hits desc, pagename limit $limit", $dbi["dbc"]);
      
      return $res;
   }

   function MostPopularNextMatch($dbi, $res) {
      if ($hits = mysql_fetch_array($res))
	 return $hits;
      else
         return 0;
   }

   function GetAllWikiPageNames($dbi) {
      $res = mysql_query("select pagename from wiki", $dbi["dbc"]);
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
      $pagename = addslashes($pagename);
      $res = mysql_query("select topage, score from wikilinks, wikiscore where topage=pagename and frompage='$pagename' order by score desc, topage");
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mysql_fetch_array($res);
	 $links['out'][] = array($out['topage'], $out['score']);
      }

      $res = mysql_query("select frompage, score from wikilinks, wikiscore where frompage=pagename and topage='$pagename' order by score desc, frompage");
      $rows = mysql_num_rows($res);
      for ($i = 0; $i < $rows; $i++) {
	 $out = mysql_fetch_array($res);
	 $links['in'][] = array($out['frompage'], $out['score']);
      }

      $res = mysql_query("select distinct pagename, hits from wikilinks, hitcount where (frompage=pagename and topage='$pagename') or (topage=pagename and frompage='$pagename') order by hits desc, pagename");
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
      $frompage = addslashes($pagename);

      // first delete the old list of links
      mysql_query("delete from wikilinks where frompage='$frompage'",
		$dbi["dbc"]);

      // the page may not have links, return if not
      if (! count($linklist))
         return;
      // now insert the new list of links
      while (list($topage, $count) = each($linklist)) {
         $topage = addslashes($topage);
	 if($topage != $frompage) {
            mysql_query("insert into wikilinks (frompage, topage) " .
                     "values ('$frompage', '$topage')", $dbi["dbc"]);
	 }
      }

      // update pagescore
      mysql_query("delete from wikiscore", $dbi["dbc"]);
      mysql_query("insert into wikiscore select w1.topage, count(*) from wikilinks as w1, wikilinks as w2 where w2.topage=w1.frompage group by w1.topage", $dbi["dbc"]);
   }

/* more mysql queries:

orphans:
select pagename from wiki left join wikilinks on pagename=topage where topage is NULL;
*/
?>
