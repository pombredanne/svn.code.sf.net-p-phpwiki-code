<?php
/* lib/prepend.php
 *
 * Things which must be done and defined before anything else.
 */
$RCS_IDS = '';
function rcs_id ($id) { $GLOBALS['RCS_IDS'] .= "$id\n"; }
rcs_id('$Id: prepend.php,v 1.8 2002-01-07 18:41:44 carstenklapp Exp $');

error_reporting(E_ALL);
require_once('lib/ErrorManager.php');
require_once('lib/WikiCallback.php');

// FIXME: make this part of Request?
function ExitWiki($errormsg = false)
{
    static $in_exit = 0;
    global $dbi, $request;

    if($in_exit)
        exit();		// just in case CloseDataBase calls us
    $in_exit = true;

    if (!empty($dbi))
        $dbi->close();

    global $ErrorManager;
    $ErrorManager->flushPostponedErrors();
   
    if(!empty($errormsg)) {
        print "<br /><hr /><h2>" . _("WikiFatalError") . "</h2>\n";

        if (is_string($errormsg))
            print $errormsg;
        else
            $errormsg->printError();
        
        print "\n</body></html>";
    }

    $request->finish();
    exit;
}

$ErrorManager->setPostponedErrorMask(E_ALL);
$ErrorManager->setFatalHandler(new WikiFunctionCb('ExitWiki'));


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
