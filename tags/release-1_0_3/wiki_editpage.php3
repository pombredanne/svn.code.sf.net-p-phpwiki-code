<?

/*
   This page is for editing a Wiki page.
   It relies on $pagename and $ScriptUrl;
   it will set $pagehash["text"]. 
*/

   if ($edit) {
      $pagename = $edit;
      $banner = "$pagename";
   } elseif ($copy) {
      $pagename = $copy;
      $banner = "Copy of $pagename";
   } else {
      echo "No page name passed into editpage!<br>\n";
      exit();
   }

   WikiHeader($pagename); 
?>

<form method="POST" action="<? echo "$ScriptUrl"; ?>">
<h1><? echo $banner, " "; ?>
<input type="submit" value=" Save ">
</h1>

<textarea name="text" ROWS="22" COLS="80" wrap="virtual"><?
   $pagehash = RetrievePage($dbi, $pagename);
   if (is_array($pagehash)) {
      echo implode($pagehash["text"], "\n");
      if (($pagehash["version"] > 1) &&
          ($pagehash["author"] != $remoteuser)) {
         $lastcopy = $pagename;
      } else {
         $lastcopy = false;
      }
   } else {
      echo "Describe $pagename here.";
      $lastcopy = false;
   }
?></textarea>
<br>

<input type="checkbox" name="convert" value="tabs" >
I can't type tabs.   Please
<a href="<? echo "$ScriptUrl"; ?>?ConvertSpacesToTabs">ConvertSpacesToTabs</a>
for me when I save.

<p>

<a href="<? echo "$ScriptUrl"; ?>?GoodStyle">GoodStyle</a>
tips for editing.

<br>

<a href="<? echo "$ScriptUrl"; ?>?links=<? echo $pagename; ?>">EditLinks</a>
to other web servers.

<br>

<?
   if ($lastcopy) {
      echo "<a href='$ScriptUrl?copy=$lastcopy'>EditCopy</a>";
      echo " from previous author";
   }

?>
<input type="hidden" size=1 name="post" value="<? echo $pagename; ?>">

</form>

<? WikiFooter(); ?>
