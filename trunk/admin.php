<?php // $Id: admin.php,v 1.4 2000-11-09 16:29:10 ahollosi Exp $

   function rcs_id($id) {}   // otherwise this gets in the way

   define('WIKI_ADMIN', true);	// has to be before includes

   include("lib/config.php");
   include("lib/stdlib.php");

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
      Header("WWW-Authenticate: Basic realm=\"PhpWiki\"");
      Header("HTTP/1.0 401 Unauthorized");
      echo "You entered an invalid login or password.\n";
      exit;
   }

   // All requests require the database
   $dbi = OpenDataBase($WikiPageStore);

   if (isset($lock) || isset($unlock)) {
      include ('admin/lockpage.php');
   } elseif (isset($zip)) {
      include ('lib/ziplib.php');
      include ('admin/zip.php');
      ExitWiki('');
   } elseif (isset($dumpserial)) {
      include ('admin/dumpserial.php');
   } elseif (isset($loadserial)) {
      include ('admin/loadserial.php');
   } elseif (isset($remove)) {
      if (get_magic_quotes_gpc())
         $remove = stripslashes($remove);
      if (function_exists('RemovePage')) {
         $html .= "You are about to remove '" . htmlspecialchars($remove)
	       . "' permanently!<P>Click <A HREF=\"$ScriptUrl?removeok="
	       . rawurlencode($remove) . "\">here</A> to remove the page now."
	       . "<P>Otherwise press the \"Back\" button of your browser.";
      } else {
         $html = "Function not yet implemented.";
      }
      GeneratePage('MESSAGE', $html, 'Remove page', 0);
      ExitWiki('');
   } elseif (isset($removeok)) {
      if (get_magic_quotes_gpc())
	 $removeok = stripslashes($removeok);
      RemovePage($dbi, $removeok);
      $html = "Removed page '".htmlspecialchars($removeok)."' successfully.'";
      GeneratePage('MESSAGE', $html, 'Removed page', 0);
      ExitWiki('');
   }

   include('index.php');
?>
