<? rcs_id('$Id: wiki_savepage.php3,v 1.13.2.2 2000-07-29 00:36:45 dairiki Exp $');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/

    
function wiki_savepage($pagename)
{
  global $remoteuser, $dbi;
  // Form parameters
  global $content, $editversion, $noarchive, $convert, $refs;
  
  $prev = $dbi->getPage($pagename);
  if (!isset($editversion) || ($editversion != $prev->version()))
      return wiki_message('EDITPROBLEM', 'ConcurrentUpdates');
  if ($prev->isLocked() && WIKI_ADMIN != 'yes')
      return wiki_message('EDITPROBLEM', 'PageLocked');
      
  $pagehash["author"] = $remoteuser;
  if ($prev->author() != $pagehash["author"])
      $noarchive = false;

  if (empty($content))
      $pagehash['content'] = $prev->packedContent();
  else
    {
      // patch from Grant Morgan <grant@ryuuguu.com> for magic_quotes_gpc
      $pagehash['content'] = strip_magic_quotes_gpc($content);
      // convert spaces to tabs at user request
      if ($convert)
         $pagehash["content"] = CookSpaces($pagehash["content"]);
   }

  if (!is_array($refs))
      $pagehash['refs'] = $prev->refs();
   else
     {
       for ($i = 1; $i <= NUM_LINKS; $i++)
	 {
	   $ref = trim(strip_magic_quotes_gpc($refs[$i]));
	   if (preg_match('#^' . SAFE_URL_REGEXP . '$#', $ref))
	     {
	       $pagehash['refs'][$i] = $ref;
	       $refs[$i] = '';
	     }
	 }
     }

  $page = new WikiPage($pagename, $pagehash);

  $is_changed = ( $page->packedContent() != $prev->packedContent()
                  || serialize($page->refs()) != serialize($prev->refs()) );
  
  if ($is_changed)
    {
      if ($dbi->insertPage($page, $noarchive))
	  UpdateRecentChanges($dbi, $pagename, $prev->version());
    }

  SetToken('Page', new PageTokens($page));
  SetToken('BadRefs', new RefIteratorTokens($refs));
  SetToken('TempDatabaseWarning', $dbi->isInTmp() ? 'yes' : '');
  SetToken('PageUnchanged', $is_changed ? '' : 'yes');

  SetToken('content', Template('SAVE'));
}
wiki_savepage($pagename);

?>
