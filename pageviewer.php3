<!-- $Id: pageviewer.php3,v 1.3 2000-06-18 15:08:53 ahollosi Exp $ -->
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

   function ViewpageProps($name)
   {
      global $dbi;

      $pagehash = RetrievePage($dbi, $name);
      if ($pagehash == -1) {
         echo "Page name '$name' is not in the database<br>\n";
         echo "(return code was -1)<br>\n";
         exit();
      }
      reset($pagehash);

      echo "<table border=1 bgcolor=white>\n";

      while (list($key, $val) = each($pagehash)) {
         if ($key == "text") {
            $val = implode($val, "<br>\n");
         }
         echo "<tr><td>$key</td><td>$val</td></tr>\n";
      }

      echo "</table>";
   }

   echo "<P><B>Current version</B></p>";
   $dbi = OpenDataBase($WikiDataBase);
   ViewPageProps($pagename);

   echo "<P><B>Archived version</B></p>";
   $dbi = OpenDataBase($ArchiveDataBase);
   ViewPageProps($pagename);
?>

</body></html>

