<? rcs_id('$Id: wiki_dblib.php3,v 1.1.2.1 2000-07-29 00:36:45 dairiki Exp $');

require('wiki_renderlib.php3');

class WikiPage 
{
  function WikiPage($pagename, $hash = false) {
    $this->pagename = $pagename;

    $this->hash = $this->_default_hash($pagename);

    if (is_array($hash))
      {
	$this->_canonify_content($hash);
	reset($hash);
	while (list ($key, $val) = each($hash))
	    $this->hash[$key] = $val;
      }
  }

  function name () {
    return $this->pagename;
  }
  function author() {
    return $this->_get('author');
  }
  function created() {
    return $this->_get('created');
  }
  function lastmodified() {
    return $this->_get('lastmodified');
  }
  function flags() {
    return $this->_get('flags');
  }
  function isLocked() {
    return ($this->flags() & FLAG_PAGE_LOCKED) != 0;
  }
  function version() {
    return $this->_get('version');
  }
  function latestversion() {
    return $this->_get('latestversion');
  }
  function content() {
    return explode("\n", $this->_get('content'));
  }
  function packedContent() {
    return $this->_get('content');
  }
  function refs() {
    return $this->_get('refs');
  }
  function hits() {
    return $this->_get('hits');
  }

  function _render () {
    if (!$this->rendered)
      {
	Debug(sprintf("RENDERING: %s(%d)", $this->name(), $this->version()));
	
	$renderer = new WikiPageRenderer;
	$this->html = $renderer->render_page($this);
	$this->links = $renderer->wikilinks;
	$this->rendered = true;
      }
  }
  
  function asHTML() {
    $this->_render();
    return $this->html;
  }
  function links() {
    $this->_render();
    return $this->links;
  }

  function _get($field) {
    if (!isset($this->hash[$field]))
      {
	//FIXME: die?
	Debug("WARNING: No value for page $field");
      }
    return $this->hash[$field];
  }
  
  function _default_hash($pagename) {
    $time = time();
    if (!preg_match('/^([A-Z][a-z]+){2,}$/', $pagename))
	$pagename = "[$pagename]";
    return array('flags' => 0,
		 'version' => 0,
		 'latestversion' => 0,
		 'author' => 'The PhpWiki programming team',
		 'content' => "Describe $pagename here.",
		 'created' => $time,
		 'lastmodified' => $time,
		 'hits' => 0,
		 'refs' => array());
  }


  function _canonify_content (&$hash) {
    if (is_array($hash['content']))
      {
	$hash['content']
	    = chop(implode("\n",
			   preg_replace('/[ \t\r\n]+$/', '',
					$hash['content'])));
      }
    else if (isset($hash['content']))
      {
	$hash['content']
	     = preg_replace('/[ \t\r]+\n/', "\n", chop($hash['content']));
      }
    
    if (isset($hash['refs']))
      {
	$this->hash['refs'] = preg_replace('/^\s+|\s+$/', '',
					   $this->hash['refs']);
      }
  }
    
  // FIXME: debugging only:
  function dump () {
    printf("<h2>Page: <b>%s</b></h2>\n", htmlspecialchars($this->pagename));
    echo "<table border=2>\n";
    $hash = $this->hash;
    while (list ($key, $val) = each($hash))
	printf("<tr><td>%s</td><td>%s</td></tr>\n",
	       htmlspecialchars($key), nl2br(htmlspecialchars($val)));
    echo "</table>\n";
  }
}

class WikiDataBase
{
  function getPage ($pagename, $version = 0) {
    if (!($page = $this->retrievePage($pagename, $version)))
      {
	if ($version)
	    return false;
	$page = new WikiPage($pagename);
      }
    return $page;
  }
  
  function isInTmp () {
    return false;
  }
}

function OpenDatabase ($dbparams)
{
  $type = $dbparams['dbtype'];
  switch ($type) {
  case 'dbm':
      require('wiki_dbmlib.php3');
      return new WikiDBMDataBase($dbparams);
  case 'mysql':
      require('wiki_mysql.php3');
      return new WikiMySQLDataBase($dbparams);
  default:
      die ("Bad database type: '$type'");
  }
}

?>
