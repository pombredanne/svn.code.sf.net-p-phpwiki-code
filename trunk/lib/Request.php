<?php rcs_id('$Id: Request.php,v 1.25 2003-02-16 04:55:54 dairiki Exp $');

// FIXME: write log entry.

class Request {
        
    function Request() {
        $this->_fix_magic_quotes_gpc();
        $this->_fix_multipart_form_data();
        
        switch($this->get('REQUEST_METHOD')) {
        case 'GET':
        case 'HEAD':
            // $this->sanify_input_array(&$GLOBALS['HTTP_GET_VARS']);
            $this->args = &$GLOBALS['HTTP_GET_VARS'];
            break;
        case 'POST':
            // $this->sanify_input_array(&$GLOBALS['HTTP_POST_VARS']);
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
    
    function debugVars() {
        $array = array();
        foreach (array('start_debug','debug_port','debug_no_cache') as $key) {
            if (isset($GLOBALS['HTTP_GET_VARS'][$key]))
                $array[$key] = $GLOBALS['HTTP_GET_VARS'][$key];
        }
        return $array;
    }


    // Well oh well. Do we really want to pass POST params back as GET?
    function getURLtoSelf($args = false, $exclude = array()) {
        $get_args = $this->args;
        if ($args)
            $get_args = array_merge($get_args, $args);
        if (defined('DEBUG'))
            $get_args = array_merge($get_args, $this->debugVars());

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
    
    function redirect($url) {
        header("Location: $url");
        if (isset($this->_log_entry))
            $this->_log_entry->setStatus(302);
    }

    /** Add a (key,val) pair to the ETag hash.
     *
     * Request with identical (key,val) sets have identical ETags.
     *
     * The idea is that things like plugins which can produce dynamic
     * output should each add a value to the ETag (each using their own
     * key...)  See the RecentChanges plugin for an example.
     */
    function addToETag($key, $val) {
        $this->_etag[$key] = $val;
    }

    function hasETag() {
        return !empty($this->_etag);
    }
    
    /** Specify that the ETag for this response is a weak ETag.
     */
    function setETagIsWeak() {
        $this->_etag_is_weak = true;
    }

    /** Set Last-Modified time for this request.
     *
     * If a more recent time has already been set, this has
     * no effect.   (The most recent modification time takes
     * precedence.)
     */
    function setModificationTime($mtime) {
        if (!isset($this->_mtime))
            $this->_mtime = $mtime;
        else
            $this->_mtime = max($this->_mtime, $mtime);
    }
    
    /** Set (and check) validators for this response.
     *
     * This sets the appropriate "Last-Modified" and "ETag"
     * headers in the HTTP response.
     *
     * Additionally, if the validators match any(all) conditional
     * headers in the HTTP request, this method will not return, but
     * instead will send "304 Not Modified" or "412 Precondition
     * Failed" (as appropriate) back to the client.
     */
    function setValidators() {
        $tag = $mtime = false;
        
        if (isset($this->_etag)) {
            $tag = new HTTP_ETag($this->_etag, !empty($this->_etag_is_weak));
            header("ETag: " . $tag->asString());
        }
        if (isset($this->_mtime)) {
            $mtime = $this->_mtime;
            header("Last-Modified: " . Rfc1123DateTime($mtime));
        }
        
        if ($tag || $mtime) {
            
            // You can set CACHE_CONTROL_MAX_AGE (in index.php) if you want
            // to allow browsers (and proxies) to be able to cache pages.
            // (it should be set to the maximum allowed page staleness,
            // in seconds.)
            //
            // Its probably not advisable to set CACHE_CONTROL_MAX_AGE to
            // a non-zero value, as it most likely will result in stale
            // pages after editing, and other insidious problems.
            
            $max_age = defined(CACHE_CONTROL_MAX_AGE) ? CACHE_CONTROL_MAX_AGE : 0;

            if ($max_age > 0)
                $cache_control = sprintf("max-age=%d", $max_age);
            else {
                $cache_control = "must-revalidate";
                $max_age = -20;
            }
            header("Cache-Control: $cache_control");
            header("Expires: " . Rfc1123DateTime(time() + $max_age));

            $this->_checkValidators($mtime, $tag); // may not return (!)
        }
    }
    
    /** Check HTTP 1.1 (RFC 2616) validators for this response.
     *
     * If the validators match the "If-(Un)Modified-Since", and/or
     * "If-(None-)Match" headers in the HTTP request, this function
     * will not return, and will cause a "304 Not Modified"
     * (or "412 Precondition Failed" as appropriate)
     * response to be sent.
     */
    function _checkValidators($mtime=false, $tag=false) {
        $request_method = $this->get('REQUEST_METHOD');
        $is_get_or_head = ($request_method == "GET" or $request_method == "HEAD");
        $conditional = false;
        $test_failed = false;
        
        if ($mtime !== false) {
            if (($since = $this->get("HTTP_IF_UNMODIFIED_SINCE")) !== false) {
                if ($mtime > ParseRfc1123DateTime($since))
                    $this->conditionFailed(412);
            }
            if ($is_get_or_head
                and ($since = $this->get("HTTP_IF_MODIFIED_SINCE")) !== false) {
                if ($mtime > ParseRfc1123DateTime($since))
                    $test_failed = true;
                $conditional = true;
            }
        }
        if ($tag !== false) {
            if (($taglist = $this->get("HTTP_IF_MATCH")) !== false) {
                if (! $tag->matches($taglist, 'strong'))
                    $this->conditionFailed(412);
            }
            if (($taglist = $this->get("HTTP_IF_NONE_MATCH")) !== false) {
                $strong_compare = ! $is_get_or_head;
                if (! $tag->matches($taglist, $strong_compare))
                    $test_failed = true;
                elseif (! $is_get_or_head)
                    $this->conditionFailed(412);
                $conditional = true;
            }
        }

        if ($conditional && !$test_failed)
            $this->conditionFailed(304);
    }

    function conditionFailed($status) {
        $this->setStatus($status);
        print "\n\n";
        $this->finish();
        exit();
    }
    
    function setStatus($status) {
        if (preg_match('|^HTTP/.*?\s(\d+)|i', $status, $m)) {
            header($status);
            $status = $m[1];
        }
        else {
            $status = (integer) $status;
            $reasons = array('200' => 'OK',
                             '302' => 'Found',
                             '304' => 'Not Modified',
                             '400' => 'Bad Request',
                             '401' => 'Unauthorized',
                             '403' => 'Forbidden',
                             '404' => 'Not Found',
                             '412' => 'Precondition Failed');
            header(sprintf("HTTP/1.1 %d %s", $status, $reason[$status]));
        }

        if (isset($this->_log_entry))
            $this->_log_entry->setStatus($status);
    }

    function compress_output() {
        if ( function_exists('ob_gzhandler')
             && function_exists('version_compare') /* (only in php >= 4.1.0) */
             && version_compare(phpversion(), '4.2.3', ">=")
             ){
            ob_start('ob_gzhandler');
            $this->_is_compressing_output = true;
        }
    }

    function finish() {
        if (!empty($this->_is_compressing_output))
            ob_end_flush();
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

    // Fixme: Seperate into fields of type: displayed, db, internal, password, ...
    // and define for each type the allowed characters.
    function sanify_input_array (&$arr) {
        if (!empty($arr)) {
            foreach (array_keys($arr) as $key) {
                if (!in_array($key,array('edit','password')))
                    $arr[$key] = $this->sanify_userinput($arr[$key]);
            }
        }
    }

    function sanify_userinput ($var) {
        // Prevent possible XSS attacks (cross site scripting attacks)
        // See http://www.cert.org/advisories/CA-2000-02.html, http://www.perl.com/pub/a/2002/02/20/css.html
        // <script> tags, ...
        // /wiki/?pagename=<script>alert(document.cookie)</script>
        if (is_string($var)) {
            return strip_tags($var);
        } elseif (is_array($var)) {
            $this->sanify_input_array($var);
            return $var;
        } else {
            return $var;
        }
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
        if (ini_get('register_globals')) {
            // This is funky but necessary, at least in some PHP's
            $GLOBALS[$key] = $val;
        }
        $vars[$key] = $val;
        session_register($key);
    }
    
    function delete($key) {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (ini_get('register_globals'))
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

        if (is_array($val)) {
            // If val is a dict, hash it...
            ksort($val);
            $val = md5(serialize($val));
        }

        $this->_val = (string)$val;
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


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
