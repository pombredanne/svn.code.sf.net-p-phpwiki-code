<?php // -*-php-*-
rcs_id('$Id: ADODB.php,v 1.6 2004-11-09 17:11:16 rurban Exp $');

require_once('lib/WikiDB.php');

/**
 * WikiDB layer for ADODB, which does nothing more than calling the 
 * mysql-specific ADODB backend.
 * Support for a newer adodb library, the adodb extension library 
 * and more databases will come with PhpWiki v1.3.10
 *
 * @author: Lawrence Akka, Reini Urban
 */
class WikiDB_ADODB extends WikiDB
{
    function WikiDB_ADODB ($dbparams) {
        if (is_array($dbparams['dsn']))
            $backend = $dbparams['dsn']['phptype'];
        elseif (preg_match('/^(\w+):/', $dbparams['dsn'], $m))
            $backend = $m[1];
        // Do we have a override? (currently: mysql, sqlite, oracle, mssql)
        // TODO: pgsql, mysqlt (innodb or bdb)
        if (FindFile("lib/WikiDB/backend/ADODB_$backend.php",true)) {
            $backend = 'ADODB_' . $backend;
        } else {
            $backend = 'ADODB';
        }
        include_once("lib/WikiDB/backend/$backend.php");
        $backend_class = "WikiDB_backend_$backend";
        $backend = new $backend_class($dbparams);
        $this->WikiDB($backend, $dbparams);
    }
    
    /**
     * Determine whether page exists (in non-default form).
     * @see WikiDB::isWikiPage
     */
    function isWikiPage ($pagename) {
        $pagename = (string) $pagename;
        if ($pagename === '') return false;
        if (!array_key_exists($pagename, $this->_cache->_id_cache)) {
            $this->_cache->_id_cache[$pagename] = $this->_backend->is_wiki_page($pagename);
        }
        return $this->_cache->_id_cache[$pagename];
    }
};
  
// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
