<?php rcs_id('$Id: PhpWikiDatabase.php,v 1.1 2001-07-20 17:29:43 wainstead Exp $');

/* 
 * Abstract base class for the database used by PhpWiki.
 * This should be extended by classes for DB/dbx, dba and 
 * flat file.
 */

class PhpWikiDatabase {

function retrievePage (string $pagename, int $version = 0) 
  {
  }
function insertPage (WikiPage $page, boolean $no_backup = false) 
  {
  }
function nPages() 
  {
  }
function isWikiPage (string $pagename) 
  {}
function previousVersion (string $pagename, int $version = 0) 
  {
  }
function retrieveAllVersions(string $pagename) 
  {
  }
function titleSearch(string $search) 
  {
  }
function fullSearch(string $search) 
  {
  }
function backLinks(string $pagename) 
  {
  }
function mostPopular(int $limit = 20) 
  {
  }
function retrieveAllPages () 
  {
  }
function genericWarnings() 
  {
  }
function close() 
  {
  }

}