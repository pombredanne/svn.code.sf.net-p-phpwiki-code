<?

   /*
      Database functions:
      OpenDataBase($dbname)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename)
      InsertPage($dbi, $pagename, $pagehash)
      UpdateRecentChanges($dbi, $pagename) 
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


   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename) {
      if ($res = mysql_query("select data from $dbi[table] where page='$pagename'", $dbi['dbc'])) {
         if ($o = mysql_fetch_object($res)) {
            // unserialize data into a hash
            $pagehash = unserialize($o->data);
            return $pagehash;
         }
      }

      return -1;
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {
      $pagedata = addslashes(serialize($pagehash));

      if (!mysql_query("replace into $dbi[table] (page, data) values ('$pagename', '$pagedata')", $dbi['dbc'])) {
            echo "error writing value";
            exit();
      }
   }



   function IsWikiPage($dbi, $pagename) {
      if ($res = mysql_query("select count(*) from $dbi[table] where page='$pagename'", $dbi['dbc'])) {
         return(mysql_result($res, 0));
      }
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $res = mysql_query("select page from $dbi[table] where page like '%$search%' order by page", $dbi["dbc"]);

      return $res;
   }


   // iterating through database
   function TitleSearchNextMatch($dbi, $res) {
      if($o = mysql_fetch_object($res)) {
         return $o->page;
      }
      else {
         return 0;
      }
   }


   // setup for full-text search
   function InitFullSearch($dbi, $search) {
      $res = mysql_query("select page,data from $dbi[table] where data like '%$search%'", $dbi["dbc"]);

      return $res;
   }

   // iterating through database
   function FullSearchNextMatch($dbi, $res) {
      if($o = mysql_fetch_object($res)) {
	 $page['name'] = $o->page;
	 $page['hash'] = unserialize($o->data);
         return $page;
      }
      else {
         return 0;
      }
   }


?>
