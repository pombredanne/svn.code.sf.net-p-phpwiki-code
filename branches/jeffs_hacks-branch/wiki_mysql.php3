<? rcs_id('$Id: wiki_mysql.php3,v 1.12.2.1 2000-07-29 00:36:45 dairiki Exp $');

class WikiMySQLPage extends WikiPage
{
  function WikiMySQLPage($dbi, $pagename, $hash) {
    $this->dbi = $dbi;
    $this->pagename = $pagename;
    $this->hash = $hash;
  }

  function refs () {
    return unserialize($this->_get('refs'));
  }

  function links () {
    //FIXME:
    die("If you need links of MySQL pages, you should implement the stuff"
	. " needed to get them from the link table.");
  }
  
  function _mysql_id () {
    return $this->_get('id');
  }

  function _get($field) {
    if (!isset($this->hash[$field]))
      {
	Debug("RETRIEVE: $this->pagename FOR $field");
	
	$page = $this->dbi->retrievePage($this->pagename,
	                                 $this->hash['version']);
	$this->hash = $page->hash;
	if (!isset($this->hash[$field]))
	    die("Failed to _get field $field");
      }
    return $this->hash[$field];
  }
}

class WikiMySQLIterator
{
  function WikiMySQLIterator ($dbi, $mysql_query_res) {
    $this->res = $mysql_query_res;
    $this->dbi = $dbi;
  }

  function next () {
    if ($this->res)
      {
	if ($hash = mysql_fetch_array($this->res))
	    return new WikiMySQLPage($this->dbi, $hash['pagename'], $hash);
	mysql_free_result($this->res);
	unset($this->res);
      }
    return false;
  }
}

    
class WikiMySQLDataBase extends WikiDataBase
{
  var $dbc;			// MySQL Connection handle.

  function _crapout ($message) {
    echo htmlspecialchars($message) . ", giving up.<br>";
    echo "MySQL error: ", mysql_error($this->dbc), "<br>\n";
    exit();
  }

  function _query ($query) {
    if (!($res = mysql_query($query, $this->dbc)))
	$this->_crapout("MySQL query '$query' failed");
    return $res;
  }

  function _query_row ($query) {
    $res = $this->_query($query);
    $row = mysql_fetch_array($res);
    mysql_free_result($res);
    return $row;
  }

  function _iterator ($query) {
    return new WikiMySQLIterator($this, $this->_query($query));
  }

  function _lock () {
    $this->_query("LOCK TABLES page WRITE, version WRITE, link WRITE");
    // FIXME: some way to use pconnect() yet ensure unlockage even if PHP dies?
  }
  function _unlock () {
    $this->_query("UNLOCK TABLES");
  }
  
  // Open the database.
  function WikiMySQLDataBase ($params) {
    //defaults:
    $server = 'localhost';
    $user = 'guest';
    $password = '';
    $database = 'test';
    extract($params);

    // Don't do persistant connection (locks can get hung).
    if (!($this->dbc = mysql_connect($server, $user, $password)))
	$this->_crapout("Cannot establish connection to database");
    if (!mysql_select_db($database, $this->dbc))
	$this->_crapout("Cannot open database");
  }
  
  function close() {
    mysql_close($this->dbc);
  }

  
  // Return hash of page + attributes or default
  // If VERSION == 0, get most recent version.
  // If VERSION > 0, get specified version.
  function retrievePage($pagename, $version = 0) {
    $pagename = addslashes($pagename);
    $join = "ON page.id=version.id";
    $pick = "pagename='$pagename'";
    if ($version == 0)
	$join .= " AND version=latestversion";
    else
	$pick .= " AND version=$version";
	
    $hash = $this->_query_row("SELECT page.id as id,"
			      . " flags,created,hits,latestversion,"
			      . " version,author,lastmodified,content,refs"
			      . " FROM page LEFT JOIN version $join"
			      . " WHERE $pick");

    return $hash ? new WikiMySQLPage($this, $pagename, $hash) : false;
  }

  function retrieveAllVersions($pagename) {
    $pagename = addslashes($pagename);
    return $this->_iterator("SELECT page.id as id,pagename,"
			    . " flags,created,hits,latestversion,"
			    . " version,author,lastmodified,content,refs"
			    . " FROM page LEFT JOIN version USING(id)"
			    . " WHERE pagename='$pagename'"
			    . " ORDER BY version DESC");
  }

  function nPages() {
    $row = $this->_query_row("SELECT count(*) FROM page");
    return $row ? $row[0] : 0;
  }

  function previousVersion($pagename, $version = false) {
    if (!$version)
	$version = "latestversion";
    $pagename = addslashes($pagename);
    $row = $this->_query_row("SELECT MAX(version)"
			     . " FROM page LEFT JOIN version USING(id)"
			     . " WHERE pagename='$pagename'"
			     . " AND version < $version");
    return $row ? $row[0] : 0;
  }
  
  // Save a new version of a page.
  function insertPage(&$page, $no_backup = false) {
    $this->_lock();
    
    $content = addslashes($page->packedContent());
    $refs = addslashes(serialize($page->refs()));
    $pagename = addslashes($page->name());
    $author = addslashes($page->author());
    $new_version = max($page->version(), 1);

    if (!($prev = $this->retrievePage($page->name())))
      {
	// Page does not exist.
	$this->_query("INSERT INTO page SET"
		      . " pagename='$pagename',"
		      . " created=" . $page->created() . ","
		      . " hits=" . $page->hits() . ","
		      . " flags=" . $page->flags());
	$id = mysql_insert_id($this->dbc);
      }
    else
      {
	$new_version = max($new_version, $prev->version() + 1);
	$id = $prev->_mysql_id();
      }
  
    $this->_query("INSERT INTO version SET"
		  . " id=$id,version=$new_version,"
		  . " author='$author',refs='$refs',content='$content',"
		  . " lastmodified=" . $page->lastmodified());

    $this->_query("UPDATE page SET latestversion=$new_version"
		  . " WHERE pagename='$pagename'");

    // Delete previous version, unless we want backup.
    if ($no_backup && $prev)
	$this->_query("DELETE FROM version"
		      . " WHERE id=$id"
		      . " AND version=" . $prev->version());

    // Update links table.
    $linknames = $page->links();
    while (list($junk,$linkname) = each($linknames))
	$links[] = sprintf("(%d, '%s')", $id, addslashes($linkname));
    
    $this->_query("DELETE FROM link WHERE id=$id");
    if ($links)
	$this->_query("INSERT INTO link (id, link) VALUES "
		      . implode(', ', $links));
    
    $this->_unlock();
    return $new_version;
  }

  function isWikiPage($pagename) {
    $pagename = addslashes($pagename);
    $row = $this->_query_row("SELECT id FROM page WHERE pagename='$pagename'");
    return $row ? true : false;
  }

  function setFlags($pagename, $flags) {
    $pagename=addslashes($pagename);
    $this->_query("UPDATE page SET flags=$flags WHERE pagename='$pagename'");
  }
  
  function increaseHitCount($pagename) {
    $pagename=addslashes($pagename);
    $this->_query("UPDATE page SET hits=hits+1 WHERE pagename='$pagename'");
  }

  // setup for title-search
  function titleSearch($search) {
    $search = preg_replace("/[%_\\\\']/", '\\\\1', $search);
    return $this->_iterator("SELECT pagename,flags,created,hits,"
			    . " latestversion, latestversion AS version"
			    . " FROM page"
			    . " WHERE pagename LIKE '%$search%'"
			    . " ORDER BY pagename");
  }

  // setup for title-search
  function backLinks($pagename) {
    $pagename = addslashes($pagename);
    return $this->_iterator("SELECT pagename,flags,created,hits,"
			    . " latestversion, latestversion AS version"
			    . " FROM link"
			    . " LEFT JOIN page USING(id)"
			    . " WHERE link='$pagename'"
			    . " ORDER BY pagename");
  }

  // setup for full-text search
  function fullSearch($search) {
    $search = preg_replace("/[%_\\\\']/", '\\\\1', $search);
    return $this->_iterator("SELECT page.id as id,pagename,"
			    . " flags,created,hits,latestversion,"
			    . " version,author,lastmodified,content,refs"
			    . " FROM page LEFT JOIN version"
			    . "  ON page.id=version.id"
			    . "  AND latestversion=version"
			    . " WHERE CONCAT(pagename, ' ', content)"
			    . "    LIKE '%$search%'"
			    . " ORDER BY pagename");
  }

  function mostPopular($limit = 20) {
    return $this->_iterator("SELECT pagename,flags,created,hits,"
			    . " latestversion, latestversion AS version"
			    . " FROM page"
			    . " WHERE hits > 0"
			    . " ORDER BY hits DESC, pagename"
			    . " LIMIT $limit");
  }

  function retrieveAllPages() {
    return $this->_iterator("SELECT pagename FROM page ORDER BY pagename");
  }
}

?>
