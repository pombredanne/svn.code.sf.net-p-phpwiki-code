<html>
<head>
<title>Importing phpwiki 1.0.x dbm files</title>
</head>
<body bgcolor="navajowhite">
<!-- $Id: wiki_port1_0.php,v 1.1.2.1.2.2 2005-01-07 13:59:57 rurban Exp $ -->

<?php
   function port1_0RenderHash($dbi, $dbmh, $pagename) {
      $pagehash = unserialize(dbmfetch($dbmh, $pagename));

	// array fields for pagehash 1.0
	// 'version', 'date' as string, 'author', 'text'
	 
      echo "$pagename<br>\n";

      $newhash['version'] = isset($pagehash['version']) ?
			    $pagehash['version'] : 1;
      $newhash['author'] = isset($pagehash['author']) ?
			    $pagehash['author'] : '1.0 wiki setup page';
      $newhash['created'] = time();
      $newhash['lastmodified'] = time();
      $newhash['flags'] = 0;
      $newhash['pagename'] = $pagename;
      $newhash['refs'] = array();
      for ($i=1; $i <= 4; $i++) {
	 if (isset($pagehash['r$i']))
	    $newhash['refs'][$i] = $pagehash['r$i'];
      }
      $content = implode("\n", $pagehash['text']);
      $content = str_replace("[", "[[", $content);
      $newhash['content'] = explode("\n", $content);

      InsertPage($dbi, $pagename, $newhash);
   }


   echo "opening dbm file: $portdbmfile ... \n";

   if (! file_exists($portdbmfile)) {
      echo "File '$portdbmfile' does not exist.<br>\n";
      exit;
   }

   if (! ($dbmh = dbmopen($portdbmfile, "r"))) {
      echo "Cannot open '$portdbmfile'<br>\n";
      exit;
   }

   echo " ok ($dbmh)<p>\n";

   $namelist = array();
   $ctr = 0;

   $namelist[$ctr] = $key = dbmfirstkey($dbmh);
   port1_0renderhash($dbi, $dbmh, $key);
   while ($key = dbmnextkey($dbmh, $key)) {
      $ctr++;
      $namelist[$ctr] = $key;
      port1_0renderhash($dbi, $dbmh, $key);
   }

   dbmclose($dbmh);
?>

<p><b>Done.</b>
</body>
</html>