<?php
/**
 * SOAP server
 * Taken from http://www.wlug.org.nz/archive/
 *
 * Please see http://phpwiki.sourceforge.net/phpwiki/PhpWiki.wdsl
 * for the wdsl discussion.
 * Todo: 
 *  Installer helper which changes server url of the default PhpWiki.wdsl
 */

define ("WIKI_SOAP", "true");

require_once('lib/prepend.php');
include_once("index.php");

//require_once('lib/stdlib.php');
require_once('lib/nusoap/nusoap.php');
require_once('lib/WikiDB.php');
require_once('lib/config.php');

$server = new soap_server;

$server->register('getPageContent');
$server->register('getPageRevision');
$server->register('getCurrentRevision');
$server->register('getPageMeta');
//$server->register('doSavePage');
$server->register('getAllPagenames');
$server->register('getBackLinks');
$server->register('doTitleSearch');
$server->register('doFullTextSearch');

function doSavePage($pagename,$content,$credentials) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page = $dbi->getPage($pagename);
  $current = $page->getCurrentRevision();
  $meta = $current->_data;
  $meta['summary'] = sprintf(_("SOAP Request")); // from
  $version = $current->getVersion();
  //todo: check credentials
  return $page->save($content, $version + 1, $meta);
}

function getPageContent($pagename) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page = $dbi->getPage($pagename);
  $rev = $page->getCurrentRevision();
  $text = $rev->getPackedContent();
  return $text;
}
function getPageRevision($pagename,$revision) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page = $dbi->getPage($pagename);
  $rev = $page->getCurrentRevision();
  $text = $rev->getPackedContent();
  return $text;
}
function getCurrentRevision($pagename) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page = $dbi->getPage($pagename);
  $rev = $page->getCurrentRevision();
  $version = $current->getVersion();
  return (double)$version;
}
function getPageMeta($pagename) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page = $dbi->getPage($pagename);
  $rev = $page->getCurrentRevision();
  $meta = $rev->_data;
  //todo: reformat the meta hash
  return $meta;
}
function getAllPagenames() {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page_iter = $dbi->getAllPages();
  $pages = array();
  while ($page = $page_iter->next()) {
    $pages[] = array('pagename' => $page->_pagename);
  }
  return $pages;
}
function getBacklinks($pagename) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $backend = &$dbi->_backend;
  $result =  $backend->get_links($pagename);
  $page_iter = new WikiDB_PageIterator($dbi, $result);
  $pages = array();
  while ($page = $page_iter->next()) {
    $pages[] = array('pagename' => $page->_pagename);
  }
  return $pages;
}
function doTitleSearch($query) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page_iter = $dbi->titleSearch($query);
  $pages = array();
  while ($page = $page_iter->next()) {
    $pages[] = array('pagename' => $page->_pagename);
  }
  return $pages;
}
function doFullTextSearch($query) {
  $dbi = WikiDB::open($GLOBALS['DBParams']);
  $page_iter = $dbi->fullSearch($query);
  $pages = array();
  while ($page = $page_iter->next()) {
    $pages[] = array('pagename' => $page->_pagename);
  }
  return $pages;
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
