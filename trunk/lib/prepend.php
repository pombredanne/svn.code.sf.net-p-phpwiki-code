<?php
/* lib/prepend.php
 *
 * Things which must be done and defined before anything else.
 */
$RCS_IDS = '';
function rcs_id ($id) { 
    // Save memory
    if (defined('DEBUG') and DEBUG)
        $GLOBALS['RCS_IDS'] .= "$id\n"; 
}
rcs_id('$Id: prepend.php,v 1.41 2005-09-10 21:23:54 rurban Exp $');

define('PHPWIKI_VERSION', '1.3.11_20050910');

/** 
 * Returns true if current php version is at mimimum a.b.c 
 * Called: check_php_version(4,1)
 */
function check_php_version ($a = '0', $b = '0', $c = '0') {
    static $PHP_VERSION;
    if (!isset($PHP_VERSION))
        $PHP_VERSION = substr( str_pad( preg_replace('/\D/','', PHP_VERSION), 3, '0'), 0, 3);
    return ($PHP_VERSION >= ($a.$b.$c));
}

/** PHP5 deprecated old-style globals if !(bool)ini_get('register_long_arrays'). 
  *  See Bug #1180115
  * We want to work with those old ones instead of the new superglobals, 
  * for easier coding.
  */
foreach (array('SERVER','REQUEST','GET','POST','SESSION','ENV','COOKIE') as $k) {
    if (!isset($GLOBALS['HTTP_'.$k.'_VARS']) and isset($GLOBALS['_'.$k]))
        $GLOBALS['HTTP_'.$k.'_VARS'] =& $GLOBALS['_'.$k];
}
unset($k);

// If your php was compiled with --enable-trans-sid it tries to
// add a PHPSESSID query argument to all URL strings when cookie
// support isn't detected in the client browser.  For reasons
// which aren't entirely clear (PHP bug) this screws up the URLs
// generated by PhpWiki.  Therefore, transparent session ids
// should be disabled.  This next line does that.
//
// (At the present time, you will not be able to log-in to PhpWiki,
// unless your browser supports cookies.)
@ini_set('session.use_trans_sid', 0);

if (defined('DEBUG') and (DEBUG & 8) and extension_loaded("xdebug")) {
    xdebug_start_trace("trace"); // on Dbgp protocol add 2
    xdebug_enable();
}

// Used for debugging purposes
class DebugTimer {
    function DebugTimer() {
        $this->_start = $this->microtime();
        if (function_exists('posix_times'))
            $this->_times = posix_times();
    }

    /**
     * @param string $which  One of 'real', 'utime', 'stime', 'cutime', 'sutime'
     * @return float Seconds.
     */
    function getTime($which='real', $now=false) {
        if ($which == 'real')
            return $this->microtime() - $this->_start;

        if (isset($this->_times)) {
            if (!$now) $now = posix_times();
            $ticks = $now[$which] - $this->_times[$which];
            return $ticks / $this->_CLK_TCK();
        }

        return 0.0;           // Not available.
    }

    function getStats() {
        if (!isset($this->_times)) {
            // posix_times() not available.
            return sprintf("real: %.3f", $this->getTime('real'));
        }
        $now = posix_times();
        return sprintf("real: %.3f, user: %.3f, sys: %.3f",
                       $this->getTime('real'),
                       $this->getTime('utime', $now),
                       $this->getTime('stime', $now));
    }
        
    function _CLK_TCK() {
        // FIXME: this is clearly not always right.
        // But how to figure out the right value?
        return 100.0;
    }

    function microtime(){
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}
$RUNTIMER = new DebugTimer;
/*
if (defined('E_STRICT') and (E_ALL & E_STRICT)) // strict php5?
    error_reporting(E_ALL & ~E_STRICT); 	// exclude E_STRICT
else
    error_reporting(E_ALL); // php4
//echo " prepend: ", error_reporting();
*/
require_once(dirname(__FILE__).'/ErrorManager.php');
require_once(dirname(__FILE__).'/WikiCallback.php');

// FIXME: deprecated
function ExitWiki($errormsg = false)
{
    global $request;
    static $in_exit = 0;

    if (is_object($request) and method_exists($request,"finish"))
        $request->finish($errormsg); // NORETURN

    if ($in_exit)
        exit;
    
    $in_exit = true;

    global $ErrorManager;
    $ErrorManager->flushPostponedErrors();
   
    if(!empty($errormsg)) {
        PrintXML(HTML::br(), $errormsg);
        print "\n</body></html>";
    }
    exit;
}
if (!defined('DEBUG') or (defined('DEBUG') and DEBUG > 2)) {
    $ErrorManager->setPostponedErrorMask(E_ALL); // ignore all errors
    $ErrorManager->setFatalHandler(new WikiFunctionCb('ExitWiki'));
} else {
    $ErrorManager->setPostponedErrorMask(E_USER_NOTICE | E_NOTICE);
}


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>