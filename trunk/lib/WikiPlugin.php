<?php //-*-php-*-
rcs_id('$Id: WikiPlugin.php,v 1.3 2001-12-15 10:55:20 carstenklapp Exp $');

class WikiPlugin
{
    function getDefaultArguments() {
        return array();
    }


    // FIXME: args?
    function run ($argstr, $request) {
        trigger_error("WikiPlugin::run: pure virtual function",
                      E_USER_ERROR);
    }

    function getName() {
        return $this->name;
    }

    function getDescription() {
        return $this->description;
    }

    
    function getArgs($argstr, $request, $defaults = false) {
        if ($defaults === false)
            $defaults = $this->getDefaultArguments();

        list ($argstr_args, $argstr_defaults) = $this->parseArgStr($argstr);

        foreach ($defaults as $arg => $default_val) {
            if (isset($argstr_args[$arg]))
                $args[$arg] = $argstr_args[$arg];
            elseif ( ($argval = $request->getArg($arg)) !== false )
                $args[$arg] = $argval;
            elseif (isset($argstr_defaults[$arg]))
                $args[$arg] = (string) $argstr_defaults[$arg];
            else
                $args[$arg] = $default_val;

            $args[$arg] = $this->expandArg($args[$arg], $request);
            
            unset($argstr_args[$arg]);
            unset($argstr_defaults[$arg]);
        }

        foreach (array_merge($argstr_args, $argstr_defaults) as $arg => $val) {
            trigger_error("$arg: argument not declared by plugin",
                          E_USER_NOTICE);
        }
        
        return $args;
    }

    function expandArg($argval, $request) {
        return preg_replace('/\[(\w[\w\d]*)\]/e', '$request->getArg("$1")', $argval);
    }
    
        
    function parseArgStr($argstr) {
        $arg_p = '\w+';
        $op_p = '(?:\|\|)?=';
        $word_p = '\S+';
        $qq_p = '"[^"]*"';
        $q_p = "'[^']*'";
        $opt_ws = '\s*';
        $argspec_p = "($arg_p) $opt_ws ($op_p) $opt_ws ($qq_p|$q_p|$word_p)";

        $args = array();
        $defaults = array();
        
        while (preg_match("/^$opt_ws $argspec_p $opt_ws/x", $argstr, $m)) {
            @ list(,$arg,$op,$val) = $m;
            $argstr = substr($argstr, strlen($m[0]));

            // Remove quotes from string values.
            if ($val && ($val[0] == '"' || $val[0] == "'"))
                $val = substr($val, 1, strlen($val) - 2);

            if ($op == '=') {
                $args[$arg] = $val;
            }
            else {
                assert($op == '||=');
                $defaults[$arg] = $val;
            }
        }
        
        if ($argstr) {
            trigger_error("trailing cruft in plugin args: '$argstr'", E_USER_WARNING);
        }

        return array($args, $defaults);
    }
    

    function getDefaultLinkArguments() {
        return array('targetpage' => $this->getName(),
                     'linktext' => $this->getName(),
                     'description' => $this->getDescription(),
                     'class' => 'wikiaction');
    }
    
    function makeLink($argstr, $request) {
        $defaults = $this->getDefaultArguments();
        $link_defaults = $this->getDefaultLinkArguments();
        $defaults = array_merge($defaults, $link_defaults);

        $args = $this->getArgs($argstr, $request, $defaults);
        $plugin = $this->getName();
        
        $query_args = array();
        foreach ($args as $arg => $val) {
            if (isset($link_defaults[$arg]))
                continue;
            if ($val != $defaults[$arg])
                $query_args[$arg] = $val;
        }
        
        $attr = array('href' => WikiURL($args['targetpage'], $query_args),
                      'class' => $args['class']);

        if ($args['description']) {
            $attr['title'] = $args['description'];
            $attr['onmouseover'] = sprintf("window.status='%s';return true;",
                                           str_replace("'", "\\'", $args['description']));
            $attr['onmouseout'] = "window.status='';return true;";
        }
        return QElement('a', $attr, $args['linktext']);
    }

    function getDefaultFormArguments() {
        return array('targetpage' => $this->getName(),
                     'buttontext' => $this->getName(),
                     'class' => 'wikiaction',
                     'method' => 'get',
                     'textinput' => 's',
                     'description' => false,
                     'formsize' => 30);
    }

    function makeForm($argstr, $request) {
        $form_defaults = $this->getDefaultFormArguments();
        $defaults = array_merge($this->getDefaultArguments(),
                                $form_defaults);

        $args = $this->getArgs($argstr, $request, $defaults);
        $plugin = $this->getName();
        $textinput = $args['textinput'];
        assert(!empty($textinput) && isset($args['textinput']));

        $formattr = array('action' => WikiURL($args['targetpage']),
                          'method' => $args['method'],
                          'class' => $args['class']);
        $contents = '';
        foreach ($args as $arg => $val) {
            if (isset($form_defaults[$arg]))
                continue;
            if ($arg != $textinput && $val == $defaults[$arg])
                continue;
            
            $attr = array('name' => $arg, 'value' => $val);
            
            if ($arg == $textinput) {
                //if ($inputs[$arg] == 'file')
                //    $attr['type'] = 'file';
                //else
                $attr['type'] = 'text';
                $attr['size'] = $args['formsize'];
                if ($args['description']) {
                    $attr['title'] = $args['description'];
                    $attr['onmouseover'] = sprintf("window.status='%s';return true;",
                                                   str_replace("'", "\\'", $args['description']));
                    $attr['onmouseout'] = "window.status='';return true;";
                }
            }
            else {
                $attr['type'] = 'hidden';
            }
            
            $contents .= Element('input', $attr);

            // FIXME: hackage
            if ($attr['type'] == 'file') {
                $formattr['enctype'] = 'multipart/form-data';
                $formattr['method'] = 'post';
                $contents .= Element('input',
                                     array('name' => 'MAX_FILE_SIZE',
                                           'value' => MAX_UPLOAD_SIZE,
                                           'type' => 'hidden'));
            }
        }

        if (!empty($args['buttontext'])) {
            $contents .= Element('input',
                                 array('type' => 'submit',
                                       'class' => 'button',
                                       'value' => $args['buttontext']));
        }

        //FIXME: can we do without this table?
        return Element('form', $formattr,
                       Element('table',
                               Element('tr',
                                       Element('td', $contents))));
    }
}

class WikiPluginLoader {
    var $_errors;
    
    function expandPI($pi, $dbi, $request) {
        if (!preg_match('/^\s*<\?(plugin(?:-form|-link)?)\s+(\w+)\s*(.*?)\s*\?>\s*$/s', $pi, $m))
            return $this->_error("Bad PI");

        list(, $pi_name, $plugin_name, $plugin_args) = $m;
        $plugin = $this->getPlugin($plugin_name);
        if (!is_object($plugin)) {
            return QElement($pi_name == 'plugin-link' ? 'span' : 'p',
                            array('class' => 'plugin-error'),
                            $this->getErrorDetail());
        }
        switch ($pi_name) {
        case 'plugin':
            return $plugin->run($dbi, $plugin_args, $request);
        case 'plugin-link':
            return $plugin->makeLink($plugin_args, $request);
        case 'plugin-form':
            return $plugin->makeForm($plugin_args, $request);
        }
    }
    
    function getPlugin($plugin_name) {

        // Note that there seems to be no way to trap parse errors
        // from this include.  (At least not via set_error_handler().)
        $plugin_source = "lib/plugin/$plugin_name.php";
        
        if (!include_once("lib/plugin/$plugin_name.php")) {
            if (!empty($GLOBALS['php_errormsg']))
                return $this->_error($GLOBALS['php_errormsg']);
            // If the plugin source has already been included, the include_once()
            // will fail, so we don't want to crap out just yet.
            $include_failed = true;
        }
        
        $plugin_class = "WikiPlugin_$plugin_name";
        if (!class_exists($plugin_class)) {
            if ($include_failed)
                return $this->_error("Include of '$plugin_source' failed");
            return $this->_error("$plugin_class: no such class");
        }
        
    
        $plugin = new $plugin_class;
        if (!is_subclass_of($plugin, "WikiPlugin"))
            return $this->_error("$plugin_class: not a subclass of WikiPlugin");

        return $plugin;
    }

    function getErrorDetail() {
        return htmlspecialchars($this->_errors);
    }
    
    function _error($message) {
        $this->_errors = $message;
        return false;
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
