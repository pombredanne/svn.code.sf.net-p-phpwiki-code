<?php rcs_id('$Id: interwiki.php,v 1.14 2002-01-29 05:05:05 dairiki Exp $');

function generate_interwikimap_and_regexp()
{
    global $interwikimap_file, $InterWikiLinkRegexp, $interwikimap;

    $intermap_data = file(INTERWIKI_MAP_FILE, 1);
    $wikiname_regexp = "";
    for ($i=0; $i<count($intermap_data); $i++)
        {
            list( $wiki, $inter_url ) = split(' ', chop($intermap_data[$i]));
            $interwikimap[$wiki] = $inter_url;
            if ($wikiname_regexp)
                $wikiname_regexp .= "|";
            $wikiname_regexp .= $wiki;
        }

    $InterWikiLinkRegexp = "(?:$wikiname_regexp)";
}

generate_interwikimap_and_regexp();

function LinkInterWikiLink($link, $linktext='')
{
    global $interwikimap;

    list( $wiki, $page ) = split( ":", $link, 2 );

    $url = $interwikimap[$wiki];

    // Urlencode page only if it's a query arg.
    // FIXME: this is a somewhat broken heuristic.
    $page_enc = strstr($url, '?') ? rawurlencode($page) : $page;

    if (strstr($url, '%s'))
        $url = sprintf($url, $page_enc);
    else
        $url .= $page_enc;

    $link = HTML::a(array('href' => $url),
                    IconForLink('interwiki'));
    
    if (!$linktext) {
        $link->pushContent("$wiki:",
                           HTML::span(array('class' => 'wikipage'), $page));
        $link->setAttr('class', 'interwiki');
    }
    else {
        $link->pushContent($linktext);
        $link->setAttr('class', 'named-interwiki');
    }

    return $link;
}


// Link InterWiki links
// These can be protected by a '!' like Wiki words.
function wtt_interwikilinks($match, &$trfrm) {
    return $match[0] == "!" ? substr($match,1) : LinkInterWikiLink($match);
}

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
