<?php
   // Backlinks: returns pages which link to a given page.
   rcs_id('$Id: backlinks.php,v 1.1.2.1 2001-08-18 00:35:10 dairiki Exp $');


   if(get_magic_quotes_gpc())
      $refs = stripslashes($refs);
   $pagename = $refs;

   // No HTML markup allowed in $title.    
   $title = sprintf(gettext("Pages which link to %s"),
		    htmlspecialchars($pagename));
   if (IsWikiPage($dbi, $pagename))   
      $pagelink = LinkExistingWikiWord($pagename);
   else   
      $pagelink = LinkUnknownWikiWord($pagename);
      
   $html = ( "<p><b>"
	     . sprintf(gettext("Pages which link to %s") . " .....",
		       $pagelink)
	     . "</b></p>\n<ul>\n" );

   // search matching pages
   $query = InitBackLinkSearch($dbi, $pagename);
   $found = 0;
   while ($page = BackLinkSearchNextMatch($dbi, $query)) {
      $found++;
      $html .= "<li>" . LinkExistingWikiWord($page) . "<br>\n";
   }

   $html .= "</ul>\n<hr noshade>\n"
	    . sprintf(gettext ("%d pages link to %s."),
		      $found, $pagelink)
	    . "\n";

   GeneratePage('MESSAGE', $html, $title, 0);
?>
