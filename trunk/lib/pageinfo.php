<?php
rcs_id('$Id: pageinfo.php,v 1.10 2001-06-26 18:02:43 uckelman Exp $');
// Display the internal structure of a page. Steve Wainstead, June 2000

$html = "\n" . Element('th', 'Version') . "\n" . Element('th', 'Newer') . "\n" . Element('th', 'Older') . "\n" . Element('th', 'Created') . "\n" . Element('th', 'Author') . "\n";
$html = "\n" . Element('tr', $html) . "\n";

// Get all versions of a page, then iterate over them to make version list
$pages = RetrievePageVersions($dbi, $pagename, $WikiPageStore, $ArchivePageStore);
$i = 0;
foreach ($pages as $pagehash) {
	$row = "\n" . Element('td', array('align' => 'right'), QElement('a', array('href' => "$pagename?version=" . $pagehash['version']), $pagehash['version']));
	$row .= "\n" . Element('td', array('align' => 'center'), QElement('input', array('type' => 'radio', 'name' => 'ver2', 'value' => ($i ? $pagehash['version'] : 0), 'checked' => ($i ? false : true))));
	$row .= "\n" . Element('td', array('align' => 'center'), QElement('input', array('type' => 'radio', 'name' => 'ver1', 'value' => ($i ? $pagehash['version'] : 0), 'checked' => ($i++-1 ? false : true))));
	$row .= "\n" . QElement('td', strftime($datetimeformat, $pagehash['lastmodified']));
	$row .= "\n" . QElement('td', $pagehash['author']) . "\n";

	$html .= Element('tr', $row) . "\n"; 
}

$html = "\n" . Element('table', $html) . "\n" . Element('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'diff')) . "\n" . Element('input', array('type' => 'submit', 'value' => 'Run Diff')) . "\n";
$html = Element('form', array('method' => 'get', 'action' => $pagename), $html);

echo GeneratePage('MESSAGE', $html, gettext("PageInfo").": '$pagename'", 0);
?>
