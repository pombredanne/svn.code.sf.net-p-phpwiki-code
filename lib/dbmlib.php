<?php  

   rcs_id('$Id: dbmlib.php,v 1.7.2.5 2005-01-07 13:59:58 rurban Exp $');

   /*
      Database functions:

      OpenDataBase($table)
      CloseDataBase($dbi)
      RetrievePage($dbi, $pagename, $pagestore)
      InsertPage($dbi, $pagename, $pagehash)
      SaveCopyToArchive($dbi, $pagename, $pagehash) 
      IsWikiPage($dbi, $pagename)
      InitTitleSearch($dbi, $search)
      TitleSearchNextMatch($dbi, $res)
      InitFullSearch($dbi, $search)
      FullSearchNextMatch($dbi, $res)
      MakeBackLinkSearchRegexp($pagename)
      InitBackLinkSearch($dbi, $pagename) 
      BackLinkSearchNextMatch($dbi, &$pos) 
      IncreaseHitCount($dbi, $pagename)
      GetHitCount($dbi, $pagename)
      InitMostPopular($dbi, $limit)
      MostPopularNextMatch($dbi, $res)
   */


   // open a database and return the handle
   // loop until we get a handle; php has its own
   // locking mechanism, thank god.
   // Suppress ugly error message with @.

   function OpenDataBase($dbname) {
      global $WikiDB; // hash of all the DBM file names

      reset($WikiDB);
      while (list($key, $file) = each($WikiDB)) {
         while (($dbi[$key] = @dbmopen($file, "c")) < 1) {
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
         dbmclose($dbihandle);
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
      if ($data = dbmfetch($dbi[$pagestore], $pagename)) {
         // unserialize $data into a hash
         $pagehash = unserialize(UnPadSerializedData($data));
         $pagehash['pagename'] = $pagename;
         return $pagehash;
      } else {
         return -1;
      }
   }


   // Either insert or replace a key/value (a page)
   function InsertPage($dbi, $pagename, $pagehash, $pagestore='wiki') {

      if ($pagestore == 'wiki') {       // a bit of a hack
         $linklist = ExtractWikiPageLinks($pagehash['content']);
         SetWikiPageLinks($dbi, $pagename, $linklist);
      }

      $pagedata = PadSerializedData(serialize($pagehash));

      if (dbminsert($dbi[$pagestore], $pagename, $pagedata)) {
         if (dbmreplace($dbi[$pagestore], $pagename, $pagedata)) {
            ExitWiki("Error inserting page '$pagename'");
         }
      } 
   }


   // for archiving pages to a separate dbm
   function SaveCopyToArchive($dbi, $pagename, $pagehash) {
      global $ArchivePageStore;

      $pagedata = PadSerializedData(serialize($pagehash));

      if (dbminsert($dbi[$ArchivePageStore], $pagename, $pagedata)) {
         if (dbmreplace($dbi['archive'], $pagename, $pagedata)) {
            ExitWiki("Error storing '$pagename' into archive");
         }
      } 
   }


   function IsWikiPage($dbi, $pagename) {
      return dbmexists($dbi['wiki'], $pagename);
   }


   function IsInArchive($dbi, $pagename) {
      return dbmexists($dbi['archive'], $pagename);
   }


   function RemovePage($dbi, $pagename) {

      dbmdelete($dbi['wiki'], $pagename);	// report error if this fails? 
      dbmdelete($dbi['archive'], $pagename);	// no error if this fails
      dbmdelete($dbi['hitcount'], $pagename);	// no error if this fails

      $linkinfo = RetrievePage($dbi, $pagename, 'wikilinks');
      
      // remove page from fromlinks of pages it had links to
      if (is_array($linkinfo)) {	// page exists?
	 $tolinks = $linkinfo['tolinks'];	
	 reset($tolinks);			
	 while (list($tolink, $dummy) = each($tolinks)) {
	    $tolinkinfo = RetrievePage($dbi, $tolink, 'wikilinks');
	    if (is_array($tolinkinfo)) {		// page found?
	       $oldFromlinks = $tolinkinfo['fromlinks'];
	       $tolinkinfo['fromlinks'] = array(); 	// erase fromlinks
	       reset($oldFromlinks);
	       while (list($fromlink, $dummy) = each($oldFromlinks)) {
		  if ($fromlink != $pagename)		// not to be erased? 
		     $tolinkinfo['fromlinks'][$fromlink] = 1; // put link back
	       }			// put link info back in DBM file
	       InsertPage($dbi, $tolink, $tolinkinfo, 'wikilinks');
	    }
	 }

	 // remove page itself     
	 dbmdelete($dbi['wikilinks'], $pagename);      
      }
   }


   // setup for title-search
   function InitTitleSearch($dbi, $search) {
      $pos['search'] = '=' . preg_quote($search) . '=i';
      $pos['key'] = dbmfirstkey($dbi['wiki']);

      return $pos;
   }


   // iterating through database
   function TitleSearchNextMatch($dbi, &$pos) {
      while ($pos['key']) {
         $page = $pos['key'];
         $pos['key'] = dbmnextkey($dbi['wiki'], $pos['key']);

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
         $pos['key'] = dbmnextkey($dbi['wiki'], $pos['key']);

         $pagedata = dbmfetch($dbi['wiki'], $key);
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
      $pos['search'] = MakeBackLinkSearchRegexp($pagename);
      $pos['key'] = dbmfirstkey($dbi['wiki']);

      return $pos;
   }

   // iterating through back-links
   function BackLinkSearchNextMatch($dbi, &$pos) {
      while ($pos['key']) {
         $page = $pos['key'];
         $pos['key'] = dbmnextkey($dbi['wiki'], $pos['key']);

         $rawdata = dbmfetch($dbi['wiki'], $page);
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

      if (dbmexists($dbi['hitcount'], $pagename)) {
         // increase the hit count
         // echo "$pagename there, incrementing...<br>\n";
         $count = dbmfetch($dbi['hitcount'], $pagename);
         $count++;
         dbmreplace($dbi['hitcount'], $pagename, $count);
      } else {
         // add it, set the hit count to one
         $count = 1;
         dbminsert($dbi['hitcount'], $pagename, $count);
      }
   }


   function GetHitCount($dbi, $pagename) {

      if (dbmexists($dbi['hitcount'], $pagename)) {
         // increase the hit count
         $count = dbmfetch($dbi['hitcount'], $pagename);
         return $count;
      } else {
         return 0;
      }
   }


   function InitMostPopular($dbi, $limit) {
      // iterate through the whole dbm file for hit counts
      // sort the results highest to lowest, and return 
      // n..$limit results

      // Because sorting all the pages may be a lot of work
      // we only get the top $limit. A page is only added if it's score is
      // higher than the lowest score in the list. If the list is full then
      // one of the pages with the lowest scores is removed.

      $pagename = dbmfirstkey($dbi['hitcount']);
      $score = dbmfetch($dbi['hitcount'], $pagename);
      $res = array($pagename => (int) $score);
      $lowest = $score;

      while ($pagename = dbmnextkey($dbi['hitcount'], $pagename)) {
	 $score = dbmfetch($dbi['hitcount'], $pagename);      
         if (count($res) < $limit) {	// room left in $res?
	    if ($score < $lowest)
	       $lowest = $score;
	    $res[$pagename] = (int) $score;	// add page to $res
	 } elseif ($score > $lowest) {
	    $oldres = $res;		// save old result
	    $res = array();
	    $removed = 0;		// nothing removed yet
	    $newlowest = $score;	// new lowest score
	    $res[$pagename] = (int) $score;	// add page to $res	    
	    reset($oldres);
	    while(list($pname, $pscore) = each($oldres)) {
	       if (!$removed and ($pscore = $lowest))
	          $removed = 1;		// don't copy this entry
	       else {
	          $res[$pname] = (int) $pscore;
		  if ($pscore < $newlowest)
		     $newlowest = $pscore;
	       }
	    }
	    $lowest = $newlowest;
	 }
      }
       
      arsort($res);		// sort
      reset($res);
       
      return($res);
   }


   function MostPopularNextMatch($dbi, &$res) {

      // the return result is a two element array with 'hits'
      // and 'pagename' as the keys

      if (list($pagename, $hits) = each($res)) {
         $nextpage = array(
            "hits" => $hits,
            "pagename" => $pagename
         );
         return $nextpage;
      } else {
         return 0;
      }
   } 


   function GetAllWikiPagenames($dbi) {
      $namelist = array();
      $ctr = 0;

      $namelist[$ctr] = $key = dbmfirstkey($dbi['wiki']);

      while ($key = dbmnextkey($dbi['wiki'], $key)) {
         $ctr++;
         $namelist[$ctr] = $key;
      }

      return $namelist;
   }


   ////////////////////////////////////////////
   // functionality for the wikilinks DBM file

   // format of the 'wikilinks' DBM file :
   // pagename =>
   //    { tolinks => ( pagename => 1}, fromlinks => { pagename => 1 } }

   // takes a page name, returns array of scored incoming and outgoing links
   function GetWikiPageLinks($dbi, $pagename) {

      $linkinfo = RetrievePage($dbi, $pagename, 'wikilinks');
      if (is_array($linkinfo))	{		// page exists?
         $tolinks = $linkinfo['tolinks'];	// outgoing links
         $fromlinks = $linkinfo['fromlinks'];	// incoming links
      } else {		// new page, but pages may already point to it
      	 // create info for page
         $tolinks = array();
	 $fromlinks = array();
         // look up pages that link to $pagename
	 $pname = dbmfirstkey($dbi['wikilinks']);
	 while ($pname) {
	    $linkinfo = RetrievePage($dbi, $pname, 'wikilinks');
	    if ($linkinfo['tolinks'][$pagename]) // $pname links to $pagename?
	       $fromlinks[$pname] = 1;
	    $pname = dbmnextkey($dbi['wikilinks'], $pname);
	 }
      }

      // get and sort the outgoing links
      $outlinks = array();      
      reset($tolinks);			// look up scores for tolinks
      while(list($tolink, $dummy) = each($tolinks)) {
         $toPage = RetrievePage($dbi, $tolink, 'wikilinks');
	 if (is_array($toPage))		// link to internal page?
	    $outlinks[$tolink] = count($toPage['fromlinks']);
      }
      arsort($outlinks);		// sort on score
      $links['out'] = array();
      reset($outlinks);			// convert to right format
      while(list($link, $score) = each($outlinks))
         $links['out'][] = array($link, $score);

      // get and sort the incoming links
      $inlinks = array();
      reset($fromlinks);		// look up scores for fromlinks
      while(list($fromlink, $dummy) = each($fromlinks)) {
         $fromPage = RetrievePage($dbi, $fromlink, 'wikilinks');
	 $inlinks[$fromlink] = count($fromPage['fromlinks']);
      }	
      arsort($inlinks);			// sort on score
      $links['in'] = array();
      reset($inlinks);			// convert to right format
      while(list($link, $score) = each($inlinks))
         $links['in'][] = array($link, $score);

      // sort all the incoming and outgoing links
      $allLinks = $outlinks;		// copy the outlinks
      reset($inlinks);			// add the inlinks
      while(list($key, $value) = each($inlinks))
         $allLinks[$key] = $value;
      reset($allLinks);			// lookup hits
      while(list($key, $value) = each($allLinks))
         $allLinks[$key] = (int) dbmfetch($dbi['hitcount'], $key);
      arsort($allLinks);		// sort on hits
      $links['popular'] = array();
      reset($allLinks);			// convert to right format
      while(list($link, $hits) = each($allLinks))
         $links['popular'][] = array($link, $hits);

      return $links;
   }


   // takes page name, list of links it contains
   // the $linklist is an array where the keys are the page names
   function SetWikiPageLinks($dbi, $pagename, $linklist) {

      $cache = array();

      // Phase 1: fetch the relevant pairs from 'wikilinks' into $cache
      // ---------------------------------------------------------------

      // first the info for $pagename
      $linkinfo = RetrievePage($dbi, $pagename, 'wikilinks');
      if (is_array($linkinfo))		// page exists?
         $cache[$pagename] = $linkinfo;
      else {
      	 // create info for page
         $cache[$pagename] = array( 'fromlinks' => array(),
				    'tolinks' => array()
			     );
         // look up pages that link to $pagename
	 $pname = dbmfirstkey($dbi['wikilinks']);
	 while ($pname) {
	    $linkinfo = RetrievePage($dbi, $pname, 'wikilinks');
	    if ($linkinfo['tolinks'][$pagename])
	       $cache[$pagename]['fromlinks'][$pname] = 1;
	    $pname = dbmnextkey($dbi['wikilinks'], $pname);
	 }
      }
			     
      // then the info for the pages that $pagename used to point to 
      $oldTolinks = $cache[$pagename]['tolinks'];
      reset($oldTolinks);
      while (list($link, $dummy) = each($oldTolinks)) {
         $linkinfo = RetrievePage($dbi, $link, 'wikilinks');
         if (is_array($linkinfo))
	    $cache[$link] = $linkinfo;
      }

      // finally the info for the pages that $pagename will point to
      reset($linklist);
      while (list($link, $dummy) = each($linklist)) {
         $linkinfo = RetrievePage($dbi, $link, 'wikilinks');
         if (is_array($linkinfo))
	    $cache[$link] = $linkinfo;
      }
	      
      // Phase 2: delete the old links
      // ---------------------------------------------------------------

      // delete the old tolinks for $pagename
      // $cache[$pagename]['tolinks'] = array();
      // (overwritten anyway in Phase 3)

      // remove $pagename from the fromlinks of pages in $oldTolinks

      reset($oldTolinks);
      while (list($oldTolink, $dummy) = each($oldTolinks)) {
         if ($cache[$oldTolink]) {	// links to existing page?
	    $oldFromlinks = $cache[$oldTolink]['fromlinks'];
	    $cache[$oldTolink]['fromlinks'] = array(); 	// erase fromlinks
	    reset($oldFromlinks);			// comp. new fr.links
	    while (list($fromlink, $dummy) = each($oldFromlinks)) {
	       if ($fromlink != $pagename)
		  $cache[$oldTolink]['fromlinks'][$fromlink] = 1;
	    }
	 }
      }

      // Phase 3: add the new links
      // ---------------------------------------------------------------

      // set the new tolinks for $pagename
      $cache[$pagename]['tolinks'] = $linklist;

      // add $pagename to the fromlinks of pages in $linklist
      reset($linklist);
      while (list($link, $dummy) = each($linklist)) {
         if ($cache[$link])	// existing page?
            $cache[$link]['fromlinks'][$pagename] = 1;
      }

      // Phase 4: write $cache back to 'wikilinks'
      // ---------------------------------------------------------------

      reset($cache);
      while (list($link,$fromAndTolinks) = each($cache))
	 InsertPage($dbi, $link, $fromAndTolinks, 'wikilinks');

   }

?>