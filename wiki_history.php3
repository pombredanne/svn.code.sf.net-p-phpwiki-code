<? rcs_id('$Id: wiki_history.php3,v 1.1.2.1 2000-07-29 00:36:45 dairiki Exp $');
class HistTokens extends PageIteratorTokens
{
  var $prefix = 'Hist';
  
  function HistTokens ($pagename) {
    global $dbi;
    $this->iter = $dbi->retrieveAllVersions($pagename);
  }
}

SetToken('Hist', new HistTokens($pagename));

SafeSetToken('ShowPageSourceChecked', $showpagesource ? 'checked' : '');
SetToken('content', Template(strtoupper($action)));
?>
