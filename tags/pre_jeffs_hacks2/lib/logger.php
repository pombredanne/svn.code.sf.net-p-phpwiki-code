<?php
rcs_id('$Id: logger.php,v 1.2 2001-05-31 17:41:55 dairiki Exp $');

class AccessLogEntry
{
   
   function AccessLogEntry () {
      global $REMOTE_HOST, $REMOTE_ADDR, $REMOTE_IDENT;
      global $REQUEST_METHOD, $REQUEST_URI, $SERVER_PROTOCOL;
      global $HTTP_REFERER, $HTTP_USER_AGENT;
      
      $this->host = empty($REMOTE_HOST) ? $REMOTE_ADDR : $REMOTE_HOST;
      $this->ident = empty($REMOTE_IDENT) ? '-' : $REMOTE_IDENT;
      $this->user = '-';
      $this->time = time();
      $this->request = "$REQUEST_METHOD $REQUEST_URI $SERVER_PROTOCOL";
      $this->status = 200;
      $this->size = 0;
      $this->referer = empty($HTTP_REFERER) ? '' : $HTTP_REFERER;
      $this->user_agent = empty($HTTP_USER_AGENT) ? '' : $HTTP_USER_AGENT;
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
		       $this->_ncsa_time(),
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

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
