<?
   $pagename = $links;
   $pagehash = RetrievePage($dbi, $pagename);
?>
<html>
<head>
<title><? echo $pagename; ?>Links</title>
</head>

<body>

<form method="POST" action="<? echo $ScriptUrl; ?>">
<h1><? echo $pagename; ?> Links
<input type="submit" value=" Save ">
<input type="reset" value=" Reset "></h1>
[1] 
<input type="text" size="55" name="r1" value="<? echo $pagehash["r1"]; ?>">
<br>
[2]
<input type="text" size="55" name="r2" value="<? echo $pagehash["r2"]; ?>">
<br>
[3]
<input type="text" size="55" name="r3" value="<? echo $pagehash["r3"]; ?>">
<br>
[4]
<input type="text" size="55" name="r4" value="<? echo $pagehash["r4"]; ?>">
<p>
Type the full URL (http:// ...) for each reference cited in the text.<p>
<input type="hidden" size=1 name="post" value="<? echo $pagename; ?>">

</form>
</body>
</html>
