<? rcs_id('$Id: wiki_editpage.php3,v 1.10.2.2 2000-07-29 00:36:45 dairiki Exp $');
function wiki_edit ($action, $pagename, $version, $remoteuser)
{
  global $dbi;

  // editpage relies on $pagename and $ScriptUrl
  if (!$pagename)
      die("No page name passed into editpage!");
  
  if (!($page = $dbi->getPage($pagename, $version)))
      return wiki_message('ERROR', 'BadVersion');

  if ($page->isLocked() && WIKI_ADMIN != 'yes')
      return wiki_message('EDITPROBLEM', 'PageLocked');


  $is_oldversion = $page->version() != $page->latestversion();
  $force_backup = $is_oldversion || $page->author() != $remoteuser;
  $noarchive = time() - $page->lastmodified() < 24 * 3600;
  $notabs = preg_match('/Windows/i', $HTTP_USER_AGENT);

  SetToken('Page', new PageTokens($page));
  SafeSetToken(array(
      'NoArchiveChecked' => $noarchive ? 'checked' : '',
      'NoTabsChecked' => $notabs ? 'checked' : '',
      'ForceBackup' => $force_backup ? 'yes' : ''
      ));

  SetToken('content', Template(strtoupper($action)));
}

wiki_edit($action, $pagename, $version, $remoteuser);
?>
