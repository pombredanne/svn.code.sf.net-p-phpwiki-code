<?php
/* Taken from http://www.wlug.org.nz/archive/
 */

define ("WIKI_SOAP", "true");

require_once('lib/prepend.php');
include_once("index.php");

//require_once('lib/stdlib.php');
require_once('lib/nusoap/nusoap.php');
require_once('lib/WikiDB.php');
require_once('lib/config.php');

$server = new soap_server;

$server->register('getPage');

function getPage($pagename) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page = $dbi->getPage($pagename);
  $rev = $page->getCurrentRevision();
  $text = $rev->getPackedContent();
  return $text;
}

$server->service($GLOBALS['HTTP_RAW_POST_DATA']);

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
