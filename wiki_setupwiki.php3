<!-- $Id: wiki_setupwiki.php3,v 1.10 2000-07-18 05:15:58 dairiki Exp $ -->
<?
require 'wiki_ziplib.php3';

function SavePage ($dbi, $page, $source)
{
  $pagename = $page['pagename'];
  $version = $page['version'];
  
  if (is_array($current = RetrievePage($dbi, $pagename)))
    {
      if ($version <= $current['version'])
	{
	  $page['version'] = $current['version'] + 1;
	  $version = $page['version'] . " [was $version]";
	}
      SaveCopyToArchive($pagename, $current);
    }

  printf("Inserting page <b>%s</b>, version %s from %s<br>\n",
	 htmlspecialchars($pagename), $version, $source);
  flush();
  InsertPage($dbi, $pagename, $page);
}
      
function LoadFile ($dbi, $filename, $text)
{
  $now = time();
  $defaults = array('author' => 'The PhpWiki programming team',
		    'pagename' => $filename,
		    'created' => $now,
		    'flags' => 0,
		    'lastmodified' => $now,
		    'refs' => array(),
		    'version' => 1);
  
  if (!($parts = ParseMimeifiedPages($text)))
    {
      // Can't parse MIME: assume plain text file.
      $page = $defaults;
      $page['pagename'] = $filename;
      $page['content'] = preg_split('/\r?\n/', preg_replace('/\r$/','',$text));

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

	  if ($page['pagename'] != $filename)
	      printf("<b>Warning:</b> "
		     . "pagename (%s) doesn't match filename (%s)"
		     . " (using pagename)<br>\n",
		     htmlspecialchars($page['pagename']),
		     htmlspecialchars($filename));

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
	  LoadFile($dbi, $fn, $data);
    }
  else if ($type == 'dir')
    {
      $handle = opendir($dir = $zip_or_dir);

      // load default pages
      while ($fn = readdir($handle))
	{
	  if (filetype("$dir/$fn") != 'file')
	      continue;
	  LoadFile($dbi, $fn, implode("", file("$dir/$fn")));
	}
      closedir($handle); 
    }
}

LoadZipOrDir($dbi, WIKI_PGSRC);
?>
