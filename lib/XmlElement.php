<?php rcs_id('$Id: XmlElement.php,v 1.2 2002-01-21 06:55:47 dairiki Exp $');
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
            foreach ($this->_content as $c)
                PrintXML($c);
            echo "</$this->_tag>";
        }
    }

    function asXML () {
        if (!$this->_content) {
            return "<" . $this->_startTag() . "/>";
        }

        $xml =  "<" . $this->_startTag() . ">";
        foreach ($this->_content as $c)
            $xml .= AsXML($c);
        $xml .= "</$this->_tag>";
        return $xml;
    }

    function asString () {
        $str = '';
        foreach ($this->_content as $c)
            $val .= AsString($c);
        return trim($str);
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

class FormattedText {
    function FormattedText ($fs /* , ... */) {
        if ($fs !== false) {
            $this->_init(func_get_args());
        }
    }

    function _init ($args) {
        $this->_fs = array_shift($args);

        // PHP's sprintf doesn't support variable width specifiers,
        // like sprintf("%*s", 10, "x"); --- so we won't either.

        if (! preg_match_all('/(?<!%)%(\d+)\$/x', $this->_fs, $m)) {
            $this->_args  = $args;
        }
        else {
            // Format string has '%2$s' style argument reordering.
            // PHP doesn't support this.
            if (preg_match('/(?<!%)%[- ]?\d*[^- \d$]/x', $fmt))
                // literal variable name substitution only to keep locale
                // strings uncluttered
                trigger_error(sprintf(_("Can't mix '%s' with '%s' type format strings"),
                                      '%1\$s','%s'), E_USER_WARNING);
        
            $this->_fs = preg_replace('/(?<!%)%\d+\$/x', '%', $this->_fs);

            $this->_args = array();
            foreach($m[1] as $argnum) {
                if ($argnum < 1 || $argnum > count($args))
                    trigger_error(sprintf(_("%s: argument index out of range"), 
                                          $argnum), E_USER_WARNING);
                $this->_args[] = $args[$argnum - 1];
            }
        }
    }

    function asXML () {
        // Not all PHP's have vsprintf, so...
        $args[] = XmlElement::_quote($this->_fs);
        foreach ($this->_args as $arg)
            $args[] = AsXML($arg);
        return call_user_func_array('sprintf', $args);
    }

    function printXML () {
        // Not all PHP's have vsprintf, so...
        $args[] = XmlElement::_quote($this->_fs);
        foreach ($this->_args as $arg)
            $args[] = AsXML($arg);
        call_user_func_array('printf', $args);
    }

    function asString() {
        $args = $this->_args;
        array_unshift($args, $this->_fs);
        return call_user_func_array('sprintf', $args);
    }
}

function PrintXML ($val) {
    if (is_object($val)) {
        if (method_exists($val, 'printxml'))
            return $val->printXML();
        elseif (method_exists($val, 'asxml')) {
            echo $val->asXML();
            return;
        }
        elseif (method_exists($val, 'asstring'))
            $val = $val->asString();
    }
    elseif (is_array($val)) {
        foreach ($val as $x)
            PrintXML($x);
    }
        
    echo (string)XmlElement::_quote($val);
}

function AsXML ($val) {
    if (is_object($val)) {
        if (method_exists($val, 'asxml'))
            return $val->asXML();
        elseif (method_exists($val, 'asstring'))
            $val = $val->asString();
    }
    elseif (is_array($val)) {
        $xml = '';
        foreach ($val as $x)
            $xml .= AsXML($x);
        return $xml;
    }
    
    return XmlElement::_quote((string)$val);
}

function AsString ($val) {
    if (can($val, 'asString'))
        return $val->asString();
    elseif (is_array($val)) {
        $str = '';
        foreach ($val as $x)
            $str .= AsString($x);
        return $str;
    }
    return (string) $val;
}

    
function fmt ($fs /* , ... */) {
    $s = new FormattedText(false);

    $args = func_get_args();
    $args[0] = gettext($args[0]);
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
