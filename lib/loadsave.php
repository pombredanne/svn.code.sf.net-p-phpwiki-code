<?php
rcs_id('$Id: loadsave.php,v 1.3 2001-02-14 05:22:49 dairiki Exp $');

require "lib/ziplib.php";

function StartLoadDump($title, $html = '')
{
   // FIXME: This is a hack
   echo ereg_replace('</body>.*', '',
		     GeneratePage('MESSAGE', $html, $title, 0));
}
function EndLoadDump()
{
   // FIXME: This is a hack
   echo Element('p', QElement('b', gettext("Complete.")));
   echo Element('p', "Return to " . LinkExistingWikiWord($GLOBALS['pagename']));
   echo "</body></html>\n";
}

   
////////////////////////////////////////////////////////////////
//
//  Functions for dumping.
//
////////////////////////////////////////////////////////////////

function MailifyPage ($pagehash, $oldpagehash = false)
{
   global $SERVER_ADMIN, $ArchivePageStore;
  
   $from = isset($SERVER_ADMIN) ? $SERVER_ADMIN : 'foo@bar';
  
   $head = "From $from  " . ctime(time()) . "\r\n";
   $head .= "Subject: " . rawurlencode($pagehash['pagename']) . "\r\n";
   $head .= "From: $from (PhpWiki)\r\n";
   $head .= "Date: " . rfc1123date($pagehash['lastmodified']) . "\r\n";
   $head .= sprintf("Mime-Version: 1.0 (Produced by PhpWiki %s)\r\n", PHPWIKI_VERSION);

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
function MakeWikiZip ($dbi, $include_archive = false)
{
   global $WikiPageStore, $ArchivePageStore;
  
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
		     
      $zip->addRegularFile( rawurlencode($pagehash['pagename']),
			    $content, $attrib);
   }
   $zip->finish();
}

function DumpToDir ($dbi, $directory) 
{
   global $WikiPageStore;

   if (empty($directory))
      ExitWiki(gettext("You must specify a directory to dump to"));
   
   // see if we can access the directory the user wants us to use
   if (! file_exists($directory)) {
      if (! mkdir($directory, 0755))
         ExitWiki("Cannot create directory '$directory'<br>\n");
      else
         $html = "Created directory '$directory' for the page dump...<br>\n";
   } else {
      $html = "Using directory '$directory'<br>\n";
   }

   StartLoadDump("Dumping Pages", $html);
   
   $pages = GetAllWikiPagenames($dbi);

   while (list ($i, $pagename) = each($pages))
   {
      $enc_name = htmlspecialchars($pagename);
      $filename = rawurlencode($pagename);

      echo "<br>$enc_name ... ";
      if($pagename != $filename)
         echo "<small>saved as $filename</small> ... ";

      $page = RetrievePage($dbi, $pagename, $WikiPageStore);

      //$data = serialize($page);
      $data = MailifyPage($page);
      
      if ( !($fd = fopen("$directory/$filename", "w")) )
         ExitWiki("<b>couldn't open file '$directory/$filename' for writing</b>\n");
      
      $num = fwrite($fd, $data, strlen($data));
      echo "<small>$num bytes written</small>\n";
      flush();
      
      assert($num == strlen($data));
      fclose($fd);
   }

   EndLoadDump();
}

////////////////////////////////////////////////////////////////
//
//  Functions for restoring.
//
////////////////////////////////////////////////////////////////

function SavePage ($dbi, $page, $defaults, $source, $filename)
{
   global $WikiPageStore;

   // Fill in defaults for missing values?
   // Should we do more sanity checks here?
   while (list($key, $val) = each($defaults))
      if (empty($page[$key]))
	 $page[$key] = $val;

   $pagename = $page['pagename'];

   if (empty($pagename))
   {
      echo Element('dd'). Element('dt', QElement('b', "Empty pagename!"));
      return;
   }
   
   
   $mesg = array();
   $version = $page['version'];
   $isnew = true;
   
   if ($version)
      $mesg[] = sprintf(gettext("version %s"), $version);
   if ($source)
      $mesg[] = sprintf(gettext("from %s"), $source);
  
   if (is_array($current = RetrievePage($dbi, $pagename, $WikiPageStore)))
   {
      $isnew = false;
      
      if (arrays_equal($current['content'], $page['content'])
	  && $current['author'] == $page['author']
	  && $current['flags'] == $page['flags'])
      {
	 $mesg[] = sprintf(gettext("is identical to current version %d"),
			   $current['version']);

	 if ( $version <= $current['version'] )
	 {
	    $mesg[] = gettext("- skipped");
	    $page = false;
	 }
      }
      else
      {
	 SaveCopyToArchive($dbi, $pagename, $current);

	 if ( $version <= $current['version'] )
	    $page['version'] = $current['version'] + 1;
      }
   }
   else if ($page['version'] < 1)
      $page['version'] = 1;
   

   if ($page)
   {
      InsertPage($dbi, $pagename, $page);
      UpdateRecentChanges($dbi, $pagename, $isnew);
      
      $mesg[] = gettext("- saved");
      if ($version != $page['version'])
	 $mesg[] = sprintf(gettext("as version %d"), $page['version']);
   }
   
   print( Element('dt', LinkExistingWikiWord($pagename))
	  . QElement('dd', join(" ", $mesg))
	  . "\n" );
   flush();
}

function ParseSerializedPage($text)
{
   if (!preg_match('/^a:\d+:{[si]:\d+/', $text))
      return false;
   return unserialize($text);
}
 
function SortByPageVersion ($a, $b) {
   return $a['version'] - $b['version'];
}

function LoadFile ($dbi, $filename, $text = false, $mtime = false)
{
   if (!is_string($text))
   {
      // Read the file.
      $stat = stat($filename);
      $mtime = $stat[9];
      $text = implode("", file($filename));
   }
   
   set_time_limit(30);	// Reset watchdog.

   // FIXME: basename("filewithnoslashes") seems to return garbage sometimes.
   $basename = basename("/dummy/" . $filename);
   
   if (!$mtime)
      $mtime = time();	// Last resort.

   $defaults = array('author' => $GLOBALS['user']->id(),
		     'pagename' => rawurldecode($basename),
		     'flags' => 0,
		     'version' => 0,
		     'created' => $mtime,
		     'lastmodified' => $mtime);

   if ( ($parts = ParseMimeifiedPages($text)) )
   {
      usort($parts, 'SortByPageVersion');
      for (reset($parts); $page = current($parts); next($parts))
	 SavePage($dbi, $page, $defaults, "MIME file $filename", $basename);
   }
   else if ( ($page = ParseSerializedPage($text)) )
   {
      SavePage($dbi, $page, $defaults, "Serialized file $filename", $basename);
   }
   else
   {
      // Assume plain text file.
      $page['content'] = preg_split('/[ \t\r]*\n/', chop($text));
      SavePage($dbi, $page, $defaults, "plain file $filename", $basename);
   }
}

function LoadZip ($dbi, $zipfile, $files = false, $exclude = false)
{
   $zip = new ZipReader($zipfile);
   while (list ($fn, $data, $attrib) = $zip->readFile())
   {
      // FIXME: basename("filewithnoslashes") seems to return garbage sometimes.
      $fn = basename("/dummy/" . $fn);
      if ( ($files && !in_array($fn, $files))
	   || ($exclude && in_array($fn, $exclude)) )
      {
	 print Element('dt', LinkExistingWikiWord($fn)) . QElement('dd', 'Skipping');
	 continue;
      }

      LoadFile($dbi, $fn, $data, $attrib['mtime']);
   }
}

function LoadDir ($dbi, $dirname, $files = false, $exclude = false)
{
   $handle = opendir($dir = $dirname);
   while ($fn = readdir($handle))
   {
      if (filetype("$dir/$fn") != 'file')
	 continue;

      if ( ($files && !in_array($fn, $files))
	   || ($exclude && in_array($fn, $exclude)) )
      {
	 print Element('dt', LinkExistingWikiWord($fn)) . QElement('dd', 'Skipping');
	 continue;
      }
      
      LoadFile($dbi, "$dir/$fn");
   }
   closedir($handle);
}

function IsZipFile ($filename_or_fd)
{
   // See if it looks like zip file
   if (is_string($filename_or_fd))
   {
      $fd = fopen($filename_or_fd, "rb");
      $magic = fread($fd, 4);
      fclose($fd);
   }
   else
   {
      $fpos = ftell($filename_or_fd);
      $magic = fread($filename_or_fd, 4);
      fseek($filename_or_fd, $fpos);
   }
   
   return $magic == ZIP_LOCHEAD_MAGIC || $magic == ZIP_CENTHEAD_MAGIC;
}

   
function LoadAny ($dbi, $file_or_dir, $files = false, $exclude = false)
{
   $type = filetype($file_or_dir);

   if ($type == 'dir')
   {
      LoadDir($dbi, $file_or_dir, $files, $exclude);
   }
   else if ($type != 'file' && !preg_match('/^(http|ftp):/', $file_or_dir))
   {
      ExitWiki("Bad file type: $type");
   }
   else if (IsZipFile($file_or_dir))
   {
      LoadZip($dbi, $file_or_dir, $files, $exclude);
   }
   else /* if (!$files || in_array(basename($file_or_dir), $files)) */
   {
      LoadFile($dbi, $file_or_dir);
   }
}

function LoadFileOrDir ($dbi, $source)
{
   StartLoadDump("Loading '$source'");
   echo "<dl>\n";
   LoadAny($dbi, $source, false, array(gettext('RecentChanges')));
   echo "</dl>\n";
   EndLoadDump();
}

function SetupWiki ($dbi)
{
   global $GenericPages, $LANG, $user;

   //FIXME: This is a hack
   $user->userid = 'The PhpWiki programming team';
   
   StartLoadDump('Loading up virgin wiki');
   echo "<dl>\n";

   $ignore = array(gettext('RecentChanges'));

   LoadAny($dbi, SearchPath(WIKI_PGSRC), false, $ignore);
   if ($LANG != "C")
      LoadAny($dbi, SearchPath(DEFAULT_WIKI_PGSRC), $GenericPages, $ignore);

   echo "</dl>\n";
   EndLoadDump();
}

function LoadPostFile ($dbi, $postname)
{
   global $HTTP_POST_FILES;

   extract($HTTP_POST_FILES[$postname]);
   fix_magic_quotes_gpc($tmp_name);
   fix_magic_quotes_gpc($name);

   if (!is_uploaded_file($tmp_name))
      ExitWiki('Bad file post');	// Possible malicious attack.
   
   // Dump http headers.
   $fd = fopen($tmp_name, "rb");
   while ( ($header = fgets($fd, 4096)) )
      if (trim($header) == '')
	 break;

   StartLoadDump("Uploading $name");
   echo "<dl>\n";
   
   if (IsZipFile($fd))
      LoadZip($dbi, $fd, false, array(gettext('RecentChanges')));
   else
      Loadfile($dbi, $name, fread($fd, MAX_UPLOAD_SIZE));

   echo "</dl>\n";
   EndLoadDump();
}

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
