<? rcs_id('$Id: wiki_search.php3,v 1.5.2.2 2000-07-29 00:36:45 dairiki Exp $');
   // Title search: returns pages having a name matching the search term

class SearchTokens extends PageIteratorTokens
{
  var $prefix = 'Search';
  
  function SearchTokens ($action, $search_term) {
    global $dbi;

    $this->term = $search_term;
    
    if ($action == 'search')
	$this->iter = $dbi->titleSearch($search_term);
    else if ($action == 'full')
	$this->iter = $dbi->fullSearch($search_term);
    else if ($action == 'backlinks')
        $this->iter = $dbi->backLinks($search_term);
    else
	die("$action: bad action");
  }

  function _get2 ($what) {
    global $dbi;

    switch ($what) {
    case 'Highlight':
	return new LineIteratorTokens($this->highlight_content());
    case 'Term':       return htmlspecialchars($this->term);
    case 'TotalPages': return strval($dbi->nPages());
    }
    return TOKEN_BAD;
  }

  function highlight_content () {
    $term = preg_quote(htmlspecialchars($this->term));
    $content = $this->page->content();
    $lines = array();

    while (list ($junk, $line) = each($content))
      {
	$line = htmlspecialchars($line);
	if (preg_match("|($term)|i", $line))
	    $lines[] = preg_replace("|($term)|i", "<b>\\0</b>", $line);
      }
    return $lines;
  }
}
  
SetToken('Search', new SearchTokens($action, $search_term));
SetToken('content', Template(strtoupper($action)));
?>
