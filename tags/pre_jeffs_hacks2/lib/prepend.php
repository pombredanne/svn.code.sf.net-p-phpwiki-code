<?php
/* lib/prepend.php
 *
 * Things which must be done and defined before anything else.
 */
$RCS_IDS = '';
function rcs_id ($id) { $GLOBALS['RCS_IDS'] .= "$id\n"; }
rcs_id('$Id: prepend.php,v 1.3 2001-02-14 22:02:05 dairiki Exp $');

error_reporting(E_ALL);

define ('FATAL_ERRORS',
	E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
define ('WARNING_ERRORS',
	E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING);
define ('NOTICE_ERRORS', E_NOTICE | E_USER_NOTICE);

$PostponedErrorMask = 0;
$PostponedErrors = array();

function PostponeErrorMessages ($newmask = -1)
{
   global $PostponedErrorMask, $PostponedErrors;

   if ($newmask < 0)
      return $PostponedErrorMask;
   
   $oldmask = $PostponedErrorMask;
   $PostponedErrorMask = $newmask;

   $i = 0;
   while ($i < sizeof($PostponedErrors))
   {
      list ($errno, $message) = $PostponedErrors[$i];
      if (($errno & $newmask) == 0)
      {
	 echo $message;
	 array_splice($PostponedErrors, $i, 1);
      }
      else
	 $i++;
   }
   
   return $oldmask;
}

function ExitWiki($errormsg = false)
{
   static $exitwiki = 0;
   global $dbi;

   if($exitwiki)		// just in case CloseDataBase calls us
      exit();
   $exitwiki = 1;

   PostponeErrorMessages(0);	// Spew postponed messages.
   
   if(!empty($errormsg)) {
      print "<P><hr noshade><h2>" . gettext("WikiFatalError") . "</h2>\n";
      print $errormsg;
      print "\n</BODY></HTML>";
   }

   if (isset($dbi))
      CloseDataBase($dbi);
   exit;
}

function PostponeErrorHandler ($errno, $errstr, $errfile, $errline)
{
   global $PostponedErrorMask, $PostponedErrors;
   static $inHandler = 0;

   if ($inHandler++ != 0)
      return;			// prevent recursion.

   if (($errno & NOTICE_ERRORS) != 0)
      $what = 'Notice';
   else if (($errno & WARNING_ERRORS) != 0)
      $what = 'Warning';
   else
      $what = 'Fatal';

   $errfile = ereg_replace('^' . getcwd() . '/', '', $errfile);
   $message = sprintf("<br>%s:%d: <b>%s</b>[%d]: %s<br>\n",
		      htmlspecialchars($errfile),
		      $errline, $what, $errno,
		      htmlspecialchars($errstr));


   if ($what == 'Fatal')
   {
      PostponeErrorMessages(0);	// Spew postponed messages.
      echo $message;
      ExitWiki();
      exit -1;
   }
   else if (($errno & error_reporting()) != 0)
   {
      if (($errno & $PostponedErrorMask) != 0)
      {
	 $PostponedErrors[] = array($errno, $message);
      }
      
      else
	 echo $message;
   }

   $inHandler = 0;
}

set_error_handler('PostponeErrorHandler');

PostponeErrorMessages(E_ALL);

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>
