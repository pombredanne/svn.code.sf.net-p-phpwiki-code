<?php rcs_id('$Id: dba.php,v 1.5 2005-02-18 20:41:28 uckelman Exp $');

require_once('lib/WikiDB.php');
require_once('lib/WikiDB/backend/dba.php');
/**
 *
 */
class WikiDB_dba extends WikiDB
{
    function WikiDB_dba ($dbparams) {
        $backend = new WikiDB_backend_dba($dbparams);
        $this->WikiDB($backend, $dbparams);

        if (empty($dbparams['directory'])
            || preg_match('@^/tmp\b@', $dbparams['directory'])) 
            trigger_error(_("DBA files are in the /tmp directory. Please read the INSTALL file and move the database to a permanent location or risk losing all the pages!"), E_USER_WARNING);
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
