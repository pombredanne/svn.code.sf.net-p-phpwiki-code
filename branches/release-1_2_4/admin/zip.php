<?php // $Id: zip.php,v 1.1.2.1.2.3 2005-01-07 14:02:27 rurban Exp $

function encode_pagename_for_wikizip ($pagename) {
  $enc = rawurlencode($pagename);
  // URL encode leading dot:
  $enc = preg_replace('/^\./', '%2e', $enc);
  return $enc;
}

function MailifyPage ($pagehash, $oldpagehash = false)
{
  global $SERVER_ADMIN, $ArchivePageStore;
  
  $from = isset($SERVER_ADMIN) ? $SERVER_ADMIN : 'foo@bar';
  
  $head = "From $from  " . ctime(time()) . "\r\n";
  $head .= "Subject: " . encode_pagename_for_wikizip($pagehash['pagename']) . "\r\n";
  $head .= "From: $from (PhpWiki)\r\n";
  $head .= "Date: " . rfc1123date($pagehash['lastmodified']) . "\r\n";
  $head .= "Mime-Version: 1.0 (Produced by PhpWiki 1.2.6)\r\n";

  if (is_array($oldpagehash))
    {
      return $head . MimeMultipart(array(MimeifyPage($oldpagehash),
				         MimeifyPage($pagehash)));
    }

  return $head . MimeifyPage($pagehash);
}

/**
 * The main() function which generates a zip archive of a PhpWiki.
 *
 * If $include_archive is false, only the current version of each page
 * is included in the zip file; otherwise all archived versions are
 * included as well.
 */
function MakeWikiZip ($include_archive = false)
{
  global $dbi, $WikiPageStore, $ArchivePageStore;
  
  $pages = GetAllWikiPageNames($dbi);
  $zipname = "wiki.zip";
  
  if ($include_archive) {
     $zipname = "wikidb.zip";
  }

  $zip = new ZipWriter("Created by PhpWiki", $zipname);

  for (reset($pages); $pagename = current($pages); next($pages))
  {
      
     set_time_limit(30);	// Reset watchdog.
     $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);

     if (! is_array($pagehash))
	continue;

     if ($include_archive)
	$oldpagehash = RetrievePage($dbi, $pagename, $ArchivePageStore);
     else
        $oldpagehash = false;

     $attrib = array('mtime' => $pagehash['lastmodified'],
		     'is_ascii' => 1);
     if (($pagehash['flags'] & FLAG_PAGE_LOCKED) != 0)
	  $attrib['write_protected'] = 1;

     $content = MailifyPage($pagehash, $oldpagehash);
		     
     $zip->addRegularFile( encode_pagename_for_wikizip($pagehash['pagename']),
			   $content, $attrib);
  }
  $zip->finish();
}


if(defined('WIKI_ADMIN'))
   MakeWikiZip(($zip == 'all'));

CloseDataBase($dbi);
exit;
?>