<?
require('wiki_config.php3');
rcs_id('$Id: admin.php3,v 1.1.2.1 2000-07-29 00:36:45 dairiki Exp $');
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

$ScriptName = 'admin.php3';
$ScriptUrl = $ServerAddress . $ScriptName;
$TemplateVars['ScriptName'] = $ScriptName;
$TemplateVars['ScriptUrl'] = $ScriptUrl;
$TemplateVars['Administrator'] = 'true';

include('index.php3');
?>
