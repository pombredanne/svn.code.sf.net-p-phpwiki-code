<!-- $Id: pageinfo.php,v 1.6 2001-02-10 22:15:08 dairiki Exp $ -->
<!-- Display the internal structure of a page. Steve Wainstead, June 2000 -->
<?php



function ViewpageProps($name, $pagestore)
{
   global $dbi, $showpagesource, $datetimeformat, $FieldSeparator;

   $pagehash = RetrievePage($dbi, $name, $pagestore);
   if ($pagehash == -1) {
      return QElement('p',
		      sprintf (gettext ("Page name '%s' is not in the database"),
			       $name));
   }

   $rows = '';
   while (list($key, $val) = each($pagehash)) {
      if ($key > 0 || !$key)
	 continue; //key is an array index
      $cols = QElement('td', array('align' => 'right'), $key);

      if (is_array($val))
      {
	 if (empty($showpagesource))
	    continue;
	 $cols .= Element('td',
			  nl2br(htmlspecialchars(join("\n", $val))));
      }
      elseif (($key == 'lastmodified') || ($key == 'created'))
	 $cols .= QElement('td',
			   date($datetimeformat, $val));
      else
	 $cols .= QElement('td', $val);
      
      $rows .= Element('tr', $cols);
   }

   return Element('table', array('border' => 1, 'bgcolor' => 'white'), $rows);
}


$html = '';

if (empty($showpagesource))
{
   $text = gettext ("Show the page source");
   $url = WikiURL($pagename, array('action' => 'info',
				   'showpagesource' => 'on'));
   $html .= QElement('a', array('href' => $url), $text);
}

$html .= Element('p', QElement('b', gettext ("Current version")));
$html .= ViewPageProps($pagename, $WikiPageStore);

$html .= Element('p', QElement('b', gettext ("Archived version")));
$html .= ViewPageProps($pagename, $ArchivePageStore);

GeneratePage('MESSAGE', $html, gettext("PageInfo").": '$pagename'", 0);
?>
