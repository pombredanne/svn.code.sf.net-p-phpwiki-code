<?php // -*-php-*-
rcs_id('$Id: Ploticus.php,v 1.3 2004-06-03 09:40:57 rurban Exp $');
/*
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
 * The Ploticus plugin passes all its arguments to the ploticus 
 * binary and displays the result as PNG, GIF, EPS, SVG or SWF.
 * Ploticus is a free, GPL, non-interactive software package 
 * for producing plots, charts, and graphics from data.
 * See http://ploticus.sourceforge.net/doc/welcome.html
 *
 * @Author: Reini Urban
 *
 * Note: 
 * - For windows you need either a gd library with GIF support or 
 *   a ploticus with PNG support. This comes only with the cygwin built.
 * - We support only images supported by GD so far (PNG most likely). 
 *   No EPS, PS, SWF, SVG or SVGZ support due to limitations in WikiPluginCached.
 *   This will be fixed soon.
 *
 * Usage:
<?plugin Ploticus device=png [ploticus options...]
   multiline ploticus script ...
?>
 * or without any script: (not tested)
<?plugin Ploticus -prefab vbars data=myfile.dat delim=tab y=1 clickmapurl="http://mywiki.url/wiki/?pagename=@2" clickmaplabel="@3" -csmap ?>
 *
 * TODO: PloticusSql - create intermediate data from SQL. Similar to SqlResult, just in graphic form.
 * For example to produce nice looking pagehit statistics or ratings statistics.
 */

if (!defined("PLOTICUS_EXE"))
  if (isWindows())
    define('PLOTICUS_EXE','pl.exe');
  else
    define('PLOTICUS_EXE','/usr/local/bin/pl');

require_once "lib/WikiPluginCached.php"; 

class WikiPlugin_Ploticus
extends WikiPluginCached
{
    /**
     * Sets plugin type to MAP if -csmap (-map or -mapdemo or -csmapdemo not supported)
     * or HTML if the imagetype is not supported by GD (EPS, SVG, SVGZ) (not yet)
     * or IMAGE if device = png, gif or jpeg
     */
    function getPluginType() {
    	if (!empty($this->_args['-csmap']))
    	    return PLUGIN_CACHED_MAP; // not yet tested
    	else    
            return PLUGIN_CACHED_IMG_INLINE;
    }
    function getName () {
        return _("Ploticus");
    }
    function getDescription () {
        return _("Ploticus image creation");
    }
    function managesValidators() {
        return true;
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }
    function getDefaultArguments() {
        return array(
                     'device' => 'png', // png,gif,svgz,svg,...
                     '-csmap' => false,
                     'alt'    => false,
                     'help'   => false,
                     );
    }
    function handle_plugin_args_cruft(&$argstr, &$args) {
        $this->source = $argstr;
    }
    /**
     * Sets the expire time to one day (so the image producing
     * functions are called seldomly) or to about two minutes
     * if a help screen is created.
     */
    function getExpire($dbi, $argarray, $request) {
        if (!empty($argarray['help']))
            return '+120'; // 2 minutes
        return sprintf('+%d', 3*86000); // approx 3 days
    }

    /**
     * Sets the imagetype according to user wishes and
     * relies on WikiPluginCached to catch illegal image
     * formats.
     * (I feel unsure whether this option is reasonable in
     *  this case, because png will definitely have the
     *  best results.)
     *
     * @return string 'png', 'jpeg', 'gif'
     */
    function getImageType($dbi, $argarray, $request) {
        return $argarray['device'];
    }

    /**
     * This gives an alternative text description of
     * the image.
     */
    function getAlt($dbi, $argstr, $request) {
        return (!empty($this->_args['alt'])) ? $this->_args['alt']
                                             : $this->getDescription();
    }

    /**
     * Returns an image containing a usage description of the plugin.
     *
     * TODO: -csmap pointing to the Ploticus documentation at sf.net.
     * @return string image handle
     */
    function helpImage() {
        $def = $this->defaultArguments();
        $other_imgtypes = $GLOBALS['CacheParams']['imgtypes'];
        unset ($other_imgtypes[$def['imgtype']]);
        $helparr = array(
            '<?plugin Ploticus ' .
            'device'           => ' = "' . $def['device'] . "(default)|" . join('|',$GLOBALS['CacheParams']['imgtypes']).'"',
            'alt'              => ' = "alternate text"',
            '-csmap'           => ' bool: clickable map?',
            'help'             => ' bool: displays this screen',
            '...'              => ' all further lines below the first plugin line ',
            ''                 => ' and inside the tags are the ploticus script.',
            "\n  ?>"
            );
        $length = 0;
        foreach($helparr as $alignright => $alignleft) {
            $length = max($length, strlen($alignright));
        }
        $helptext ='';
        foreach($helparr as $alignright => $alignleft) {
            $helptext .= substr('                                                        '
                                . $alignright, -$length).$alignleft."\n";
        }
        return $this->text2img($helptext, 4, array(1, 0, 0),
                               array(255, 255, 255));
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

    function getImage($dbi, $argarray, $request) {
        //extract($this->getArgs($argstr, $request));
        //extract($argarray);
        $source =& $this->source;
        if (!empty($source)) {
            $html = HTML();
            $cacheparams = $GLOBALS['CacheParams'];
            $tempfiles = tempnam($cacheparams['cache_dir'], 'Ploticus');
            $gif = $argarray['device'];
            $args = " -stdin -$gif -o $tempfiles.$gif";
            if (!empty($argarray['-csmap'])) {
            	$args .= " -csmap -mapfile $tempfiles.map";
            	$this->_mapfile = "$tempfiles.map";
            }
            $commandLine = PLOTICUS_EXE . "$args";
            if (check_php_version(4,3,0))
                $code = $this->newFilterThroughCmd($source, $commandLine);
            else 
                $code = $this->oldFilterThroughCmd($source, $commandLine);
            //if (empty($code))
            //    return $this->error(fmt("Couldn't start commandline '%s'", $commandLine));
            if (! file_exists("$tempfiles.$gif") )
                return $this->error(fmt("Ploticus error: Outputfile '%s' not created", $tempfiles.$gif));
            $ImageCreateFromFunc = "ImageCreateFrom$gif";
            $img = $ImageCreateFromFunc( "$tempfiles.$gif" );
            return $img;
        } else {
            return $this->error(fmt("empty source"));
        }
    }
    
    function getMap($dbi, $argarray, $request) {
    	$img = $this->getImage($dbi, $argarray, $request);
    	return array($this->_mapfile, $img);
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.2  2004/06/02 19:37:07  rurban
// extended description
//
// Revision 1.1  2004/06/02 19:12:42  rurban
// new Ploticus plugin
//
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
