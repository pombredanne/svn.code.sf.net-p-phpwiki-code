<!-- $Id: wiki_mysql.php3,v 1.7 2000-06-26 21:26:45 ahollosi Exp $ -->
<?

   /*
      Database functions:

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
      global $mysql_server, $mysql_user, $mysql_pwd, $mysql_db;

      if (!($dbc = mysql_pconnect($mysql_server, $mysql_user, $mysql_pwd))) {
         echo "Cannot establish connection to database, giving up.";
         exit();
      }
      if (!mysql_select_db($mysql_db, $dbc)) {
         echo "Cannot open database, giving up.";
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
   function RetrievePage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      if ($res = mysql_query("select * from $dbi[table] where pagename='$pagename'", $dbi['dbc'])) {
         if ($dbhash = mysql_fetch_array($res)) {
            return MakePageHash($dbhash);
         }
      }
      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash)
   {
      $pagehash = MakeDBHash($pagename, $pagehash);

      $COLUMNS = "author, content, created, flags, " .
                 "lastmodified, pagename, refs, version";

      $VALUES =  "'$pagehash[author]', '$pagehash[content]', " .
                 "$pagehash[created], $pagehash[flags], " .
                 "$pagehash[lastmodified], '$pagehash[pagename]', " .
                 "'$pagehash[refs]', $pagehash[version]";


      if (!mysql_query("replace into $dbi[table] ($COLUMNS) values ($VALUES)",
      			$dbi['dbc'])) {
            echo "error writing page '$pagename'";
            exit();
      }
   }


   function IsWikiPage($dbi, $pagename) {
      $pagename = addslashes($pagename);
      if ($res = mysql_query("select count(*) from $dbi[table] where pagename='$pagename'", $dbi['dbc'])) {
         return(mysql_result($res, 0));
      }
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
      $res = mysql_query("select * from hitcount order by hits desc, pagename limit $limit");
      
      return $res;
   }

   function MostPopularNextMatch($dbi, $res) {
      if ($hits = mysql_fetch_array($res))
	 return $hits;
      else
         return 0;
   }
?>
