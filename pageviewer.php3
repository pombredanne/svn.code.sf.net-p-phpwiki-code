<!-- $Id: pageviewer.php3,v 1.1 2000-06-14 01:34:51 wainstead Exp $ -->
<!-- Display the internal structure of a page. Steve Wainstead, June 2000 -->
<html>
<head>
<title>PhpWiki page viewer</title>
</head>

<body bgcolor="navajowhite" text="navy">

<form>
<input type="text" name="pagename"> Enter a page name
</form>

<?

   // don't bother unless we were asked
   if (! $pagename) { exit; }

   include "wiki_config.php3";
   include "wiki_stdlib.php3";

   $dbi = OpenDataBase($WikiDataBase);
   $pagehash = RetrievePage($dbi, $pagename);

   reset($pagehash);
   
?>

<table border="1" bgcolor="white">

<?
   while (list($key, $val) = each($pagehash)) {
      if ($key == "text") {
         $val = implode($val, "<br>\n");
      }
      echo "<tr><td>$key</td><td>$val</td></tr>\n";
   }   
?>

</table>



