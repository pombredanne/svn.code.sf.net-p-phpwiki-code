<?php rcs_id('$Id: SQL.php,v 1.9 2004-11-09 17:11:16 rurban Exp $');

require_once('lib/WikiDB.php');
//require_once('lib/WikiDB/backend/PearDB.php');
//require_once('DB.php'); // Always favor use our local pear copy

/**
 *
 */
class WikiDB_SQL extends WikiDB
{
    function WikiDB_SQL ($dbparams) {
        $backend_type = 'PearDB';
        if (is_array($dbparams['dsn']))
            $backend_type = $dbparams['dsn']['phptype'];
        elseif (preg_match('/^(\w+):/', $dbparams['dsn'], $m))
            $backend_type = $m[1];
        include_once ("lib/WikiDB/backend/PearDB_".$backend_type.".php");
        $backend_class = "WikiDB_backend_PearDB_".$backend_type;
        $backend = & new $backend_class($dbparams);
        $this->_iwpcache = array();
        
        $this->WikiDB($backend, $dbparams);
    }
    
    function view_dsn ($dsn = false) {
        if (!$dsn)
            $dsninfo = DB::parseDSN($GLOBALS['DBParams']['dsn']);
        else
            $dsninfo = DB::parseDSN($dsn);
        return sprintf("%s://%s:<not displayed>@%s/%s",
                       $dsninfo['phptype'],
                       $dsninfo['username'],
                       $dsninfo['hostspec'],
                       $dsninfo['database']
                       );
    }

    
    /**
     * Determine whether page exists (in non-default form).
     * @see WikiDB::isWikiPage for the slow generic version
     */
    function isWikiPage ($pagename) {
        $pagename = (string) $pagename;
        if ($pagename === '') return false;
        //if (empty($this->_iwpcache)) {  $this->_iwpcache = array();  }
        if (!array_key_exists($pagename, $this->_iwpcache)) {
            $this->_iwpcache[$pagename] = $this->_backend->is_wiki_page($pagename);
        }
        return $this->_iwpcache[$pagename];
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
