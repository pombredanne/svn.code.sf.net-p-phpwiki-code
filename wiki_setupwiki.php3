<? rcs_id('$Id: wiki_setupwiki.php3,v 1.11.2.1 2000-07-29 00:36:45 dairiki Exp $');
require 'wiki_ziplib.php3';

function SavePage ($dbi, $page, $source)
{
  $version = $dbi->insertPage($page);

  if ($version != $page->version())
      $version .= "was " . $page->version();
  // FIXME: templatize?
  printf("Inserted page <b>%s</b>, version %s from %s<br>\n",
	 htmlspecialchars($page->name()), $version, $source);
  flush();
}
      
function LoadFile ($dbi, $filename, $text, $mtime)
{
  set_time_limit(30);	// Reset watchdog.
  
  if (!($parts = ParseMimeifiedPages($text)))
    {
      // Can't parse MIME: assume plain text file.
      $pagename = rawurldecode($filename);
      $page = new WikiPage($pagename, array('content' => $text,
					    'version' => 1,
					    'created' => $mtime,
					    'lastmodified' => $mtime));
      SavePage($dbi, $page, "text file");
    }
  else
    {
      for (reset($parts); $page = current($parts); next($parts))
	{
	  // FIXME: templatize?
	  if ($page->name() != rawurldecode($filename))
	      printf("<b>Warning:</b> "
		     . "pagename (%s) doesn't match filename (%s)"
		     . " (using pagename)<br>\n",
		     htmlspecialchars($page->name()),
		     htmlspecialchars(rawurldecode($filename)));

	  SavePage($dbi, $page, "MIME file");
	}
    }
}

function LoadZipOrDir ($dbi, $zip_or_dir)
{
  $type = filetype($zip_or_dir);
  
  if ($type == 'file')
    {
      $zip = new ZipReader($zip_or_dir);
      while (list ($fn, $data, $attrib) = $zip->readFile())
	  LoadFile($dbi, $fn, $data, time()); // FIXME:should really get mtime from zip
    }
  else if ($type == 'dir')
    {
      $handle = opendir($dir = $zip_or_dir);

      // load default pages
      while ($fn = readdir($handle))
	{
	  if (filetype("$dir/$fn") != 'file')
	      continue;
	  $stat = stat("$dir/$fn");
	  $mtime = $stat[9];
	  LoadFile($dbi, $fn, implode("", file("$dir/$fn")), $mtime);
	}
      closedir($handle); 
    }
}

LoadZipOrDir($dbi, WIKI_PGSRC);
?>
