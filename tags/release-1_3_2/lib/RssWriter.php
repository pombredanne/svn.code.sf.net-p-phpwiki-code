<?php rcs_id('$Id: RssWriter.php,v 1.2 2001-12-14 20:16:53 dairiki Exp $');
/*
 * Code for creating RSS 1.0.
 */

// FIXME: this should probably be improved/cleaned then moved into its own file.
//
/**
 * An XML element.
 */
class XmlElement
{
    function XmlElement ($name, $attr = false, $content = false) {
	$this->_name = $name;
	$this->_attr = array();
	if (is_array($attr)) {
	    $this->set($attr);
	}
	$this->_content = array();
	if ($content) {
	    $this->add($content);
	}
    }

    function set ($attr, $value = false) {
	if (is_array($attr)) {
            assert($value === false);
            foreach ($attr as $a => $v)
		$this->set($a, $v);
	}
	else {
	    assert(is_string($attr));
	    assert(is_string($value));
	    $this->_attr[$attr] = $value;
	}
    }

    function get ($attr) {
	if (isset($this->_attr[$attr]))
	    return $this->_attr[$attr];
	else
	    return false;
    }
    
    function add ($content) {
	if (!is_array($content))
            $content = array($content);
        foreach ($content as $c)
            $this->_content[] = $c;
    }

    function asString ($indent = '') {
	$begin[0] = $indent . '<' . $this->_name;
	$nchars = strlen($begin[0]) + 1;
	
	reset($this->_attr);
	while (list ($attr, $value) = each($this->_attr)) {
	    $q = sprintf('%s="%s"',
			      $attr, $this->_quote_attribute($value));
	    $nchars += strlen($q) + 1;
	    $begin[] = $q;
	}

	if ($nchars > 79) {
	    $xml = join("\n$indent    ", $begin);
	}
	else {
	    $xml = join(" ", $begin);
	}
	
	if (($n = count($this->_content)) > 0) {
	    $xml .= ">";

	    $c = $this->_content[0];
	    if (is_object($c)) {
		$xml .= "\n" . $c->asString($indent . "  ");
		$break_lines = true;
	    }
	    else {
		$xml .= $this->_quote($c);
		$break_lines = false;
	    }

	    for ($i = 1; $i < $n; $i++) {
		$c = $this->_content[$i];
		if (is_string($c)) {
		    $xml .= "\n$indent" . $this->_quote($c);
		}
		else {
		    $xml .= "\n" . $c->asString($indent . "  ");
		}
		$break_lines = true;
	    }
	    if ($break_lines) {
		$xml .= "\n$indent";
	    }
	    $xml .= sprintf("</%s>", $this->_name);
	}
	else {
	    $xml .= "/>";
	}
	return $xml;
    }

    function _quote ($string) {
	return str_replace('<', '&lt;',
			   str_replace('>', '&gt;',
				       str_replace('&', '&amp;', $string)));
    }

    function _quote_attribute ($value) {
	return str_replace('"', '&quot;', $this->_quote($value));
    }
    
};

// Encoding for RSS output.
define('RSS_ENCODING', 'ISO-8859-1');

/**
 * A class for writing RSS 1.0.
 *
 * @see http://purl.org/rss/1.0/spec,
 *      http://www.usemod.com/cgi-bin/mb.pl?ModWiki
 */
class RssWriter extends XmlElement
{
    function RssWriter () {
        $this->XmlElement('rdf:RDF',
                          array('xmlns' => "http://purl.org/rss/1.0/",
                                'xmlns:rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#'));

	$this->_modules = array(
            //Standards
	    'content'	=> "http://purl.org/rss/1.0/modules/content/",
	    'dc'	=> "http://purl.org/dc/elements/1.1/",
	    'sy'	=> "http://purl.org/rss/1.0/modules/syndication/",
            //Proposed
            'wiki'      => "http://purl.org/rss/1.0/modules/wiki/",
	    'ag'	=> "http://purl.org/rss/1.0/modules/aggregation/",
	    'annotate'	=> "http://purl.org/rss/1.0/modules/annotate/",
	    'audio'	=> "http://media.tangent.org/rss/1.0/",
	    'cp'	=> "http://my.theinfo.org/changed/1.0/rss/",
	    'rss091'	=> "http://purl.org/rss/1.0/modules/rss091/",
	    'slash'	=> "http://purl.org/rss/1.0/modules/slash/",
	    'taxo'	=> "http://purl.org/rss/1.0/modules/taxonomy/",
	    'thr'	=> "http://purl.org/rss/1.0/modules/threading/"
	    );

	$this->_uris_seen = array();
        $this->_items = array();
    }

    function registerModule($alias, $uri) {
	assert(!isset($this->_modules[$alias]));
	$this->_modules[$alias] = $uri;
    }
        
    // Args should include:
    //  'title', 'link', 'description'
    // and can include:
    //  'URI'
    function channel($properties, $uri = false) {
        $this->_channel = $this->__node('channel', $properties, $uri);
    }
    
    // Args should include:
    //  'title', 'link'
    // and can include:
    //  'description', 'URI'
    function addItem($properties, $uri = false) {
        $this->_items[] = $this->__node('item', $properties, $uri);
    }

    // Args should include:
    //  'url', 'title', 'link'
    // and can include:
    //  'URI'
    function image($properties, $uri = false) {
        $this->_image = $this->__node('image', $properties, $uri);
    }

    // Args should include:
    //  'title', 'description', 'name', and 'link'
    // and can include:
    //  'URI'
    function textinput($properties, $uri = false) {
        $this->_textinput = $this->__node('textinput', $properties, $uri);
    }

    /**
     * Finish construction of RSS.
     */
    function finish() {
        if (isset($this->_finished))
            return;

        $channel = &$this->_channel;
        $items = &$this->_items;

        if ($items) {
            $seq = new XmlElement('rdf:Seq');
            foreach ($items as $item)
                $seq->add($this->__ref('rdf:li', $item));
            $channel->add(new XmlElement('items', false, $seq));
        }
     
	if (isset($this->_image)) {
            $channel->add($this->__ref('image', $this->_image));
	    $items[] = $this->_image;
	}
	if (isset($this->_textinput)) {
            $channel->add($this->__ref('textinput', $this->_textinput));
	    $items[] = $this->_textinput;
	}

	$this->add($channel);
	if ($items)
	    $this->add($items);

        $this->__spew();
        $this->_finished = true;
    }
            

    /**
     * Write output to HTTP client.
     */
    function __spew() {
        header("Content-Type: application/xml; charset=" . RSS_ENCODING);
        printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", RSS_ENCODING);
        echo $this->asString();
    }
        
    
    /**
     * Create a new RDF <i>typedNode</i>.
     */
    function __node($type, $properties, $uri = false) {
	if (! $uri)
	    $uri = $properties['link'];
	$attr['rdf:about'] = $this->__uniquify_uri($uri);
	return new XmlElement($type, $attr,
                              $this->__elementize($properties));
    }

    /**
     * Check object URI for uniqueness, create a unique URI if needed.
     */
    function __uniquify_uri ($uri) {
	if (!$uri || isset($this->_uris_seen[$uri])) {
	    $n = count($this->_uris_seen);
	    $uri = $this->_channel->get('rdf:about') . "#uri$n";
	    assert(!isset($this->_uris_seen[$uri]));
	}
	$this->_uris_seen[$uri] = true;
	return $uri;
    }

    /**
     * Convert hash of RDF properties to <i>propertyElt</i>s.
     */
    function __elementize ($elements) {
	$out = array();
        foreach ($elements as $prop => $val) {
	    $this->__check_predicate($prop);
	    $out[] = new XmlElement($prop, false, $val);
	}
	return $out;
    }

    /**
     * Check property predicates for XMLNS sanity.
     */
    function __check_predicate ($name) {
	if (preg_match('/^([^:]+):[^:]/', $name, $m)) {
	    $ns = $m[1];
	    if (! $this->get("xmlns:$ns")) {
		if (!isset($this->_modules[$ns]))
		    die("$name: unknown namespace ($ns)");
		$this->set("xmlns:$ns", $this->_modules[$ns]);
	    }
	}
    }

    /**
     * Create a <i>propertyElt</i> which references another node in the RSS.
     */
    function __ref($predicate, $reference) {
        $attr['rdf:resource'] = $reference->get('rdf:about');
        return new XmlElement($predicate, $attr);
    }
};


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
