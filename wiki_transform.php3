<? rcs_id('$Id: wiki_transform.php3,v 1.13.2.1 2000-07-21 18:29:07 dairiki Exp $');

   // expects $pagehash and $html to be set
   $renderer = new WikiPageRenderer;
   $html .= $renderer->render_page($pagehash);
?>
