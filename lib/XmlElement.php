<?php rcs_id('$Id: XmlElement.php,v 1.1 2002-01-21 01:48:50 dairiki Exp $');
/*
 * Code for writing XML.
 */

/**
 * An XML element.
 */
class XmlElement
{
    function XmlElement ($tagname /* , $attr_or_content , ...*/) {
	$this->_tag = $tagname;
        $this->_content = array();

        if (func_num_args() > 1)
            $this->_init(array_slice(func_get_args(), 1));
        else 
            $this->_attr = array();
    }

    function _init ($args) {
        assert(is_array($args));
        if (!$args)
            return;

        if (is_array($args[0]))
            $this->_attr = array_shift($args);
        else {
            $this->_attr = array();
            if (count($args) > 1 && ! $args[0])
                array_shift($args);
        }

        if (count($args) == 1 && is_array($args[0]))
            $this->_content = $args[0];
        else
            $this->_content = $args;
    }

    function getTag () {
        return $this->_tag;
    }
    
    function setAttr ($attr, $value = false) {
	if (is_array($attr)) {
            assert($value === false);
            foreach ($attr as $a => $v)
		$this->set($a, $v);
            return;
	}

        assert(is_string($attr));
        if ($value === false) {
            unset($this->_attr[$attr]);
        }
        if (is_bool($value))
            $value = $attr;
        $this->_attr[$attr] = (string) $value;
    }

    function getAttr ($attr) {
	if (isset($this->_attr[$attr]))
	    return $this->_attr[$attr];
	else
	    return false;
    }
    
    function pushContent ($args /*, ...*/) {
        $c = &$this->_content;
        if (func_num_args() != 1 || ! is_array($args))
            $args = func_get_args();
        array_splice($c, count($c), 0, $args);
    }

    function unshiftContent ($args /*, ...*/) {
        $c = &$this->_content;
        if (func_num_args() != 1 || ! is_array($args))
            $args = func_get_args();
        array_splice($c, 0, 0, $args);
    }

    function getContent () {
        return $this->_content;
    }

    function _startTag () {
        $tag = $this->_tag;
        foreach ($this->_attr as $attr => $val)
            $tag .= " $attr=\"" . $this->_quoteAttr($val) . '"';
        return $tag;
    }

    function printXML () {
        if (!$this->_content) {
            echo "<" . $this->_startTag() . "/>";
        }
        else {
            echo "<" . $this->_startTag() . ">";
            foreach ($this->_content as $c) {
                if (is_object($c)) {
                    if (method_exists($c, 'printxml')) {
                        $c->printXML();
                        continue;
                    }
                    elseif (method_exists($c, 'asstring'))
                        $c = $c->asString();
                }
                echo $this->_quote($c);
            }
            echo "</$this->_tag>";
        }
    }

    function asXML () {
        if (!$this->_content) {
            return "<" . $this->_startTag() . "/>";
        }

        $xml =  "<" . $this->_startTag() . ">";
        foreach ($this->_content as $c) {
            if (is_object($c)) {
                if (method_exists($c, 'printxml')) {
                    $xml .= $c->asXML();
                    continue;
                }
                elseif (method_exists($c, 'asstring'))
                    $c = $c->asString();
            }
            $xml .= $this->_quote($c);
        }
        $xml .= "</$this->_tag>";
        return $xml;
    }

    function _quote ($string) {
        return str_replace('<', '&lt;',
                           str_replace('>', '&gt;',
                                       str_replace('&', '&amp;', $string)));
    }

    function _quoteAttr ($value) {
        return str_replace('"', '&quot;', XmlElement::_quote($value));
    }
};

class RawXml {
    function RawXml ($xml_text) {
        $this->_xml = $xml_text;
    }

    function printXML () {
        echo $this->_xml;
    }

    function asXML () {
        return $this->_xml;
    }
}

class FormattedText extends RawXml {
    function FormattedText ($fs /* , ... */) {
        if ($fs !== false)
            $this->_init(func_get_args());
    }

    function _init ($args) {
        $fs = array_shift($args);
        $qargs = array();
        
        // PHP's sprintf doesn't support variable with specifiers,
        // like sprintf("%*s", 10, "x"); --- so we won't either.
        if (preg_match_all('/(?<!%)%(\d+)\$/x', $fs, $m)) {
            // Format string has '%2$s' style argument reordering.
            // PHP doesn't support this.
            if (preg_match('/(?<!%)%[- ]?\d*[^- \d$]/x', $fmt))
                // literal variable name substitution only to keep locale
                // strings uncluttered
                trigger_error(sprintf(_("Can't mix '%s' with '%s' type format strings"),
                                      '%1\$s','%s'), E_USER_WARNING);
        
            $fs = preg_replace('/(?<!%)%\d+\$/x', '%', $fs);

            // Reorder arguments appropriately.
            // FIXME: pay attention to format type?  (only quote %s args?)
            foreach($m[1] as $argnum) {
                if ($argnum < 1 || $argnum > count($args))
                    trigger_error(sprintf(_("%s: argument index out of range"), 
                                          $argnum), E_USER_WARNING);
                $qargs[] = asXML($args[$argnum - 1]);
            }
        }
        else {
            // FIXME: pay attention to format type?  (only quote %s args?)
            foreach ($args as $arg)
                $qargs[] = asXML($arg);
        }

        $fs = XmlElement::_quote($fs);
        
        // Not all PHP's have vsprintf, so...
        array_unshift($qargs, $fs);
        $this->_xml = call_user_func_array('sprintf', $qargs);
    }
}

function asXML ($val) {
    if (is_object($val) && method_exists($val, 'asxml'))
        return $val->asXML();
    return XmlElement::_quote($val);
}

function fmt ($fs /* , ... */) {
    $s = new FormattedText(false);
    $args = func_get_args();
    $fs = &$args[0];
    $fs = gettext($fs);
    $s->_init($args);
    return $s;
}
    
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
