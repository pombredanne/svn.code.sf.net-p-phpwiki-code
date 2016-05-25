<?php

require_once 'lib/WikiDB.php';

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
    function __construct($dbparams)
    {
        $backend = 'ADODB';
        if (is_array($dbparams['dsn']))
            $backend = $dbparams['dsn']['phptype'];
        elseif (preg_match('/^(\w+):/', $dbparams['dsn'], $m))
            $backend = $m[1];
        // Do we have a override? (currently: mysql, sqlite, oracle, mssql, oci8po, postgres7)
        // TODO: mysqlt (innodb or bdb)
        if ($backend == 'pgsql') { // PearDB DSN cross-compatibility hack (for unit testing)
            $backend = 'postgres7';
            if (is_string($dbparams['dsn']))
                $dbparams['dsn'] = $backend . ':' . substr($dbparams['dsn'], 6);
        }
        if (findFile("lib/WikiDB/backend/ADODB_" . $backend . ".php", true)) {
            $backend = 'ADODB_' . $backend;
        } else {
            $backend = 'ADODB';
        }
        include_once 'lib/WikiDB/backend/' . $backend . '.php';
        $backend_class = "WikiDB_backend_" . $backend;
        $backend = new $backend_class($dbparams);
        if (!$backend->_dbh->_connectionID) return;
        parent::__construct($backend, $dbparams);
    }

    /*
     * Determine whether page exists (in non-default form).
     * @see WikiDB::isWikiPage for the slow generic version
     */
    public function isWikiPage($pagename)
    {
        $pagename = (string)$pagename;
        if ($pagename === '') {
            return false;
        }
        if (!array_key_exists($pagename, $this->_cache->_id_cache)) {
            $this->_cache->_id_cache[$pagename] = $this->_backend->is_wiki_page($pagename);
        }
        return $this->_cache->_id_cache[$pagename];
    }

    // add surrounding quotes '' if string
    public function quote($in)
    {
        if (is_int($in) || is_double($in)) {
            return $in;
        } elseif (is_bool($in)) {
            return $in ? 1 : 0;
        } elseif (is_null($in)) {
            return 'NULL';
        } else {
            return $this->_backend->_dbh->qstr($in);
        }
    }

    // ADODB handles everything as string
    // Don't add surrounding quotes '', same as in PearDB
    public function qstr($in)
    {
        return $this->_backend->_dbh->addq($in);
    }

    public function isOpen()
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (!$request->_dbi) {
            return false;
        }
        return is_resource($this->_backend->connection());
    }

    // SQL result: for simple select or create/update queries
    // returns the database specific resource type
    public function genericSqlQuery($sql, $args = array())
    {
        if ($args)
            $result = $this->_backend->_dbh->Execute($sql, $args);
        else
            $result = $this->_backend->_dbh->Execute($sql);
        if (!$result) {
            trigger_error("SQL Error: " . $this->_backend->_dbh->ErrorMsg(), E_USER_WARNING);
            return false;
        } else {
            return $result;
        }
    }

    // SQL iter: for simple select or create/update queries
    // returns the generic iterator object (count, next)
    public function genericSqlIter($sql, $field_list = NULL)
    {
        $result = $this->genericSqlQuery($sql);
        return new WikiDB_backend_ADODB_generic_iter($this->_backend, $result, $field_list);
    }

}
