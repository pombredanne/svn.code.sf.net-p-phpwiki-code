<!-- $Id: test_dbmlib.php3,v 1.2 2000-06-14 01:02:41 wainstead Exp $ -->
<html>
<head>
<title>Test bed for database library</title>
</head>

<body>

<?
   include "wiki_pgsql.php3";
   
   $dbi = OpenDataBase("wiki"); 
   echo "Result from OpenDataBase: ", 
   $dbi['dbc'], " ",  $dbi['table'], "\n";

?>

<hr>

<?

   // puzzling results.
   $pagename = "TestPage";
   $res = IsWikiPage($dbi, $pagename);
   echo "Return code for $pagename: '$res'\n";
 
?>

<hr>

<?

   $res = CloseDataBase($dbi);
   echo "Result from close: '$res'\n";

?>
