<?php
rcs_id('$Id: pgsql.php,v 1.8 2001-07-18 04:59:47 uckelman Exp $');

   /*
      Database functions:

      OpenDataBase($table)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename, $pagestore, $version)
      RetrievePageVersions($dbi, $pagename, $curstore, $archstore)
      GetMaxVersionNumber($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash, $clobber)
      SelectStore($dbi, $pagename, $version, $curstore, $archstore)
      IsVersionInWiki($dbi, $pagename, $version)
      IsVersionInArchive($dbi, $pagename, $version)
      IsWikiPage($dbi, $pagename)
      IsInArchive($dbi, $pagename)
      RemovePage($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, $res)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, $res)
      IncreaseHitCount($dbi, $pagename)
      GetHitCount($dbi, $pagename)
      InitMostPopular($dbi, $limit)
      MostPopularNextMatch($dbi, $res)
      GetAllWikiPageNames($dbi)
      GetWikiPageLinks($dbi, $pagename)
      SetWikiPageLinks($dbi, $pagename, $linklist)
   */

$WikiPageStore = $DBParams['prefix']      . "pages";
$ArchivePageStore = $DBParams['prefix']   . "archive";
$WikiLinksPageStore = $DBParams['prefix'] . "links";
$HotTopicsPageStore = $DBParams['prefix'] . "hottopics";
$HitCountPageStore = $DBParams['prefix']  . "hitcount";

   // open a database and return a hash

   function OpenDataBase($table) {
      extract($GLOBALS['DBParams']);
      
      $args = array();
      if (!empty($server))
	 $args[] = "host=$server";
      if (!empty($port))
	 $args[] = "port=$port";
      if (!empty($database))
	 $args[] = "dbname=$database";
      if (!empty($user))
	 $args[] = "user=$user";
      if (!empty($password))
	 $args[] = "password=$password";

      if (!($dbc = pg_pconnect(join(' ', $args)))) {
         ExitWiki("Cannot establish connection to database, giving up.");
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
   function RetrievePage($dbi, $pagename, $pagestore, $version) {
      $pagename = addslashes($pagename);
      $version = $version ? " and version=$version" : '';
      $query = "select * from $pagestore where pagename='$pagename'$version";
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


   // Return all versions of a page as an array of page hashes
   function RetrievePageVersions($dbi, $pagename, $curstore, $archstore) {
      $pagename = addslashes($pagename);
      if (($page[0] = RetrievePage($dbi, $pagename, $curstore, 0)) != -1) {
         $res = pg_exec($dbi['dbc'], "select * from $archstore where pagename='$pagename' order by version desc");
         if (pg_numrows($res)) {
            while ($array = pg_fetch_array($res, 0)) {
               while (list($key, $val) = each($array)) {
                  if (gettype($key) == "integer") {
                     continue;
                  }
                  $dbhash[$key] = $val;
               }

               $dbhash['refs'] = unserialize($dbhash['refs']);
               $dbhash['content'] = explode("\n", $dbhash['content']);

               array_push($page, $dbhash);
            }
         
            return $page;
         }
      }

      // if we reach this the query failed
      return -1;
   }


   // Get maximum version number of a page in pagestore
   function GetMaxVersionNumber($dbi, $pagename, $pagestore) {
      $pagename = addslashes($pagename);
      if ($res = pg_exec($dbi['dbc'], "select max(version) from $pagestore where pagename='$pagename'")) {
         return pg_result($res, 0, "version");
      }
      return -1;
   }

   
   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash, $clobber) {
      $pagename = addslashes($pagename);

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

      // Clobber existing page?
      $clobber = $clobber ? 'replace' : 'insert';

      $COLUMNS = "author, content, created, flags, " .
                 "lastmodified, pagename, refs, version";

      $VALUES =  "'$pagehash[author]', '$pagehash[content]', " .
                 "$pagehash[created], $pagehash[flags], " .
                 "$pagehash[lastmodified], '$pagehash[pagename]', " .
                 "'$pagehash[refs]', $pagehash[version]";

      if (!pg_exec($dbi['dbc'], "$clobber into $dbi[table] ($COLUMNS) values ($VALUES)")) {
         $msg = htmlspecialchars(sprintf(gettext("Error writing page '%s'"), $pagename));
         $msg .= "<BR>";
         $msg .= htmlspecialchars(sprintf(gettext("PostgreSQL error: %s"), pg_errormessage($dbi['dbc'])));
         ExitWiki($msg);
      }
   }

   
   // Adds a page to the archive pagestore
   function SavePageToArchive($pagename, $pagehash) {
      global $ArchivePageStore;
      $dbi = OpenDataBase($ArchivePageStore);
      InsertPage($dbi, $pagename, $pagehash, false);
   }   


   // Returns store where version of page resides
   function SelectStore($dbi, $pagename, $version, $curstore, $archstore) {
      if ($version) {
         if (IsVersionInWiki($dbi, $pagename, $version)) return $curstore;
         elseif (IsVersionInArchive($dbi, $pagename, $version)) return $archstore;
         else return -1;
      }
      elseif (IsWikiPage($dbi, $pagename)) return $curstore;
      else return -1;
   }


   function IsVersionInWiki($dbi, $pagename, $version) {
      $pagename = addslashes($pagename);
      if ($res = pg_exec($dbi['dbc'], "select count(*) from $dbi[table] where pagename='$pagename' and version='$version'")) {
         return pg_result($res, 0, "count");
      }
      return 0;
   }


   function IsVersionInArchive($dbi, $pagename, $version) {
      global $ArchivePageStore;

      $pagename = addslashes($pagename);
      if ($res = pg_exec($dbi['dbc'], "select count(*) from $ArchivePageStore where pagename='$pagename' and version='$version'")) {
         return pg_result($res, 0, "count");
      }
      return 0;
   }


   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      if ($res = pg_exec($dbi['dbc'], "select count(*) from $dbi[table] where pagename='$pagename'")) {
         return pg_result($res, 0, "count");
      }
      return 0;
   }


   function IsInArchive($dbi, $pagename) {
      global $ArchivePageStore;

      $pagename = addslashes($pagename);
      if ($res = pg_exec($dbi['dbc'], "select count(*) from $ArchivePageStore where pagename='$pagename'")) {
         return pg_result($res, 0, "count");
      }
      return 0;
   }


   function RemovePage($dbi, $pagename) {
      global $WikiPageStore, $ArchivePageStore;
      global $WikiLinksStore, $HitCountStore, $WikiScoreStore;

      $pagename = addslashes($pagename);
      $msg = gettext ("Cannot delete '%s' from table '%s'");
      $msg .= "<br>\n";
      $msg .= gettext ("PostgreSQL error: %s");

      if (!pg_exec($dbi['dbc'], "delete from $WikiPageStore where pagename='$pagename'"))
         ExitWiki(sprintf($msg, $pagename, $WikiPageStore, pg_errormessage()));

      if (!pg_exec($dbi['dbc'], "delete from $ArchivePageStore where pagename='$pagename'"))
         ExitWiki(sprintf($msg, $pagename, $ArchivePageStore, pg_errormessage()));

      if (!pg_exec($dbi['dbc'], "delete from $WikiLinksStore where frompage='$pagename'"))
         ExitWiki(sprintf($msg, $pagename, $WikiLinksStore, pg_errormessage()));

      if (!pg_exec($dbi['dbc'], "delete from $HitCountStore where pagename='$pagename'"))
         ExitWiki(sprintf($msg, $pagename, $HitCountStore, pg_errormessage()));

      if (!pg_exec($dbi['dbc'], "delete from $WikiScoreStore where pagename='$pagename'"))
         ExitWiki(sprintf($msg, $pagename, $WikiScoreStore, mysql_error()));
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {

      global $search_counter;
      $search_counter = 0;

      $search = strtolower($search);
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
      $search = addslashes($search);
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


   function IncreaseHitCount($dbi, $pagename) {
      global $HitCountPageStore;
      $query = "update $HitCountPageStore set hits=hits+1 where pagename='$pagename'";
      $res = pg_exec($dbi['dbc'], $query);

      if (!pg_cmdtuples($res)) {
         $query = "insert into $HitCountPageStore (pagename, hits) " .
                  "values ('$pagename', 1)";
	 $res = pg_exec($dbi['dbc'], $query);
      }

      return $res;
   }

   function GetHitCount($dbi, $pagename) {
      global $HitCountPageStore;
      $query = "select hits from $HitCountPageStore where pagename='$pagename'";
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

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// tab-width: 4
// indent-tabs-mode: nil
// End:   
?>
