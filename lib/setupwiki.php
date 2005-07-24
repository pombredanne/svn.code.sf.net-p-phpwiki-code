<!-- $Id: setupwiki.php,v 1.3.2.4 2005-01-07 14:23:05 rurban Exp $ -->
<?php
require "lib/ziplib.php";

function SavePage ($dbi, $page, $source)
{
  global $WikiPageStore;
  $pagename = $page['pagename'];
  $version = $page['version'];
  
  if (is_array($current = RetrievePage($dbi, $pagename, $WikiPageStore)))
    {
      if ($version <= $current['version'])
	{
	  $page['version'] = $current['version'] + 1;
	  $version = $page['version'] . " [was $version]";
	}
      SaveCopyToArchive($dbi, $pagename, $current);
    }

  printf (gettext ("Inserting page %s, version %s from %s"),
	 "<b>" . htmlspecialchars ($pagename) . "</b>", $version, $source);
  print ("<br />\n");

  flush();
  InsertPage($dbi, $pagename, $page);
}
      
function LoadFile ($dbi, $filename, $text, $mtime)
{
  set_time_limit(30);	// Reset watchdog.
  if (!$mtime)
      $mtime = time();	// Last resort.

  $defaults = array('author' => 'The PhpWiki programming team',
		    'pagename' => rawurldecode($filename),
		    'created' => $mtime,
		    'flags' => 0,
		    'lastmodified' => $mtime,
		    'refs' => array(),
		    'version' => 1);
  
  if (!($parts = ParseMimeifiedPages($text)))
    {
      // Can't parse MIME: assume plain text file.
      $page = $defaults;
      $page['pagename'] = rawurldecode($filename);
      $page['content'] = preg_split('/[ \t\r]*\n/', chop($text));
      SavePage($dbi, $page, "text file");
    }
  else
    {
      for (reset($parts); $page = current($parts); next($parts))
	{
	  // Fill in defaults for missing values?
	  // Should we do more sanity checks here?
	  reset($defaults);
	  while (list($key, $val) = each($defaults))
	      if (!isset($page[$key]))
		  $page[$key] = $val;

	  if ($page['pagename'] != rawurldecode($filename))
	      printf("<b>Warning:</b> "
		     . "pagename (%s) doesn't match filename (%s)"
		     . " (using pagename)<br />\n",
		     htmlspecialchars($page['pagename']),
		     htmlspecialchars(rawurldecode($filename)));

	  SavePage($dbi, $page, "MIME file");
	}
    }
}

function LoadZipOrDir ($dbi, $zip_or_dir)
{
  global $LANG, $genericpages;

  $type = filetype($zip_or_dir);
  
  if ($type == 'file')
    {
      $zip = new ZipReader($zip_or_dir);
      while (list ($fn, $data, $attrib) = $zip->readFile())
	  LoadFile($dbi, $fn, $data, $attrib['mtime']);
    }
  else if ($type == 'dir')
    {
      $handle = opendir($dir = $zip_or_dir);

      // load default pages
      while ($fn = readdir($handle))
	{
	  if ($fn[0] == '.' || filetype("$dir/$fn") != 'file')
	      continue;
	  $stat = stat("$dir/$fn");
	  $mtime = $stat[9];
	  LoadFile($dbi, $fn, implode("", file("$dir/$fn")), $mtime);
	}
      closedir($handle);

      if ($LANG != "C") {   // if language is not default, then insert
			   // generic pages from the English ./pgsrc
	 reset($genericpages);
	 $dir = DEFAULT_WIKI_PGSRC;
	 while (list(, $fn) = each($genericpages))
	    LoadFile($dbi, $fn, implode("", file("$dir/$fn")), $mtime);
	}
    }
}

$genericpages = array(
	"ReleaseNotes",
	"SteveWainstead",
	"TestPage"
	);

LoadZipOrDir($dbi, WIKI_PGSRC);
?>