<!-- $Id: test_dbmlib.php3,v 1.8 2000-10-20 11:42:52 ahollosi Exp $ -->
<html>
<head>
<title>Test bed for database library</title>
</head>

<body>

<?php
   include "wiki_config.php3";
   
   // OpenDataBase()
   // Try to open the database
   //
   $dbi = OpenDataBase($WikiPageStore);

   if ($dbi) {
      $vartype = gettype($dbi);
      echo "Return type from OpenDataBase($WikiPageStore): $vartype<br>\n";
      if ($vartype == 'array') {
         reset($dbi);
         while (list($key, $val) = each($dbi)) {
            echo "<dd>$key : $val</dd>\n";
         }
      } else {
         echo "Return value: $dbi <p>\n";
      }
   } else {
      echo "Database open failed: return value '$dbi' <br>\n";
   }

?>

<hr>

<?php

   // IsWikPage()
   // Test for pages to see if they are there
   //
   $pagename = gettext ("TestPage");
   echo "Testing for existence of $pagename<br>\n";
   $res = IsWikiPage($dbi, $pagename);
   if ($res) {
      echo "<DD>SUCCESS: ";
      echo "Return code for $pagename: '$res' <p>\n";
   } else {
      echo "<DD>FAILED: ";
      echo "PAGE NOT FOUND! (return code '$res')<p>\n";
   }

   $pagename = "pageThatDoesNotExist";
   echo "Testing for existence of $pagename<br>\n";
   $res = IsWikiPage($dbi, $pagename);
   if ($res) {
      echo "<DD>FAILED: ";
      echo "FOUND NONEXISTENT PAGE $pagename! (return code '$res')<p>\n";
   } else {
      echo "<DD>SUCCESS: ";
      echo "Returned false (test passed, return code '$res')<p>\n";
   }

 
?>

<hr>

<?php

   // RetrievePage()
   // Retrieve a page; should handle successful 
   // retrieves and failed retrieves
   //

   $pagename = gettext ("TestPage");
   echo "Retrieving page '$pagename'<br>\n";
   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);
   $type = gettype($pagehash);
   if ($type == "array") {
      echo "<DD>SUCCESS: ";
      echo "RetrievePage($pagename) returned type '$type'<p>\n";
   } else {
      echo "<DD>FAILED: ";
      echo "RetrievePage($pagename) returned type '$type'<p>\n";
   }

   $pagename = "thisIsAPageThatIsNotThere";
   echo "Retrieving page '$pagename'<br>\n";
   $pagehash = RetrievePage($dbi, $pagename, $WikiPageStore);
   if ($pagehash == -1) {
      echo "<DD>SUCCESS: ";
      echo "RetrievePage($pagename) returned -1<p>\n";
   } else {
      echo "<DD>FAILED: ";
      echo "RetrievePage($pagename) returned '$pagehash'<p>\n";
   }


?>


<hr>

<?php

   // CloseDataBase()
   // try to close the database
   //

   $res = CloseDataBase($dbi);
   echo "Result from close: '$res'\n";

?>
