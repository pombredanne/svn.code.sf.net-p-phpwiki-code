<?php  

   rcs_id('$Id: dbalib.php,v 1.2.2.5 2001-11-07 23:19:16 dairiki Exp $');

   /*
      Database functions:

      OpenDataBase($dbname) 
      CloseDataBase($dbi) 
      PadSerializedData($data) 
      UnPadSerializedData($data) 
      RetrievePage($dbi, $pagename, $pagestore) 
      InsertPage($dbi, $pagename, $pagehash) 
      SaveCopyToArchive($dbi, $pagename, $pagehash) 
      IsWikiPage($dbi, $pagename) 
      IsInArchive($dbi, $pagename) 
      InitTitleSearch($dbi, $search) 
      TitleSearchNextMatch($dbi, &$pos) 
      InitFullSearch($dbi, $search) 
      FullSearchNextMatch($dbi, &$pos) 
      MakeBackLinkSearchRegexp($pagename)
      InitBackLinkSearch($dbi, $pagename) 
      BackLinkSearchNextMatch($dbi, &$pos) 
      IncreaseHitCount($dbi, $pagename) 
      GetHitCount($dbi, $pagename) 
      InitMostPopular($dbi, $limit) 
      MostPopularNextMatch($dbi, &$res) 
      GetAllWikiPagenames($dbi) 
   */


   // open a database and return the handle
   // loop until we get a handle; php has its own
   // locking mechanism, thank god.
   // Suppress ugly error message with @.

   function OpenDataBase($dbname) {
      global $WikiDB; // hash of all the DBM file names

      reset($WikiDB);
      while (list($key, $file) = each($WikiDB)) {
         while (($dbi[$key] = @dba_open($file, "c", DBM_FILE_TYPE)) < 1) {
            $numattempts++;
            if ($numattempts > MAX_DBM_ATTEMPTS) {
               ExitWiki("Cannot open database '$key' : '$file', giving up.");
            }
            sleep(1);
         }
      }
      return $dbi;
   }


   function CloseDataBase($dbi) {
      reset($dbi);
      while (list($dbmfile, $dbihandle) = each($dbi)) {
         dba_close($dbihandle);
      }
      return;
   }


   // take a serialized hash, return same padded out to
   // the next largest number bytes divisible by 500. This
   // is to save disk space in the long run, since DBM files
   // leak memory.
   function PadSerializedData($data) {
      // calculate the next largest number divisible by 500
      $nextincr = 500 * ceil(strlen($data) / 500);
      // pad with spaces
      $data = sprintf("%-${nextincr}s", $data);
      return $data;
   }

   // strip trailing whitespace from the serialized data 
   // structure.
   function UnPadSerializedData($data) {
      return chop($data);
   }



   // Return hash of page + attributes or default
   function RetrievePage($dbi, $pagename, $pagestore) {
      if ($data = dba_fetch($pagename, $dbi[$pagestore])) {
         // unserialize $data into a hash
         $pagehash = unserialize(UnPadSerializedData($data));
         $pagehash['pagename'] = $pagename;
         return $pagehash;
      } else {
         return -1;
      }
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash) {
      $pagedata = PadSerializedData(serialize($pagehash));

      if (!dba_insert($pagename, $pagedata, $dbi['wiki'])) {
         if (!dba_replace($pagename, $pagedata, $dbi['wiki'])) {
            ExitWiki("Error inserting page '$pagename'");
         }
      } 
   }


   // for archiving pages to a seperate dbm
   function SaveCopyToArchive($dbi, $pagename, $pagehash) {
      global $ArchivePageStore;

      $pagedata = PadSerializedData(serialize($pagehash));

      if (!dba_insert($pagename, $pagedata, $dbi[$ArchivePageStore])) {
         if (!dba_replace($pagename, $pagedata, $dbi['archive'])) {
            ExitWiki("Error storing '$pagename' into archive");
         }
      } 
   }


   function IsWikiPage($dbi, $pagename) {
      return dba_exists($pagename, $dbi['wiki']);
   }


   function IsInArchive($dbi, $pagename) {
      return dba_exists($pagename, $dbi['archive']);
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $pos['search'] = '=' . preg_quote($search) . '=i';
      $pos['key'] = dba_firstkey($dbi['wiki']);

      return $pos;
   }

   // iterating through database
   function TitleSearchNextMatch($dbi, &$pos) {
      while ($pos['key']) {
         $page = $pos['key'];
         $pos['key'] = dba_nextkey($dbi['wiki']);

         if (preg_match($pos['search'], $page)) {
            return $page;
         }
      }
      return 0;
   }

   // setup for full-text search
   function InitFullSearch($dbi, $search) {
      return InitTitleSearch($dbi, $search);
   }

   //iterating through database
   function FullSearchNextMatch($dbi, &$pos) {
      while ($pos['key']) {
         $key = $pos['key'];
         $pos['key'] = dba_nextkey($dbi['wiki']);

         $pagedata = dba_fetch($key, $dbi['wiki']);
         // test the serialized data
         if (preg_match($pos['search'], $pagedata)) {
	    $page['pagename'] = $key;
            $pagedata = unserialize(UnPadSerializedData($pagedata));
	    $page['content'] = $pagedata['content'];
	    return $page;
	 }
      }
      return 0;
   }

   ////////////////////////
   // new database features

   // Compute PCRE suitable for searching for links to the given page.
   function MakeBackLinkSearchRegexp($pagename) {
      global $WikiNameRegexp;
     
      $quoted_pagename = preg_quote($pagename, '/');
      if (preg_match("/^$WikiNameRegexp\$/", $pagename)) {
	 // FIXME: This may need modification for non-standard (non-english) $WikiNameRegexp.
	 return "/(?<![A-Za-z0-9!])$quoted_pagename(?![A-Za-z0-9])/";
      }
      else {
	 // Note from author: Sorry. :-/
	 return ( '/'
		  . '(?<!\[)\[(?!\[)' // Single, isolated '['
		  . '([^]|]*\|)?'     // Optional stuff followed by '|'
	          . '\s*'             // Optional space
		  . $quoted_pagename  // Pagename
		  . '\s*\]/' );	      // Optional space, followed by ']'
	 // FIXME: the above regexp is still not quite right.
	 // Consider the text: " [ [ test page ]".  This is a link to a page
	 // named '[ test page'.  The above regexp will recognize this
	 // as a link either to '[ test page' (good) or to 'test page' (wrong).
      } 
   }

   // setup for back-link search
   function InitBackLinkSearch($dbi, $pagename) {
      $pos['search'] = MakeBackLinkSearchRegexp($pagename);
      $pos['key'] = dba_firstkey($dbi['wiki']);
      
      return $pos;
   }

   // iterating through back-links
   function BackLinkSearchNextMatch($dbi, &$pos) {
      while ($pos['key']) {
         $page = $pos['key'];
         $pos['key'] = dba_nextkey($dbi['wiki']);

         $rawdata = dba_fetch($page, $dbi['wiki']);
	 if ( ! preg_match($pos['search'], $rawdata))
	     continue;
	 
	 $pagedata = unserialize(UnPadSerializedData($rawdata));
	 while (list($i, $line) = each($pagedata['content'])) {
	    if (preg_match($pos['search'], $line))
	       return $page;
	 }
      }
      return 0;
   }

   function IncreaseHitCount($dbi, $pagename) {

      if (dba_exists($pagename, $dbi['hitcount'])) {
         // increase the hit count
         // echo "$pagename there, incrementing...<br>\n";
         $count = dba_fetch($pagename, $dbi['hitcount']);
         $count++;
         dba_replace($pagename, $count, $dbi['hitcount']);
      } else {
         // add it, set the hit count to one
         // echo "adding $pagename to hitcount...<br>\n";
         $count = 1;
         dba_insert($pagename, $count, $dbi['hitcount']);
      }
   }

   function GetHitCount($dbi, $pagename) {

      if (dba_exists($pagename, $dbi['hitcount'])) {
         // increase the hit count
         $count = dba_fetch($pagename, $dbi['hitcount']);
         return $count;
      } else {
         return 0;
      }
   }

   function InitMostPopular($dbi, $limit) {
      // iterate through the whole dbm file for hit counts
      // sort the results highest to lowest, and return 
      // n..$limit results

      $pagename = dba_firstkey($dbi['hitcount']);
      $res[$pagename] = dba_fetch($pagename, $dbi['hitcount']);

      while ($pagename = dba_nextkey($dbi['hitcount'])) {
         $res[$pagename] = dba_fetch($pagename, $dbi['hitcount']);
         //echo "got $pagename with value " . $res[$pagename] . "<br>\n";
      }

      arsort($res);
      return($res);
   }

   function MostPopularNextMatch($dbi, &$res) {

      // the return result is a two element array with 'hits'
      // and 'pagename' as the keys

      if (count($res) == 0)
         return 0;

      if (list($pagename, $hits) = each($res)) {
         //echo "most popular next match called<br>\n";
         //echo "got $pagename, $hits back<br>\n";
         $nextpage = array(
            "hits" => $hits,
            "pagename" => $pagename
         );
         // $dbm_mostpopular_cntr++;
         return $nextpage;
      } else {
         return 0;
      }
   } 

   function GetAllWikiPagenames($dbi) {
      $namelist = array();
      $ctr = 0;

      $namelist[$ctr] = $key = dba_firstkey($dbi);

      while ($key = dba_nextkey($dbi)) {
         $ctr++;
         $namelist[$ctr] = $key;
      }

      return $namelist;
   }

?>
