<?  rcs_id('$Id: wiki_dbmlib.php3,v 1.10.2.2 2000-07-30 22:05:38 dairiki Exp $');

class WikiDBMPage extends WikiPage
{
  function WikiDBMPage(&$dbi, $pagename, $hash = false) {
    if (is_array($hash))
	$this->hash = $hash;
    else
	$this->hash = array();
    
    $this->pagename = $pagename;
    $this->dbi = $dbi;
  }

  // Overrides method in WikiPage
  function links() {
    return $this->_get('links');
  }

  function _hasLinkTo($pagename) {
    $links = $this->_get('links');
    return isset($links[$pagename]);
  }

  function _prevkey() {
    return $this->_get('prevkey');
  }

  function _get($field) {
    if (!isset($this->hash[$field]))
      {
	if ($field == 'version')
	    return 0;
	else if (preg_match('/^(flags|created|hits|links|verkey)$/', $field))
	  {
	    // Optimization: don't need full page info.
	    Debug("RETRIEVE(partial): $this->pagename for $field");
	    $this->hash = $this->dbi->_fetchpage($this->pagename);
	  }
	else
	  {
	    Debug("RETRIEVE(full): $this->pagename for $field");
	    $page = $this->dbi->retrievePage($this->pagename,
	                                     $this->hash['version']);
	    $this->hash = $page->hash;
	  }
	if (!isset($this->hash[$field]))
	    die("Failed to _get() field '$field'");
      }
    return $this->hash[$field];
  }
}


class WikiDBMSearchIterator
{
  function WikiDBMSearchIterator (&$dbi, $search, $full_search = false) {
    $this->pages = $dbi->retrieveAllPages();
    $this->search = '/(' . preg_quote($search) . ')/i';
    $this->full_search = $full_search;
  }

  function next() {
    while ($page = $this->pages->next())
      {
	if (preg_match($this->search, $page->name()))
	    return $page;
	if ($this->full_search)
	  {
	    if (preg_match($this->search, $page->packedContent()))
		return $page;
	  }
      }
    return false;
  }
}

class WikiDBMBackLinkIterator
{
  function WikiDBMBackLinkIterator (&$dbi, $linkname) {
    $this->pages = $dbi->retrieveAllPages();
    $this->linkname = $linkname;
  }

  function next() {
    while ($page = $this->pages->next())
	if ($page->_hasLinkTo($this->linkname))
	    return $page;
    return false;
  }
}


class WikiDBMListIterator
{
  function WikiDBMListIterator ($list) {
    $this->list = $list;
    $this->i = 0;
  }

  function next() {
    return $this->list[$this->i++];
  }
}

class WikiDBMPageIterator
{
  function WikiDBMPageIterator ($dbi, $pages) {
    $this->dbi = $dbi;
    $this->pages = $pages;
    $this->i = 0;
  }

  function next() {
    if ($pagename = $this->pages[$this->i++])
	return new WikiDBMPage($this->dbi, $pagename);
    return false;
  }
}

class WikiDBMPageVersionIterator
{
  function WikiDBMPageVersionIterator (&$dbi, $pagename) {
    $this->dbi = $dbi;
    $this->pagename = $pagename;
    $this->pagehash = $dbi->_fetchpage($pagename);
    if ($this->pagehash)
	$this->verkey = $this->pagehash['verkey'];
  }

  function next() {
    if (!$this->verkey)
	return false;
    $ver = $this->dbi->_fetchver($this->verkey);
    if (!isset($this->pagehash['latestversion']))
	$this->pagehash['latestversion'] = $ver['version'];
	
    $hash = $this->pagehash;
    $this->verkey = $ver['prevkey'];
    while (list ($key, $val) = each($ver))
	$hash[$key] = $val;
    return new WikiDBMPage($this->dbi, $this->pagename, $hash);
  }
}

class WikiDBMDataBase extends WikiDataBase
{
  var $db;			// dbm handles.

  function _crapout ($message) {
    echo htmlspecialchars($message) . ", giving up.<br>";
    exit();
  }

  function _opendb($filename) {
    while (($dbh = @dbmopen($filename, "c")) < 1) 
      {
	if ($numattempts++ > MAX_DBM_ATTEMPTS)
	    $this->_crapout("Cannot open database $filename");
	sleep(1);
      }
    return $dbh;
  }

  function _fetchpage($pagename) {
    $val = dbmfetch($this->pagedb, $pagename);
    return $val ? unserialize($val) : false;
  }

  function _fetchver ($key) {
    $val = dbmfetch($this->verdb, $key);
    return $val ? unserialize($val) : false;
  }

  var $_spaces = "                                                           ";
  function _store ($db, $key, $val) {
    $val = serialize($val);

    $npad = (500 - (strlen($val) % 500)) % 500;
    while (strlen($this->_spaces) < $npad)
	$this->_spaces .= $this->_spaces;

    dbmreplace($db, $key, $val . substr($this->_spaces, 0, $npad));
  }


  // Open the database.
  function WikiDBMDataBase ($params) {
    $this->dbfile = ( isset($params['dbfile'])
		      ? $params['dbfile']
		      : '/tmp/phpwiki' );
    $this->pagedb = $this->_opendb($this->dbfile . ".page");
    $this->verdb  = $this->_opendb($this->dbfile . ".vers");
  }

  function close() {
    dbmclose($this->pagedb);
    dbmclose($this->verdb);
  }

  function isInTmp() {
    return preg_match(':^/tmp/:', $this->dbfile);
  }
  
  // Return hash of page + attributes or default
  // If VERSION == 0, get most recent version.
  // If VERSION > 0, get specified version.
  function retrievePage($pagename, $version = 0) {
    $iter = new WikiDBMPageVersionIterator($this, $pagename);
    do
      {
	$page = $iter->next();
      }
    while ($page && $version && $version != $page->version());

    return $page;
  }

  // Save a new version of a page.
  function insertPage(&$page, $no_backup = false) {
    $pagename = $page->name();
    
    if (!($pg = $this->_fetchpage($pagename)))
      {
	// Page does not exist.
	$pg = array('flags' => $page->flags(),
		    'created' => $page->created(),
		    'hits' => $page->hits(),
		    'verkey' => "1|$pagename");
	$ver = array('version' => max($page->version(), 1),
		     'prevkey' => false);

	if (isset($this->_npages))
	    $this->_npages++;
      }
    else
      {
	$ver = $this->_fetchver($pg['verkey']);
	$ver['version'] = max($page->version(), $ver['version'] + 1);
	
	if (!$no_backup)
	  {
	    $ver['prevkey'] = $pg['verkey'];
	    $pg['verkey'] = ($pg['verkey'] + 1) . "|$pagename";
	  }
      }
    
    $ver['content'] = $page->packedContent();
    $ver['refs'] = $page->refs();
    $ver['author'] = $page->author();
    $ver['lastmodified'] = $page->lastmodified();

    $pg['links'] = $page->links();  // FIXME: inefficient key == val

    $this->_store($this->pagedb, $pagename, $pg);
    $this->_store($this->verdb, $pg['verkey'], $ver);

    return $ver['version'];
  }

  function retrieveAllVersions($pagename) {
    return new WikiDBMPageVersionIterator($this, $pagename);
  }

  function nPages() {
    if (!isset($this->_npages))
      {
	$start = utime();
	Debug("WikiDBMDataBase:nPages():  COUNTING PAGES");
	// retrieveAllPages() caches the page count in $this->_npages;
	$pages = $this->retrieveAllPages();
	Debug(sprintf("PAGE COUNT took %f seconds", utime() - $start));
      }
    return $this->_npages;
  }

  function previousVersion($pagename, $version = 0) {
    if ($version < 0) die("bad arg");

    //FIXME: optimize?
    if (!($page = $this->retrievePage($pagename, $version)))
	return false;
    
    if ( $page->version() && ($pkey = $page->_prevkey()) )
      {
	$pver = $this->_fetchver($pkey);
	return $pver['version'];
      }
    return false;
  }

  function isWikiPage($pagename) {
    return dbmexists($this->pagedb, $pagename);
  }

  function setFlags($pagename, $flags) {
    $page = $this->_fetchpage($pagename);
    if (!$page)
	$this->_crapout("Can't set flags on non-existing page $pagename");
    $page['flags'] = $flags;
    $this->_store($this->pagedb, $pagename, $page);
  }
  
  function increaseHitCount($pagename) {
    $page = $this->_fetchpage($pagename);
    if (!$page)
	$this->_crapout("Can't bump hits on non-existing page $pagename");
    $page['hits']++;
    $this->_store($this->pagedb, $pagename, $page);
  }

  // setup for title-search
  function titleSearch($search) {
    return new WikiDBMSearchIterator($this, $search);
  }

  // setup for title-search
  function backLinks($pagename) {
    return new WikiDBMBackLinkIterator($this, $pagename);
  }

  // setup for full-text search
  function fullSearch($search) {
    return new WikiDBMSearchIterator($this, $search, 'full');
  }

  function mostPopular($limit = 20) {
    $pages = $this->retrieveAllPages();
    $hitlist = array();
    while ($page = $pages->next())
	$hitlist[$page->name()] = $page->hits();

    arsort($hitlist);
    $list = array();
    while ($limit-- > 0 && (list ($pagename, $hits) = each($hitlist)) && $hits)
	$list[] = new WikiDBMPage($this, $pagename, array('hits' => $hits));
    return new WikiDBMListIterator($list);
  }

  function retrieveAllPages() {
    $db = $this->pagedb;
    $pages = array();
    for ($page = dbmfirstkey($db); $page; $page = dbmnextkey($db, $page))
	$pages[] = $page;
    $this->_npages = sizeof($pages);
    sort($pages);
    return new WikiDBMPageIterator($this, $pages);
  }
}

?>
