<?php // -*-php-*-
rcs_id('$Id: GraphViz.php,v 1.2 2004-12-14 21:34:22 rurban Exp $');
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
 * The GraphViz plugin passes all its arguments to the grapviz dot
 * binary and displays the result as cached image (PNG,GIF,SVG) or imagemap.
 *
 * @Author: Reini Urban
 *
 * Note: 
 * - We support only images supported by GD so far (PNG most likely). 
 *   EPS, PS, SWF, SVG or SVGZ and imagemaps need to be tested.
 *
 * Usage:
<?plugin GraphViz [options...]
   multiline dot script ...
?>

 * See also: VisualWiki, which also uses dot and WikiPluginCached.
 *
 * TODO: 
 * - neato binary ?
 * - expand embedded <!plugin-list pagelist !> within the digraph script.
 */

if (!defined("GRAPHVIZ_EXE"))
  if (isWindows())
    define('GRAPHVIZ_EXE','dot.exe');
  else
    define('GRAPHVIZ_EXE','/usr/local/bin/dot');

require_once "lib/WikiPluginCached.php"; 

class WikiPlugin_GraphViz
extends WikiPluginCached
{
    /**
     * Sets plugin type to MAP if -csmap (-map or -mapdemo or -csmapdemo not supported)
     * or HTML if the imagetype is not supported by GD (EPS, SVG, SVGZ) (not yet)
     * or IMG_INLINE if device = png, gif or jpeg
     */
    function getPluginType() {
    	if (!empty($this->_args['-csmap']))
    	    return PLUGIN_CACHED_MAP; // not yet tested
        $type = $this->decideImgType($this->_args['imgtype']);
        if ($type == $this->_args['imgtype'])
            return PLUGIN_CACHED_IMG_INLINE;
        $device = strtolower($this->_args['imgtype']);
    	if (in_array($device, array('svg','swf','svgz','eps','ps'))) {
            switch ($this->_args['device']) {
            	case 'svg':
            	case 'svgz':
                   return PLUGIN_CACHED_STATIC | PLUGIN_CACHED_SVG_PNG;
            	case 'swf':
                   return PLUGIN_CACHED_STATIC | PLUGIN_CACHED_SWF;
                default: 
                   return PLUGIN_CACHED_STATIC | PLUGIN_CACHED_HTML;
            }
        }
    	else
            return PLUGIN_CACHED_IMG_INLINE; // normal cached libgd image handles
    }
    function getName () {
        return _("GraphViz");
    }
    function getDescription () {
        return _("GraphViz image or imagemap creation of directed graphs");
    }
    function managesValidators() {
        return true;
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.2 $");
    }
    function getDefaultArguments() {
        return array(
                     'imgtype' => 'png', // png,gif,svgz,svg,...
                     'alt'     => false,
                     //'data'    => false, // <!plugin-list !> support
                     'help'    => false,
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
     * @return string 'png', 'jpeg', 'gif'
     */
    function getImageType($dbi, $argarray, $request) {
        return $argarray['imgtype'];
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
     * TODO: *map features.
     * @return string image handle
     */
    function helpImage() {
        $def = $this->defaultArguments();
        //$other_imgtypes = $GLOBALS['PLUGIN_CACHED_IMGTYPES'];
        //unset ($other_imgtypes[$def['imgtype']]);
        $imgtypes = $GLOBALS['PLUGIN_CACHED_IMGTYPES'];
        $imgtypes = array_merge($imgtypes, array("svg", "svgz", "imap", "cmapx", "ismap", "cmap"));
        $helparr = array(
            '<?plugin GraphViz ' .
            'imgtype'           => ' = "' . $def['imgtype'] . "(default)|" . join('|',$imgtypes).'"',
            'alt'              => ' = "alternate text"',
            //'data'             => ' <!plugin-list !>: pagelist as input',
            'help'             => ' bool: displays this screen',
            '...'              => ' all further lines below the first plugin line ',
            ''                 => ' and inside the tags are the dotty script.',
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

    function getImage($dbi, $argarray, $request) {
        //extract($this->getArgs($argstr, $request));
        //extract($argarray);
        $source =& $this->source;
        if (!empty($source)) {
            //TODO: parse lines for <!plugin-list !> pagelists
            /*
            if (is_array($argarray['data'])) { // support <!plugin-list !> pagelists
                $src = ""; //#proc getdata\ndata:";
                $i = 0;
                foreach ($argarray['data'] as $data) {
                    // hash or array?
                    if (is_array($data))
                        $src .= ("\t" . join(" ", $data) . "\n");
                    else
                        $src .= ("\t" . '"' . $data . '" ' . $i++ . "\n");
                }
                $src .= $source;
                $source = $src;
            }
            */
            $tempfile = $this->tempnam('Graphviz');
            unlink($tempfile);
            $gif = $argarray['imgtype'];
            $args = " -T$gif -o $tempfile.$gif";
            if (in_array($gif, array("imap", "cmapx", "ismap", "cmap")))
                $this->_mapfile = "$tempfile.map";
            $code = $this->filterThroughCmd($source, GRAPHVIZ_EXE . "$args");
            //if (empty($code))
            //    return $this->error(fmt("Couldn't start commandline '%s'", $commandLine));
            sleep(1);
            if (! file_exists("$tempfile.$gif") ) {
                $this->_errortext .= sprintf(_("%s error: outputfile '%s' not created"), 
                                             "GraphViz", "$tempfile.$gif");
                $this->_errortext .= ("\ncmd-line: cat script | " . GRAPHVIZ_EXE . "$args");
                return false;
            }
            $ImageCreateFromFunc = "ImageCreateFrom$gif";
            if (function_exists($ImageCreateFromFunc))
                return $ImageCreateFromFunc( "$tempfile.$gif" );
            return "$tempfile.$gif";
        } else {
            return $this->error(fmt("empty source"));
        }
    }
    
    // which argument must be set to 'png', for the fallback image when svg will fail on the client.
    // type: SVG_PNG
    function pngArg() {
    	return 'imgtype';
    }
    
    function getMap($dbi, $argarray, $request) {
    	$img = $this->getImage($dbi, $argarray, $request);
    	return array($this->_mapfile, $img);
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.1  2004/12/13 14:45:33  rurban
// new generic GraphViz plugin: similar to Ploticus
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
