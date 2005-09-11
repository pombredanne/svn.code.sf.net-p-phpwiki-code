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

        $this->assertTrue($subpages->count() > 0, "glob count > 0");

	// apply limit
	$sortby = false; $limit = 5; $exclude = "";
        $subpages = $dbi->titleSearch($query, $sortby, $limit, $exclude);

	// don't trust count()
        $this->assertEquals(5, $subpages->count(), "count() limit 5");
	while ($page = $subpages->next())
	    $result[] = $page->getName();
	$this->assertEquals(5, count($result), "limit 5");
	
    }

    function testFulltextSearch() {
        global $request;
        $query = new TextSearchQuery('Indent the paragraph*', true); // auto
        $dbi = $request->getDbh();
        $pages = $dbi->fullSearch($query);
	while ($page = $pages->next())
	    $result[] = $page->getName();

        $this->assertTrue(in_array("TextFormattingRules", $result), "found TextFormattingRules");
    }
}


?>
