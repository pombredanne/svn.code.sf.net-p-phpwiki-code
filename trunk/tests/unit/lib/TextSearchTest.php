<?php

require_once 'lib/TextSearchQuery.php';
require_once 'PHPUnit.php';

class TextSearchTest extends phpwiki_TestCase {

    function testTitleSearch() {
        global $request;
	// find subpages
	$pagename = "PgsrcTranslation";
        $query = new TextSearchQuery($pagename . SUBPAGE_SEPARATOR . '*', true, 'glob');
	$sortby = false; $limit = 20; $exclude = "";
        $dbi = $request->getDbh();
        $subpages = $dbi->titleSearch($query, $sortby, $limit, $exclude);

	$result = array();
	while ($page = $subpages->next())
	    $result[] = $page->getName();
        $this->assertTrue(count($result) > 0, "glob count > 0");

	// apply limit
	$sortby = false; $limit = 5; $exclude = "";
        $subpages = $dbi->titleSearch($query, $sortby, $limit, $exclude);

	// don't trust count() with limit
	$this->assertTrue($subpages->count() > 0 and $subpages->count() <= 7, 
			  "0 < count() <= 7");
	$result = array();
	// but the iterator should limit
	while ($page = $subpages->next())
	    $result[] = $page->getName();
	$this->assertEquals(5, count($result), "limit 5");
	
    }

    function testFulltextSearch() {
        global $request;
        $dbi = $request->getDbh();

        $query = new TextSearchQuery('Indent the paragraph*', true); // auto
        $pages = $dbi->fullSearch($query);
        $result = array();
	while ($page = $pages->next())
	    $result[] = $page->getName();

        $this->assertTrue(in_array("TextFormattingRules", $result), "found all");

        $query = new TextSearchQuery('"Indent the paragraph"', false); // case-insensitive, auto
        $pages = $dbi->fullSearch($query);
        $result = array();
	while ($page = $pages->next())
	    $result[] = $page->getName();
        $this->assertTrue(in_array("TextFormattingRules", $result), "found phrase");

    }
}


?>
