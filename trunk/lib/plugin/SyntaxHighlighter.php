<?php // -*-php-*-
rcs_id('$Id: SyntaxHighlighter.php,v 1.1 2004-05-14 14:55:52 rurban Exp $');
/**
 Copyright 2004 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * The SyntaxHighlighter plugin passes all its arguments through a C++ 
 * highlighter called "highlight" (available at http://www.andre-simon.de/).
 *
 * @author: alecthomas
 * 
 * syntax: See http://www.andre-simon.de/doku/highlight/highlight.html
 * style = ["ansi", "gnu", "kr", "java", "linux"]
 
<?plugin SyntaxHighlighter syntax=c style=kr
 #include <stdio.h>
 
 int main() {
 printf("Lalala\n");
 }
?>

 I did not use beautifier, because it used up more than 8M of memory on
 my system and PHP killed it. I'm not sure whether this is a problem
 with my integration, or with beautifier itself.

Fixes by Reini Urban:
  support options: syntax, style, color.
  php version switch
  HIGHLIGHT_DATA_DIR, HIGHLIGHT_EXE
*/

define('HIGHLIGHT_EXE','highlight');
//define('HIGHLIGHT_EXE','/usr/local/bin/highlight');

// highlight requires two subdirs themes and langDefs somewhere.
if (isWindows())
    define('HIGHLIGHT_DATA_DIR','f:\cygnus\usr\local\share\highlight');
else  
    define('HIGHLIGHT_DATA_DIR','/usr/local/share/highlight');

class WikiPlugin_SyntaxHighlighter
extends WikiPlugin
{
    function getName () {
        return _("SyntaxHighlighter");
    }
    function getDescription () {
        return _("Source code syntax highlighter (via http://www.andre-simon.de)");
    }
    function managesValidators() {
        return true;
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }
    function getDefaultArguments() {
        return array(
                     'syntax' => null,
                     'style'  => null,
                     'color'  => null,
                     'number' => 0,
                     'wrap'   => 0,
                     );
    }
    function handle_plugin_args_cruft(&$argstr, &$args) {
        $this->source = $argstr;
    }

    function newFilterThroughCmd($input, $commandLine) {
        $descriptorspec = array(
               0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
               1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
               2 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        );

        $process = proc_open("$commandLine", $descriptorspec, $pipes);
        if (is_resource($process)) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable  handle connected to child stdout
            // 2 => readable  handle connected to child stderr
            fwrite($pipes[0], $input);
            fclose($pipes[0]);
            $buf = "";
            while(!feof($pipes[1])) {
                $buf .= fgets($pipes[1], 1024);
            }
            fclose($pipes[1]);
            $stderr = '';
            while(!feof($pipes[2])) {
                $stderr .= fgets($pipes[2], 1024);
            }
            fclose($pipes[2]);
            // It is important that you close any pipes before calling
            // proc_close in order to avoid a deadlock
            $return_value = proc_close($process);
            if (empty($buf)) printXML($this->error($stderr));
            return $buf;
        }
    }

    /* PHP versions < 4.3
     * TODO: via temp file looks more promising
     */
    function OldFilterThroughCmd($input, $commandLine) {
         $input = str_replace ("\\", "\\\\", $input);
         $input = str_replace ("\"", "\\\"", $input);
         $input = str_replace ("\$", "\\\$", $input);
         $input = str_replace ("`", "\`", $input);
         $input = str_replace ("'", "\'", $input);
         //$input = str_replace (";", "\;", $input);

         $pipe = popen("echo \"$input\"|$commandLine", 'r');
         if (!$pipe) {
            print "pipe failed.";
            return "";
         }
         $output = '';
         while (!feof($pipe)) {
            $output .= fread($pipe, 1024);
         }
         pclose($pipe);
         return $output;
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        $source =& $this->source;
        if (empty($syntax)) return $this->error(_("Syntax language not specified)"));
        if (!empty($source)) {
            $args = "";
            if (defined('HIGHLIGHT_DATA_DIR'))
                $args .= " --data-dir " . HIGHLIGHT_DATA_DIR;
            if ($number != 0) $args .= " -l";
            if ($wrap != 0)   $args .= " -V";
            if (!empty($color)) $args .= " --style $color -c ".FindFile("uploads")."/highlight.css";
            if (!empty($style)) $args .= " -F $style";
            $commandLine = HIGHLIGHT_EXE . "$args -q -X -f -S $syntax";
            if (check_php_version(4,3,0))
                $code = $this->newFilterThroughCmd($source, $commandLine);
            else 
                $code = $this->oldFilterThroughCmd($source, $commandLine);
            if (empty($code))
                return $this->error(fmt("Couldn't start commandline '%s'",$commandLine));
            $html = HTML::pre(HTML::raw($code));

            global $Theme;
            $html->setAttr('class','tightenable top bottom');
            $css = $Theme->_CSSlink('',empty($color) ? 'highlight.css' : 'uploads/highlight.css','');
            return HTML($css,$html);
        } else {
            return $this->error(_("empty source"));
        }
    }
};

// $Log: not supported by cvs2svn $
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
