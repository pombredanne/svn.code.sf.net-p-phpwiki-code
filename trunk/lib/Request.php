<?php rcs_id('$Id: Request.php,v 1.35 2003-11-06 19:39:55 carstenklapp Exp $');
// FIXME: write log entry.


// backward compatibility for PHP < 4.2.0
if (!function_exists('ob_clean')) {
    function ob_clean() {
        ob_end_clean();
        ob_start();
    }
}

        
class Request {
        
    function Request() {
        $this->_fix_magic_quotes_gpc();
        $this->_fix_multipart_form_data();
        
        switch($this->get('REQUEST_METHOD')) {
        case 'GET':
        case 'HEAD':
            $this->args = &$GLOBALS['HTTP_GET_VARS'];
            break;
        case 'POST':
            $this->args = &$GLOBALS['HTTP_POST_VARS'];
            break;
        default:
            $this->args = array();
            break;
        }
        
        $this->session = new Request_SessionVars; 
        $this->cookies = new Request_CookieVars;
        
        if (ACCESS_LOG)
            $this->_log_entry = & new Request_AccessLogEntry($this,
                                                             ACCESS_LOG);
        
        $GLOBALS['request'] = $this;
    }

    function get($key) {
        $vars = &$GLOBALS['HTTP_SERVER_VARS'];

        if (isset($vars[$key]))
            return $vars[$key];

        switch ($key) {
        case 'REMOTE_HOST':
            $addr = $vars['REMOTE_ADDR'];
            if (defined('ENABLE_REVERSE_DNS') && ENABLE_REVERSE_DNS)
                return $vars[$key] = gethostbyaddr($addr);
            else
                return $addr;
        default:
            return false;
        }
    }

    function getArg($key) {
        if (isset($this->args[$key]))
            return $this->args[$key];
        return false;
    }

    function getArgs () {
        return $this->args;
    }
    
    function setArg($key, $val) {
        if ($val === false)
            unset($this->args[$key]);
        else
            $this->args[$key] = $val;
    }
    
    // Well oh well. Do we really want to pass POST params back as GET?
    function getURLtoSelf($args = false, $exclude = array()) {

        // Err... good point...
        if ($this->isPost())
            trigger_error("Request::getURLtoSelf() should probably not be from POST",
                          E_USER_NOTICE);
        
        $get_args = $this->args;
        if ($args)
            $get_args = array_merge($get_args, $args);

        foreach ($exclude as $ex) {
            if (!empty($get_args[$ex])) unset($get_args[$ex]);
        }

        $pagename = $get_args['pagename'];
        unset ($get_args['pagename']);
        if ($get_args['action'] == 'browse')
            unset($get_args['action']);

        return WikiURL($pagename, $get_args);
    }

    function isPost () {
        return $this->get("REQUEST_METHOD") == "POST";
    }

    function isGetOrHead () {
        return in_array($this->get('REQUEST_METHOD'),
                        array('GET', 'HEAD'));
    }

    function httpVersion() {
        if (!preg_match('@HTTP\s*/\s*(\d+.\d+)@', $this->get('SERVER_PROTOCOL'), $m))
            return false;
        return (float) $m[1];
    }
    
    function redirect($url, $noreturn=true) {
        $bogus = defined('DISABLE_HTTP_REDIRECT') and DISABLE_HTTP_REDIRECT;
        
        if (!$bogus) {
            header("Location: $url");
            /*
             * "302 Found" is not really meant to be sent in response
             * to a POST.  Worse still, according to (both HTTP 1.0
             * and 1.1) spec, the user, if it is sent, the user agent
             * is supposed to use the same method to fetch the
             * redirected URI as the original.
             *
             * That means if we redirect from a POST, the user-agent
             * supposed to generate another POST.  Not what we want.
             * (We do this after a page save after all.)
             *
             * Fortunately, most/all browsers don't do that.
             *
             * "303 See Other" is what we really want.  But it only
             * exists in HTTP/1.1
             *
             * FIXME: this is still not spec compliant for HTTP
             * version < 1.1.
             */
            $status = $this->httpVersion() >= 1.1 ? 303 : 302;

            $this->setStatus($status);
        }

        if ($noreturn) {
            include_once('lib/Template.php');
            $this->discardOutput();
            $tmpl = new Template('redirect', $this, array('REDIRECT_URL' => $url));
            $tmpl->printXML();
            $this->finish();
        }
        else if ($bogus) {
            return JavaScript("
              function redirect(url) {
                if (typeof location.replace == 'function')
                  location.replace(url);
                else if (typeof location.assign == 'function')
                  location.assign(url);
                else
                  window.location = url;
              }
              redirect('" . addslashes($url) . "')");
        }
    }

    /** Set validators for this response.
     *
     * This sets a (possibly incomplete) set of validators
     * for this response.
     *
     * The validator set can be extended using appendValidators().
     *
     * When you're all done setting and appending validators, you
     * must call checkValidators() to check them and set the
     * appropriate headers in the HTTP response.
     *
     * Example Usage:
     *  ...
     *  $request->setValidators(array('pagename' => $pagename,
     *                                '%mtime' => $rev->get('mtime')));
     *  ...
     *  // Wups... response content depends on $otherpage, too...
     *  $request->appendValidators(array('otherpage' => $otherpagerev->getPageName(),
     *                                   '%mtime' => $otherpagerev->get('mtime')));
     *  ...
     *  // After all validators have been set:
     *  $request->checkValidators();
     */
    function setValidators($validator_set) {
        if (is_array($validator_set))
            $validator_set = new HTTP_ValidatorSet($validator_set);
        $this->_validators = $validator_set;
    }
    
    /** Append validators for this response.
     *
     * This appends additional validators to this response.
     * You must call setValidators() before calling this method.
     */ 
    function appendValidators($validator_set) {
        $this->_validators->append($validator_set);
    }
    
    /** Check validators and set headers in HTTP response
     *
     * This sets the appropriate "Last-Modified" and "ETag"
     * headers in the HTTP response.
     *
     * Additionally, if the validators match any(all) conditional
     * headers in the HTTP request, this method will not return, but
     * instead will send "304 Not Modified" or "412 Precondition
     * Failed" (as appropriate) back to the client.
     */
    function checkValidators() {
        $validators = &$this->_validators;
        
        // Set validator headers
        if (($etag = $validators->getETag()) !== false)
            header("ETag: " . $etag->asString());
        if (($mtime = $validators->getModificationTime()) !== false)
            header("Last-Modified: " . Rfc1123DateTime($mtime));

        // Set cache control headers
        $this->cacheControl();

        if (CACHE_CONTROL == 'NONE')
            return;             // don't check conditionals...
        
        // Check conditional headers in request
        $status = $validators->checkConditionalRequest($this);
        if ($status) {
            // Return short response due to failed conditionals
            $this->setStatus($status);
            print "\n\n";
            $this->discardOutput();
            $this->finish();
            exit();
        }
    }

    /** Set the cache control headers in the HTTP response.
     */
    function cacheControl($strategy=CACHE_CONTROL, $max_age=CACHE_CONTROL_MAX_AGE) {
        if ($strategy == 'NONE') {
            $cache_control = "no-cache";
            $max_age = -20;
        }
        elseif ($strategy == 'ALLOW_STALE' && $max_age > 0) {
            $cache_control = sprintf("max-age=%d", $max_age);
        }
        else {
            $cache_control = "must-revalidate";
            $max_age = -20;
        }
        header("Cache-Control: $cache_control");
        header("Expires: " . Rfc1123DateTime(time() + $max_age));
        header("Vary: Cookie"); // FIXME: add more here?
    }
    
    function setStatus($status) {
        if (preg_match('|^HTTP/.*?\s(\d+)|i', $status, $m)) {
            header($status);
            $status = $m[1];
        }
        else {
            $status = (integer) $status;
            $reason = array('200' => 'OK',
                            '302' => 'Found',
                            '303' => 'See Other',
                            '304' => 'Not Modified',
                            '400' => 'Bad Request',
                            '401' => 'Unauthorized',
                            '403' => 'Forbidden',
                            '404' => 'Not Found',
                            '412' => 'Precondition Failed');
            // FIXME: is it always okay to send HTTP/1.1 here, even for older clients?
            header(sprintf("HTTP/1.1 %d %s", $status, $reason[$status]));
        }

        if (isset($this->_log_entry))
            $this->_log_entry->setStatus($status);
    }

    function buffer_output($compress = true) {
        if (defined('COMPRESS_OUTPUT')) {
            if (!COMPRESS_OUTPUT)
                $compress = false;
        }
        elseif (!function_exists('version_compare')
                || version_compare(phpversion(), '4.2.3', "<")) {
            $compress = false;
        }

        if (!function_exists('ob_gzhandler'))
            $compress = false;
        
        if ($compress) {
            ob_start('ob_gzhandler');
            /*
             * Attempt to prevent Apache from doing the dreaded double-gzip.
             *
             * It would be better if we could detect when apache was going
             * to zip for us, and then let it ... but I have yet to figure
             * out how to do that.
             */
            @apache_note('no-gzip', 1);
        }
        else {
            // Now we alway buffer output.
            // This is so we can set HTTP headers (e.g. for redirect)
            // at any point.
            // FIXME: change the name of this method.
            ob_start();
        }
        $this->_is_buffering_output = true;
    }

    function discardOutput() {
        if (!empty($this->_is_buffering_output))
            ob_clean();
        else
            trigger_error("Not buffering output", E_USER_NOTICE);
    }
    
    function finish() {
        if (!empty($this->_is_buffering_output)) {
            //header(sprintf("Content-Length: %d", ob_get_length()));
            ob_end_flush();
        }
        exit;
    }

    function getSessionVar($key) {
        return $this->session->get($key);
    }
    function setSessionVar($key, $val) {
        return $this->session->set($key, $val);
    }
    function deleteSessionVar($key) {
        return $this->session->delete($key);
    }

    function getCookieVar($key) {
        return $this->cookies->get($key);
    }
    function setCookieVar($key, $val, $lifetime_in_days = false) {
        return $this->cookies->set($key, $val, $lifetime_in_days);
    }
    function deleteCookieVar($key) {
        return $this->cookies->delete($key);
    }
    
    function getUploadedFile($key) {
        return Request_UploadedFile::getUploadedFile($key);
    }
    

    function _fix_magic_quotes_gpc() {
        $needs_fix = array('HTTP_POST_VARS',
                           'HTTP_GET_VARS',
                           'HTTP_COOKIE_VARS',
                           'HTTP_SERVER_VARS',
                           'HTTP_POST_FILES');
        
        // Fix magic quotes.
        if (get_magic_quotes_gpc()) {
            foreach ($needs_fix as $vars)
                $this->_stripslashes($GLOBALS[$vars]);
        }
    }

    function _stripslashes(&$var) {
        if (is_array($var)) {
            foreach ($var as $key => $val)
                $this->_stripslashes($var[$key]);
        }
        elseif (is_string($var))
            $var = stripslashes($var);
    }
    
    function _fix_multipart_form_data () {
        if (preg_match('|^multipart/form-data|', $this->get('CONTENT_TYPE')))
            $this->_strip_leading_nl($GLOBALS['HTTP_POST_VARS']);
    }
    
    function _strip_leading_nl(&$var) {
        if (is_array($var)) {
            foreach ($var as $key => $val)
                $this->_strip_leading_nl($var[$key]);
        }
        elseif (is_string($var))
            $var = preg_replace('|^\r?\n?|', '', $var);
    }
}

class Request_SessionVars {
    function Request_SessionVars() {
        // Prevent cacheing problems with IE 5
        session_cache_limiter('none');
                                        
        session_start();
    }
    
    function get($key) {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (isset($vars[$key]))
            return $vars[$key];
        return false;
    }
    
    function set($key, $val) {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (!function_usable('ini_get') or ini_get('register_globals')) {
            // This is funky but necessary, at least in some PHP's
            $GLOBALS[$key] = $val;
        }
        $vars[$key] = $val;
        session_register($key);
    }
    
    function delete($key) {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (!function_usable('ini_get') or ini_get('register_globals'))
            unset($GLOBALS[$key]);
        unset($vars[$key]);
        session_unregister($key);
    }
}

class Request_CookieVars {
    
    function get($key) {
        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        if (isset($vars[$key])) {
            @$val = unserialize($vars[$key]);
            if (!empty($val))
                return $val;
        }
        return false;
    }
        
    function set($key, $val, $persist_days = false) {
        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        
        if (is_numeric($persist_days)) {
            $expires = time() + (24 * 3600) * $persist_days;
        }
        else {
            $expires = 0;
        }
        
        $packedval = serialize($val);
        $vars[$key] = $packedval;
        setcookie($key, $packedval, $expires, '/');
    }
    
    function delete($key) {
        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        setcookie($key);
        unset($vars[$key]);
    }
}

class Request_UploadedFile {
    function getUploadedFile($postname) {
        global $HTTP_POST_FILES;
        
        if (!isset($HTTP_POST_FILES[$postname]))
            return false;
        
        $fileinfo = &$HTTP_POST_FILES[$postname];
        if (!is_uploaded_file($fileinfo['tmp_name']))
            return false;       // possible malicious attack.

        return new Request_UploadedFile($fileinfo);
    }
    
    function Request_UploadedFile($fileinfo) {
        $this->_info = $fileinfo;
    }

    function getSize() {
        return $this->_info['size'];
    }

    function getName() {
        return $this->_info['name'];
    }

    function getType() {
        return $this->_info['type'];
    }

    function getTmpName() {
        return $this->_info['tmp_name'];
    }

    function open() {
        if ( ($fd = fopen($this->_info['tmp_name'], "rb")) ) {
            if ($this->getSize() < filesize($this->_info['tmp_name'])) {
                // FIXME: Some PHP's (or is it some browsers?) put
                //    HTTP/MIME headers in the file body, some don't.
                //
                // At least, I think that's the case.  I know I used
                // to need this code, now I don't.
                //
                // This code is more-or-less untested currently.
                //
                // Dump HTTP headers.
                while ( ($header = fgets($fd, 4096)) ) {
                    if (trim($header) == '') {
                        break;
                    }
                    else if (!preg_match('/^content-(length|type):/i', $header)) {
                        rewind($fd);
                        break;
                    }
                }
            }
        }
        return $fd;
    }

    function getContents() {
        $fd = $this->open();
        $data = fread($fd, $this->getSize());
        fclose($fd);
        return $data;
    }
}

/**
 * Create NCSA "combined" log entry for current request.
 */
class Request_AccessLogEntry
{
    /**
     * Constructor.
     *
     * The log entry will be automatically appended to the log file
     * when the current request terminates.
     *
     * If you want to modify a Request_AccessLogEntry before it gets
     * written (e.g. via the setStatus and setSize methods) you should
     * use an '&' on the constructor, so that you're working with the
     * original (rather than a copy) object.
     *
     * <pre>
     *    $log_entry = & new Request_AccessLogEntry($req, "/tmp/wiki_access_log");
     *    $log_entry->setStatus(401);
     * </pre>
     *
     *
     * @param $request object  Request object for current request.
     * @param $logfile string  Log file name.
     */
    function Request_AccessLogEntry (&$request, $logfile) {
        $this->logfile = $logfile;
        
        $this->host  = $request->get('REMOTE_HOST');
        $this->ident = $request->get('REMOTE_IDENT');
        if (!$this->ident)
            $this->ident = '-';
        $this->user = '-';        // FIXME: get logged-in user name
        $this->time = time();
        $this->request = join(' ', array($request->get('REQUEST_METHOD'),
                                         $request->get('REQUEST_URI'),
                                         $request->get('SERVER_PROTOCOL')));
        $this->status = 200;
        $this->size = 0;
        $this->referer = (string) $request->get('HTTP_REFERER');
        $this->user_agent = (string) $request->get('HTTP_USER_AGENT');

        global $Request_AccessLogEntry_entries;
        if (!isset($Request_AccessLogEntry_entries)) {
            register_shutdown_function("Request_AccessLogEntry_shutdown_function");
        }
        $Request_AccessLogEntry_entries[] = &$this;
    }

    /**
     * Set result status code.
     *
     * @param $status integer  HTTP status code.
     */
    function setStatus ($status) {
        $this->status = $status;
    }
    
    /**
     * Set response size.
     *
     * @param $size integer
     */
    function setSize ($size) {
        $this->size = $size;
    }
    
    /**
     * Get time zone offset.
     *
     * This is a static member function.
     *
     * @param $time integer Unix timestamp (defaults to current time).
     * @return string Zone offset, e.g. "-0800" for PST.
     */
    function _zone_offset ($time = false) {
        if (!$time)
            $time = time();
        $offset = date("Z", $time);
        if ($offset < 0) {
            $negoffset = "-";
            $offset = -$offset;
        }
        $offhours = floor($offset / 3600);
        $offmins  = $offset / 60 - $offhours * 60;
        return sprintf("%s%02d%02d", $negoffset, $offhours, $offmins);
    }

    /**
     * Format time in NCSA format.
     *
     * This is a static member function.
     *
     * @param $time integer Unix timestamp (defaults to current time).
     * @return string Formatted date & time.
     */
    function _ncsa_time($time = false) {
        if (!$time)
            $time = time();

        return date("d/M/Y:H:i:s", $time) .
            " " . $this->_zone_offset();
    }

    /**
     * Write entry to log file.
     */
    function write() {
        $entry = sprintf('%s %s %s [%s] "%s" %d %d "%s" "%s"',
                         $this->host, $this->ident, $this->user,
                         $this->_ncsa_time($this->time),
                         $this->request, $this->status, $this->size,
                         $this->referer, $this->user_agent);

        //Error log doesn't provide locking.
        //error_log("$entry\n", 3, $this->logfile);

        // Alternate method 
        if (($fp = fopen($this->logfile, "a"))) {
            flock($fp, LOCK_EX);
            fputs($fp, "$entry\n");
            fclose($fp);
        }
    }
}

/**
 * Shutdown callback.
 *
 * @access private
 * @see Request_AccessLogEntry
 */
function Request_AccessLogEntry_shutdown_function ()
{
    global $Request_AccessLogEntry_entries;
    
    foreach ($Request_AccessLogEntry_entries as $entry) {
        $entry->write();
    }
    unset($Request_AccessLogEntry_entries);
}


class HTTP_ETag {
    function HTTP_ETag($val, $is_weak=false) {
        $this->_val = hash($val);
        $this->_weak = $is_weak;
    }

    /** Comparison
     *
     * Strong comparison: If either (or both) tag is weak, they
     *  are not equal.
     */
    function equals($that, $strong_match=false) {
        if ($this->_val != $that->_val)
            return false;
        if ($strong_match and ($this->_weak or $that->_weak))
            return false;
        return true;
    }


    function asString() {
        $quoted = '"' . addslashes($this->_val) . '"';
        return $this->_weak ? "W/$quoted" : $quoted;
    }

    /** Parse tag from header.
     *
     * This is a static member function.
     */
    function parse($strval) {
        if (!preg_match(':^(W/)?"(.+)"$:i', trim($strval), $m))
            return false;       // parse failed
        list(,$weak,$str) = $m;
        return new HTTP_ETag(stripslashes($str), $weak);
    }

    function matches($taglist, $strong_match=false) {
        $taglist = trim($taglist);

        if ($taglist == '*') {
            if ($strong_match)
                return ! $this->_weak;
            else
                return true;
        }

        while (preg_match('@^(W/)?"((?:\\\\.|[^"])*)"\s*,?\s*@i',
                          $taglist, $m)) {
            list($match, $weak, $str) = $m;
            $taglist = substr($taglist, strlen($match));
            $tag = new HTTP_ETag(stripslashes($str), $weak);
            if ($this->equals($tag, $strong_match)) {
                return true;
            }
        }
        return false;
    }
}

// Possible results from the HTTP_ValidatorSet::_check*() methods.
// (Higher numerical values take precedence.)
define ('_HTTP_VAL_PASS', 0);   // Test is irrelevant
define ('_HTTP_VAL_NOT_MODIFIED', 1); // Test passed, content not changed
define ('_HTTP_VAL_MODIFIED', 2); // Test failed, content changed
define ('_HTTP_VAL_FAILED', 3); // Precondition failed.

class HTTP_ValidatorSet {
    function HTTP_ValidatorSet($validators) {
        $this->_mtime = $this->_weak = false;
        $this->_tag = array();
        
        foreach ($validators as $key => $val) {
            if ($key == '%mtime') {
                $this->_mtime = $val;
            }
            elseif ($key == '%weak') {
                if ($val)
                    $this->_weak = true;
            }
            else {
                $this->_tag[$key] = $val;
            }
        }
    }

    function append($that) {
        if (is_array($that))
            $that = new HTTP_ValidatorSet($that);

        // Pick the most recent mtime
        if (isset($that->_mtime))
            if (!isset($this->_mtime) || $that->_mtime > $this->_mtime)
                $this->_mtime = $that->_mtime;

        // If either is weak, we're weak
        if (!empty($that->_weak))
            $this->_weak = true;

        $this->_tag = array_merge($this->_tag, $that->_tag);
    }

    function getETag() {
        if (! $this->_tag)
            return false;
        return new HTTP_ETag($this->_tag, $this->_weak);
    }

    function getModificationTime() {
        return $this->_mtime;
    }
    
    function checkConditionalRequest (&$request) {
        $result = max($this->_checkIfUnmodifiedSince($request),
                      $this->_checkIfModifiedSince($request),
                      $this->_checkIfMatch($request),
                      $this->_checkIfNoneMatch($request));

        if ($result == _HTTP_VAL_PASS || $result == _HTTP_VAL_MODIFIED)
            return false;       // "please proceed with normal processing"
        elseif ($result == _HTTP_VAL_FAILED)
            return 412;         // "412 Precondition Failed"
        elseif ($result == _HTTP_VAL_NOT_MODIFIED)
            return 304;         // "304 Not Modified"

        trigger_error("Ack, shouldn't get here", E_USER_ERROR);
        return false;
    }

    function _checkIfUnmodifiedSince(&$request) {
        if ($this->_mtime !== false) {
            $since = ParseRfc1123DateTime($request->get("HTTP_IF_UNMODIFIED_SINCE"));
            if ($since !== false && $this->_mtime > $since)
                return _HTTP_VAL_FAILED;
        }
        return _HTTP_VAL_PASS;
    }

    function _checkIfModifiedSince(&$request) {
        if ($this->_mtime !== false and $request->isGetOrHead()) {
            $since = ParseRfc1123DateTime($request->get("HTTP_IF_MODIFIED_SINCE"));
            if ($since !== false) {
                if ($this->_mtime <= $since)
                    return _HTTP_VAL_NOT_MODIFIED;
                return _HTTP_VAL_MODIFIED;
            }
        }
        return _HTTP_VAL_PASS;
    }

    function _checkIfMatch(&$request) {
        if ($this->_tag && ($taglist = $request->get("HTTP_IF_MATCH"))) {
            $tag = $this->getETag();
            if (!$tag->matches($taglist, 'strong'))
                return _HTTP_VAL_FAILED;
        }
        return _HTTP_VAL_PASS;
    }

    function _checkIfNoneMatch(&$request) {
        if ($this->_tag && ($taglist = $request->get("HTTP_IF_NONE_MATCH"))) {
            $tag = $this->getETag();
            $strong_compare = ! $request->isGetOrHead();
            if ($taglist) {
                if ($tag->matches($taglist, $strong_compare)) {
                    if ($request->isGetOrHead())
                        return _HTTP_VAL_NOT_MODIFIED;
                    else
                        return _HTTP_VAL_FAILED;
                }
                return _HTTP_VAL_MODIFIED;
            }
        }
        return _HTTP_VAL_PASS;
    }
}

    

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
