<?php rcs_id('$Id: dba.php,v 1.2 2001-12-19 08:54:05 carstenklapp Exp $');

require_once('lib/WikiDB/backend/dbaBase.php');

require_once('lib/DbaDatabase.php');

class WikiDB_backend_dba
extends WikiDB_backend_dbaBase
{
    function WikiDB_backend_dba ($dbparams) {
        $directory = '/tmp';
        $prefix = 'wiki_';
        $dba_handler = 'gdbm';
        $timeout = 20;
        extract($dbparams);
        
        $dbfile = "$directory/$prefix" . 'pagedb' . '.' . $dba_handler;

        // FIXME: error checking.
        $db = new DbaDatabase($dbfile, false, $dba_handler);
        $db->set_timeout($timeout);
        if (!$db->open('c')) {
            trigger_error(sprintf(_("%s: Can't open dba database"),$dbfile), E_USER_ERROR);
            ExitWikit();
        }

        $this->WikiDB_backend_dbaBase($db);
    }
};

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
