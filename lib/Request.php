<?php rcs_id('$Id: Request.php,v 1.1 2001-09-18 19:16:23 dairiki Exp $');

// FIXME: write log entry.

class Request {
        
    function Request() {
        
        $this->_fix_magic_quotes_gpc();

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

        $this->_log_entry = new Request_AccessLogEntry($this);
        
        $TheRequest = $this;
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

    function setArg($key, $val) {
        $this->args[$key] = $val;
    }

    
    function redirect($url) {
        header("Location: $url");
        $this->_log_entry->setStatus(302);
    }

    function compress_output() {
        if (function_exists('ob_gzhandler')) {
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
}

class Request_SessionVars {
    function Request_SessionVars() {
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
        if (!is_uploaded_file($fileinfo['temp_name']))
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
            // Dump http headers.
            while ( ($header = fgets($fd, 4096)) )
                if (trim($header) == '')
                    break;
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

class Request_AccessLogEntry
{
   function AccessLogEntry ($request) {
      $this->host = $req->get('REMOTE_HOST');
      $this->ident = $req->get('REMOTE_IDENT');
      if (!$this->ident)
          $this->ident = '-';
      $this->user = '-';
      $this->time = time();
      $this->request = join(' ', array($req->get('REQUEST_METHOD'),
                                       $req->get('REQUEST_URI'),
                                       $req->get('SERVER_PROTOCOL')));
      $this->status = 200;
      $this->size = 0;
      $this->referer = (string) $req->get('HTTP_REFERER');
      $this->user_agent = (string) $req->get('HTTP_USER_AGENT');
   }

   //
   // Returns zone offset, like "-0800" for PST.
   //
   function _zone_offset () {
      $offset = date("Z", $this->time);
      if ($offset < 0)
      {
	 $negoffset = "-";
	 $offset = -$offset;
      }
      $offhours = floor($offset / 3600);
      $offmins = $offset / 60 - $offhours * 60;
      return sprintf("%s%02d%02d", $negoffset, $offhours, $offmins);
   }
  
   // Format time into NCSA format.
   function _ncsa_time($time = false) {
      if (!$time)
	 $time = time();

      return date("d/M/Y:H:i:s", $time) .
	 " " . $this->_zone_offset();
   }

   function write($logfile) {
      $entry = sprintf('%s %s %s [%s] "%s" %d %d "%s" "%s"',
		       $this->host, $this->ident, $this->user,
		       $this->_ncsa_time($this->time),
		       $this->request, $this->status, $this->size,
		       $this->referer, $this->user_agent);
      
      //Error log doesn't provide locking.
      //error_log("$entry\n", 3, $logfile);

      // Alternate method 
      if (($fp = fopen($logfile, "a")))
      {
	 flock($fp, LOCK_EX);
	 fputs($fp, "$entry\n");
	 fclose($fp);
      }
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
