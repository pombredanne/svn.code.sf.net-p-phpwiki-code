<!-- $Id: test_dbmlib.php3,v 1.3 2000-06-14 03:25:23 wainstead Exp $ -->
<html>
<head>
<title>Test bed for database library</title>
</head>

<body>

<?
   include "wiki_config.php3";
   
   // OpenDataBase()
   // Try to open the database
   //
   $dbi = OpenDataBase($WikiDataBase); 
   echo "Result from OpenDataBase($WikiDataBase):<br>", 
   "dbc: ", $dbi['dbc'], "<br>table: ",  $dbi['table'], "<br>\n";

?>

<hr>

<?

   // IsWikPage()
   // Test for pages to see if they are there
   //
   $pagename = "TestPage";
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

<?

   // RetrievePage()
   // Retrieve a page; should handle successful 
   // retrieves and failed retrieves
   //

   $pagename = "TestPage";
   echo "Retrieving page '$pagename'<br>\n";
   $pagehash = RetrievePage($dbi, $pagename);
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
   $pagehash = RetrievePage($dbi, $pagename);
   if ($pagehash == -1) {
      echo "<DD>SUCCESS: ";
      echo "RetrievePage($pagename) returned -1<p>\n";
   } else {
      echo "<DD>FAILED: ";
      echo "RetrievePage($pagename) returned '$pagehash'<p>\n";
   }


?>


<hr>

<?

   // CloseDataBase()
   // try to close the database
   //

   $res = CloseDataBase($dbi);
   echo "Result from close: '$res'\n";

?>
