<?
   /*
      Constants and settings. Edit the values below for
      your site. You need two image files, a banner and 
      a signature. The dbm file MUST be writable by the
      web server or this won't work. If you configure your
      server to allow index.php3 as an index file, you 
      can just give the URL without the script name.
   */

   // You should set the $ServerAddress as below, and comment out
   // the if/else below.
   //$ServerAddress = "http://wcsb.org:8080/~swain/php/wiki/";

   if (preg_match("#(.*?)([^/]*$)#", $REQUEST_URI, $matches)) {
      $ServerAddress = "http://$SERVER_NAME:$SERVER_PORT" . $matches[1];
   } else {
      $ServerAddress = "http://$SERVER_NAME:$SERVER_PORT$REQUEST_URI";
   }

   $ScriptName = "index.php3";
   $WikiDataBase = "/tmp/wikidb"; // must be server-writable!
   $ArchiveDataBase = "/tmp/wikiarchive"; // see above!
   $SignatureImg = "$ServerAddress/signature.png";
   $logo = "wikibase.png";

   // you shouldn't have to edit anyting below this line

   $ScriptUrl = $ServerAddress . $ScriptName;
   $LogoImage = "<img src='${ServerAddress}$logo' border='0'>";
   $LogoImage = "<a href='$ScriptUrl'>$LogoImage</a>";

   $FieldSeparator = "\263";

   // Apache won't show REMOTE_HOST unless the admin configured it
   // properly. We'll be nice and see if it's there.
   empty($REMOTE_HOST) ?
      ($remoteuser = $REMOTE_ADDR) : ($remoteuser = $REMOTE_HOST);


   // number of user-defined external links, i.e. "[1]"
   define("NUM_LINKS", 4);

   // try this many times if the dbm is unavailable
   define("MAX_DBM_ATTEMPTS", 20);

   // constants used for HTML output. List tags like UL and 
   // OL have a depth of one, PRE has a depth of 0.
   define("ZERO_DEPTH", 0);
   define("SINGLE_DEPTH", 1);


?>
