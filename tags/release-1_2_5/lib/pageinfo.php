<!-- $Id: pageinfo.php,v 1.5.4.1 2005-01-07 13:48:42 rurban Exp $ -->
<!-- Display the internal structure of a page. Steve Wainstead, June 2000 -->
<?php
   if (get_magic_quotes_gpc()) {
      $info = stripslashes($info);
   }

   $encname = htmlspecialchars($info);
   $enter = gettext ("Enter a page name");
   $go = gettext ("Go");
   $html = "<form action=\"$ScriptUrl\" METHOD=GET>\n" .
	   "<input name=\"info\" value=\"$encname\">" .
	   " $enter\n" .
	   "<input type=submit value=$go><br>\n" .
	   "<input type=checkbox name=showpagesource";

   if (isset($showpagesource) && ($showpagesource == "on")) {
      $html .= " checked";
   }
   $html .= "> ";
   $html .= gettext ("Show the page source and references");
   $html .= "\n</form>\n";

   // don't bother unless we were asked
   if (! $info) {
      GeneratePage('MESSAGE', $html, gettext("PageInfo"), 0);
      exit;
   }

   function ViewpageProps($name, $pagestore)
   {
      global $dbi, $showpagesource, $datetimeformat, $FieldSeparator;

      $pagehash = RetrievePage($dbi, $name, $pagestore);
      if ($pagehash == -1) {
         $table = sprintf (gettext ("Page name '%s' is not in the database"),
		$name) . "\n";
      }
      else {
	 $table = "<table border=1 bgcolor=white>\n";

	 while (list($key, $val) = each($pagehash)) {
	    if ($key > 0 || !$key) #key is an array index
	       continue;
            if ((gettype($val) == "array") && ($showpagesource == "on")) {
               $val = implode($val, "$FieldSeparator#BR#$FieldSeparator\n");
	       $val = htmlspecialchars($val);
	       $val = str_replace("$FieldSeparator#BR#$FieldSeparator", "<br>", $val);
            }
	    elseif (($key == 'lastmodified') || ($key == 'created'))
	       $val = date($datetimeformat, $val);
	    elseif (gettype($val) != "array")
	       $val = htmlspecialchars($val);

            $table .= "<tr><td>$key</td><td>$val</td></tr>\n";
	 }

	 $table .= "</table>";
      }
      return $table;
   }

   $html .= "<P><B>";
   $html .= gettext ("Current version");
   $html .= "</B></p>";
   // $dbi = OpenDataBase($WikiPageStore);   --- done by index.php
   $html .= ViewPageProps($info, $WikiPageStore);

   $html .= "<P><B>";
   $html .= gettext ("Archived version");
   $html .= "</B></p>";
   // $dbi = OpenDataBase($ArchivePageStore);
   $html .= ViewPageProps($info, $ArchivePageStore);

   GeneratePage('MESSAGE', $html, gettext("PageInfo").": '$info'", 0);
?>
