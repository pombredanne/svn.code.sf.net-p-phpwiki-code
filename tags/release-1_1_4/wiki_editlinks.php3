<?
   // Thanks to Alister <alister@minotaur.nu> for this code.
   // This allows an arbitrary number of reference links.

   $pagename = rawurldecode($links);
   $pagehash = array();
   $pagehash = RetrievePage($dbi, $pagename);
   settype ($pagehash, 'array');
   for ($i = 1; $i <= NUM_LINKS; $i++)
   	  if (!isset($pagehash['r'.$i]))
   	  		$pagehash['r'.$i] = '';
   	  	
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

<?
   for ($i = 1; $i <= NUM_LINKS; $i++) {
   	   echo "[$i] <input type='text' size='55' name='r$i' value='".
                $pagehash["r$i"] ."'><br>\n";
   }

?>
<p>
Type the full URL (http:// ...) for each reference cited in the
text.<p>
<input type="hidden" size=1 name="post"
value="<? echo rawurlencode($pagename); ?>">

</form>
</body>
</html>
