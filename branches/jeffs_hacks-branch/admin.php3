<?
require('wiki_config.php3');
rcs_id('$Id: admin.php3,v 1.1.2.2 2000-08-01 18:20:51 dairiki Exp $');
if (1) {
   // set these to your preferences. For heaven's sake
   // pick a good password!
   $wikiadmin   = "";
   $adminpasswd = "";

   // Do not tolerate sloppy systems administration
   if (empty($wikiadmin) || empty($adminpasswd)) {
      echo "Set the administrator account and password first.\n";
      exit;
   }

   // from the manual, Chapter 16
   if (($PHP_AUTH_USER != $wikiadmin  )  ||
       ($PHP_AUTH_PW   != $adminpasswd)) {
      Header("WWW-Authenticate: Basic realm=\"My Realm\"");
      Header("HTTP/1.0 401 Unauthorized");
      echo "You entered an invalid login or password.\n";
      exit;
   }
}

define('WIKI_ADMIN', 'yes');
$TemplateVars['Administrator'] = 'true';

include('index.php3');
?>
