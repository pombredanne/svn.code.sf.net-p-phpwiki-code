<? rcs_id('$Id: wiki_display.php3,v 1.6.2.3 2000-07-29 00:36:45 dairiki Exp $');
   /*
      display.php3: render a page. This has all the display 
      logic in it, except for the search boxes.
   */

if (!($page = $dbi->retrievePage($pagename, $version))) {
  wiki_message('ERROR', 'BadVersion');
}
else {
  SetToken('Page', new PageTokens($page));
  SetToken('content', Template('BROWSE'));
  if ($page->version())
      $dbi->increaseHitCount($pagename);
}

?>
