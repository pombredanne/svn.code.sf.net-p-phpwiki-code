<!-- $Id: test_dbmlib.php3,v 1.1 2000-06-12 04:11:47 wainstead Exp $ -->
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

   $res = CloseDataBase($dbi);
   echo "Result from close: '$res'\n";

?>


