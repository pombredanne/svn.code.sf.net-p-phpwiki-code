<?php
/**
 * Copyright © 2001-2003 Jeff Dairiki
 * Copyright © 2002-2003 Carsten Klapp
 * Copyright © 2002,2004-2005,2007-2008 Reini Urban
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

abstract class WikiPlugin
{
    public $_pi;

    public function getDefaultArguments()
    {
        return array('description' => $this->getDescription());
    }

    /** Does the plugin manage its own HTTP validators?
     *
     * This should be overwritten by (some) individual plugins.
     *
     * If the output of the plugin is static, depending only
     * on the plugin arguments, query arguments and contents
     * of the current page, this can (and should) return true.
     *
     * If the plugin can deduce a modification time, or equivalent
     * sort of tag for it's content, then the plugin should
     * call $request->appendValidators() with appropriate arguments,
     * and should override this method to return true.
     *
     * When in doubt, the safe answer here is false.
     * Unfortunately, returning false here will most likely make
     * any page which invokes the plugin uncacheable (by HTTP proxies
     * or browsers).
     */
    public function managesValidators()
    {
        return false;
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    abstract public function run($dbi, $argstr, &$request, $basepage);

    /** Get wiki-pages linked to by plugin invocation.
     *
     * A plugin may override this method to add pages to the
     * link database for the invoking page.
     *
     * For example, the IncludePage plugin should override this so
     * that the including page shows up in the backlinks list for the
     * included page.
     *
     * Not all plugins which generate links to wiki-pages need list
     * those pages here.
     *
     * Note also that currently the links are calculated at page save
     * time, so only static page links (e.g. those dependent on the PI
     * args, not the rest of the wikidb state or any request query args)
     * will work correctly here.
     *
     * @param  string $argstr   The plugin argument string.
     * @param  string $basepage The pagename the plugin is invoked from.
     * @return array List of pagenames linked to.
     */
    public function getWikiPageLinks($argstr, $basepage)
    {
        return array();
    }

    /**
     * Get name of plugin.
     *
     * This is used (by default) by getDefaultLinkArguments and
     * getDefaultFormArguments to compute the default link/form
     * targets.
     *
     * If you override this method in your plugin class,
     * you MUST NOT translate the name.
     * <pre>
     *   function getName() { return "MyPlugin"; }
     * </pre>
     *
     * @return string plugin name/target.
     */
    public function getName()
    {
        return preg_replace('/^WikiPlugin_/', '', get_class($this));
    }

    /**
     * Get description of plugin.
     *
     * This method should be overriden in your plugin class, like:
     * <pre>
     *   function getDescription() { return _("MyPlugin does this..."); }
     * </pre>
     *
     * @return string plugin description
     */

    abstract protected function getDescription();

    /**
     * @param string $argstr
     * @param WikiRequest $request
     * @param array $defaults
     * @return array
     */
    public function getArgs($argstr, $request = null, $defaults = array())
    {
        if (empty($defaults)) {
            $defaults = $this->getDefaultArguments();
        }
        //Fixme: on POST argstr is empty
        list($argstr_args, $argstr_defaults) = $this->parseArgStr($argstr);
        $args = array();
        if (!empty($defaults)) {
            foreach ($defaults as $arg => $default_val) {
                if (isset($argstr_args[$arg])) {
                    $args[$arg] = $argstr_args[$arg];
                } elseif ($request and ($argval = $request->getArg($arg)) !== false) {
                    $args[$arg] = $argval;
                } elseif (isset($argstr_defaults[$arg])) {
                    $args[$arg] = (string)$argstr_defaults[$arg];
                } else {
                    $args[$arg] = $default_val;
                }
                // expand [arg]
                if ($request and is_string($args[$arg]) and strstr($args[$arg], "[")) {
                    $args[$arg] = $this->expandArg($args[$arg], $request);
                }

                unset($argstr_args[$arg]);
                unset($argstr_defaults[$arg]);
            }
        }

        foreach (array_merge($argstr_args, $argstr_defaults) as $arg => $val) {
            if ($this->allow_undeclared_arg($arg, $val)) {
                $args[$arg] = $val;
            }
        }

        // Add special handling of pages and exclude args to accept <! plugin-list !>
        // and split explodePageList($args['exclude']) => array()
        // TODO : handle p[] pagehash
        foreach (array('pages', 'exclude') as $key) {
            if (!empty($args[$key]) and array_key_exists($key, $defaults)) {
                $args[$key] = is_string($args[$key])
                    ? explodePageList($args[$key])
                    : $args[$key]; // <! plugin-list !>
            }
        }

        return $args;
    }

    // Patch by Dan F:
    // Expand [arg] to $request->getArg("arg") unless preceded by ~
    public function expandArg($argval, &$request)
    {
        // Replace the arg unless it is preceded by a ~
        $ret = preg_replace_callback(
            '/([^~]|^)\[(\w[\w\d]*)\]/',
            function ($m) {
                global $request;
                return "$m[1]" . $request->getArg("$m[2]");
            },
            $argval
        );
        // Ditch the ~ so later versions can be expanded if desired
        return preg_replace('/~(\[\w[\w\d]*\])/', '$1', $ret);
    }

    public function parseArgStr($argstr)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $args = array();
        $defaults = array();
        if (empty($argstr)) {
            return array($args, $defaults);
        }

        $arg_p = '\w+';
        $op_p = '(?:\|\|)?=';
        $word_p = '\S+';
        $opt_ws = '\s*';
        $qq_p = '" ( (?:[^"\\\\]|\\\\.)* ) "';
        //"<--kludge for brain-dead syntax coloring
        $q_p = "' ( (?:[^'\\\\]|\\\\.)* ) '";
        $gt_p = "_\\( $opt_ws $qq_p $opt_ws \\)";
        $argspec_p = "($arg_p) $opt_ws ($op_p) $opt_ws (?: $qq_p|$q_p|$gt_p|($word_p))";

        // handle plugin-list arguments separately
        $plugin_p = '<!plugin-list\s+\w+.*?!>';
        while (preg_match("/^($arg_p) $opt_ws ($op_p) $opt_ws ($plugin_p) $opt_ws/x", $argstr, $m)) {
            @ list(, $arg, $op, $plugin_val) = $m;
            $argstr = substr($argstr, strlen($m[0]));
            $loader = new WikiPluginLoader();
            $markup = null;
            $basepage = null;
            $plugin_val = preg_replace(array("/^<!/", "/!>$/"), array("<?", "?>"), $plugin_val);
            $val = $loader->expandPI($plugin_val, $request, $markup, $basepage);
            if ($op == '=') {
                $args[$arg] = $val; // comma delimited pagenames or array()?
            } else {
                assert($op == '||=');
                $defaults[$arg] = $val;
            }
        }
        while (preg_match("/^$opt_ws $argspec_p $opt_ws/x", $argstr, $m)) {
            $qq_val = '';
            $q_val = '';
            $gt_val = '';
            $word_val = '';
            $op = '';
            $arg = '';
            $count = count($m);
            if ($count >= 7) {
                list(, $arg, $op, $qq_val, $q_val, $gt_val, $word_val) = $m;
            } elseif ($count == 6) {
                list(, $arg, $op, $qq_val, $q_val, $gt_val) = $m;
            } elseif ($count == 5) {
                list(, $arg, $op, $qq_val, $q_val) = $m;
            } elseif ($count == 4) {
                list(, $arg, $op, $qq_val) = $m;
            }
            $argstr = substr($argstr, strlen($m[0]));
            // Remove quotes from string values.
            if ($qq_val) {
                $val = stripslashes($qq_val);
            } elseif ($count > 4 and $q_val) {
                $val = stripslashes($q_val);
            } elseif ($count >= 6 and $gt_val) {
                $val = _(stripslashes($gt_val));
            } elseif ($count >= 7) {
                $val = $word_val;
            } else {
                $val = '';
            }

            if ($op == '=') {
                $args[$arg] = $val;
            } else {
                // NOTE: This does work for multiple args. Use the
                // separator character defined in your webserver
                // configuration, usually & or &amp; (See
                // http://www.htmlhelp.com/faq/cgifaq.4.html)
                // e.g. <plugin RecentChanges days||=1 show_all||=0 show_minor||=0>
                // url: RecentChanges?days=1&show_all=1&show_minor=0
                assert($op == '||=');
                $defaults[$arg] = $val;
            }
        }

        if ($argstr) {
            $this->handle_plugin_args_cruft($argstr, $args);
        }

        return array($args, $defaults);
    }

    /* A plugin can override this function to define how any remaining text is handled */
    public function handle_plugin_args_cruft($argstr, $args)
    {
        trigger_error(sprintf(
            _("trailing cruft in plugin args: “%s”"),
            $argstr
        ));
    }

    /* A plugin can override this to allow undeclared arguments.
       Or to silence the warning.
     */
    public function allow_undeclared_arg($name, $value)
    {
        trigger_error(sprintf(
            _("Argument “%s” not declared by plugin."),
            $name
        ));
        return false;
    }

    /* handle plugin-list argument: use run(). */
    public function makeList($plugin_args, $request, $basepage)
    {
        $dbi = $request->getDbh();
        $pagelist = $this->run($dbi, $plugin_args, $request, $basepage);
        $list = array();
        if (is_object($pagelist) and is_a($pagelist, 'PageList')) {
            return $pagelist->pageNames();
        } elseif (is_array($pagelist)) {
            return $pagelist;
        } else {
            return $list;
        }
    }

    public function getDefaultLinkArguments()
    {
        return array('targetpage' => $this->getName(),
            'linktext' => $this->getName(),
            'description' => $this->getDescription(),
            'class' => 'wikiaction');
    }

    public function getDefaultFormArguments()
    {
        return array('targetpage' => $this->getName(),
            'buttontext' => _($this->getName()),
            'class' => 'wikiaction',
            'method' => 'get',
            'textinput' => 's',
            'description' => $this->getDescription(),
            'formsize' => 30);
    }

    public function makeForm($argstr, $request)
    {
        $form_defaults = $this->getDefaultFormArguments();
        $defaults = array_merge(
            $form_defaults,
            array('start_debug' => $request->getArg('start_debug')),
            $this->getDefaultArguments()
        );

        $args = $this->getArgs($argstr, $request, $defaults);
        $textinput = $args['textinput'];
        assert(!empty($textinput) && isset($args['textinput']));

        $form = HTML::form(array('action' => WikiURL($args['targetpage']),
            'method' => $args['method'],
            'class' => $args['class'],
            'accept-charset' => 'UTF-8'));
        if (!USE_PATH_INFO) {
            $form->pushContent(HTML::input(array('type' => 'hidden',
                'name' => 'pagename',
                'value' => $args['targetpage'])));
        }
        if ($args['targetpage'] != $this->getName()) {
            $form->pushContent(HTML::input(array('type' => 'hidden',
                'name' => 'action',
                'value' => $this->getName())));
        }
        $contents = HTML::div();
        $contents->setAttr('class', $args['class']);

        foreach ($args as $arg => $val) {
            if (isset($form_defaults[$arg])) {
                continue;
            }
            if ($arg != $textinput && $val == $defaults[$arg]) {
                continue;
            }

            $i = HTML::input(array('name' => $arg, 'value' => $val));

            if ($arg == $textinput) {
                //if ($inputs[$arg] == 'file')
                //    $attr['type'] = 'file';
                //else
                $i->setAttr('type', 'text');
                $i->setAttr('size', $args['formsize']);
                if ($args['description']) {
                    $i->addTooltip($args['description']);
                }
            } else {
                $i->setAttr('type', 'hidden');
            }
            $contents->pushContent($i);

            // FIXME: hackage
            if ($i->getAttr('type') == 'file') {
                $form->setAttr('enctype', 'multipart/form-data');
                $form->setAttr('method', 'post');
                $contents->pushContent(HTML::input(array('name' => 'MAX_FILE_SIZE',
                    'value' => MAX_UPLOAD_SIZE,
                    'type' => 'hidden')));
            }
        }

        if (!empty($args['buttontext'])) {
            $contents->pushContent(HTML::input(array('type' => 'submit',
                'class' => 'button',
                'value' => $args['buttontext'])));
        }
        $form->pushContent($contents);
        return $form;
    }

    // box is used to display a fixed-width, narrow version with common header
    /**
     * @param string $args
     * @param WikiRequest $request
     * @param string $basepage
     * @return $this|HtmlElement
     */
    public function box($args = '', $request = null, $basepage = '')
    {
        if (!$request) {
            $request =& $GLOBALS['request'];
        }
        $dbi = $request->getDbh();
        return $this->makeBox('', $this->run($dbi, $args, $request, $basepage));
    }

    public function makeBox($title, $body)
    {
        if (!$title) {
            $title = $this->getName();
        }
        return HTML::div(
            array('class' => 'box'),
            HTML::div(array('class' => 'box-title'), $title),
            HTML::div(array('class' => 'box-data'), $body)
        );
    }

    public function error($message)
    {
        return HTML::span(
            array('class' => 'error'),
            HTML::strong(fmt("Plugin %s failed.", $this->getName())),
            ' ',
            $message
        );
    }

    public function disabled($message = '')
    {
        $html[] = HTML::div(
            array('class' => 'title'),
            fmt("Plugin %s disabled.", $this->getName()),
            ' ',
            $message
        );
        $html[] = HTML::pre($this->_pi);
        return HTML::div(array('class' => 'disabled-plugin'), $html);
    }

    // TODO: Not really needed, since our plugins generally initialize their own
    // PageList object, which accepts options['types'].
    // Register custom PageList types for special plugins, like
    // 'hi_content' for WikiAdminSearcheplace, 'renamed_pagename' for WikiAdminRename, ...
    public function addPageListColumn($array)
    {
        global $customPageListColumns;
        if (empty($customPageListColumns)) {
            $customPageListColumns = array();
        }
        foreach ($array as $column => $obj) {
            $customPageListColumns[$column] = $obj;
        }
    }

    // provide a sample usage text for automatic edit-toolbar insertion
    public function getUsage()
    {
        $args = $this->getDefaultArguments();
        $string = '<<' . $this->getName() . ' ';
        if ($args) {
            foreach ($args as $key => $value) {
                $string .= ($key . "||=" . (string)$value . " ");
            }
        }
        return $string . '>>';
    }

    public function getArgumentsDescription()
    {
        $arguments = HTML();
        foreach ($this->getDefaultArguments() as $arg => $default) {
            if (!empty($default) && stristr($default, ' ')) {
                $default = "'$default'";
            }
            $arguments->pushContent("$arg=$default", HTML::br());
        }
        return $arguments;
    }
}

class WikiPluginLoader
{
    public $_errors;

    public function expandPI($pi, &$request, &$markup, $basepage = false)
    {
        if (!($ppi = $this->parsePI($pi))) {
            return false;
        }
        list($pi_name, $plugin, $plugin_args) = $ppi;

        if (!is_object($plugin)) {
            return new HtmlElement(
                'div',
                array('class' => 'error'),
                $this->getErrorDetail()
            );
        }
        switch ($pi_name) {
            case 'plugin':
                // FIXME: change API for run() (no $dbi needed).
                $dbi = $request->getDbh();
                // pass the parsed CachedMarkup context in dbi to the plugin
                // to be able to know about itself, or even to change the markup XmlTree (CreateToc)
                $dbi->_markup = &$markup;
                // FIXME: could do better here...
                if (!$plugin->managesValidators()) {
                    // Output of plugin (potentially) depends on
                    // the state of the WikiDB (other than the current
                    // page.)

                    // Lacking other information, we'll assume things
                    // changed last time the wikidb was touched.

                    // As an additional hack, mark the ETag weak, since,
                    // for all we know, the page might depend
                    // on things other than the WikiDB (e.g. Calendar...)

                    $timestamp = $dbi->getTimestamp();
                    $request->appendValidators(array('dbi_timestamp' => $timestamp,
                        '%mtime' => (int)$timestamp,
                        '%weak' => true));
                }
                return $plugin->run($dbi, $plugin_args, $request, $basepage);
            case 'plugin-list':
                return $plugin->makeList($plugin_args, $request, $basepage);
            case 'plugin-form':
                return $plugin->makeForm($plugin_args, $request);
        }
        return false;
    }

    public function getWikiPageLinks($pi, $basepage)
    {
        if (!($ppi = $this->parsePI($pi))) {
            return array();
        }
        list($pi_name, $plugin, $plugin_args) = $ppi;
        if (!is_object($plugin)) {
            return array();
        }
        if ($pi_name != 'plugin') {
            return array();
        }
        return $plugin->getWikiPageLinks($plugin_args, $basepage);
    }

    public function parsePI($pi)
    {
        if (!preg_match('/^\s*<\?(plugin(?:-form|-link|-list)?)\s+(\w+)\s*(.*?)\s*\?>\s*$/s', $pi, $m)) {
            return $this->_error(sprintf("Bad %s", 'PI'));
        }

        list(, $pi_name, $plugin_name, $plugin_args) = $m;
        $plugin = $this->getPlugin($plugin_name, $pi);

        return array($pi_name, $plugin, $plugin_args);
    }

    public function getPlugin($plugin_name, $pi = false)
    {
        global $ErrorManager;
        global $AllAllowedPlugins;

        if (in_array($plugin_name, $AllAllowedPlugins) === false) {
            return $this->_error(sprintf(
                _("Plugin “%s” does not exist."),
                $plugin_name
            ));
        }

        // Note that there seems to be no way to trap parse errors
        // from this include.  (At least not via set_error_handler().)
        $plugin_source = "lib/plugin/$plugin_name.php";

        $ErrorManager->pushErrorHandler(new WikiMethodCb($this, '_plugin_error_filter'));
        $plugin_class = "WikiPlugin_$plugin_name";
        if (!class_exists($plugin_class)) {
            $include_failed = !include_once($plugin_source);
            $ErrorManager->popErrorHandler();

            if (!class_exists($plugin_class)) {
                if ($include_failed) {
                    return $this->_error(sprintf(
                        _("Plugin “%s” does not exist."),
                        $plugin_name
                    ));
                }
                return $this->_error(sprintf(_("%s: no such class"), $plugin_class));
            }
        }
        $ErrorManager->popErrorHandler();
        $plugin = new $plugin_class();
        if (!is_subclass_of($plugin, "WikiPlugin")) {
            return $this->_error(sprintf(
                _("%s: not a subclass of WikiPlugin."),
                $plugin_class
            ));
        }

        $plugin->_pi = $pi;
        return $plugin;
    }

    public function _plugin_error_filter($err)
    {
        if (preg_match("/Failed opening '.*' for inclusion/", $err->errstr)) {
            return true;
        } // Ignore this error --- it's expected.
        return false;
    }

    public function getErrorDetail()
    {
        return $this->_errors;
    }

    public function _error($message)
    {
        $this->_errors = $message;
        return false;
    }
}
