<?php // -*-php-*-
rcs_id('$Id: PhotoAlbum.php,v 1.10 2004-12-01 19:34:13 rurban Exp $');
/*
 Copyright 2003, 2004 $ThePhpWikiProgrammingTeam
 
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
 * Display an album of a set of photos with optional descriptions.
 *
 * @author: Ted Vinke <teddy@jouwfeestje.com>
 *          Reini Urban (local fs)
 *
 * Usage:
 * <?plugin PhotoAlbum
 *          src="http://server/textfile" or localfile or localdir or nothing
 *          mode=[column|row]
 *          desc=true
 *          sort=false
 *          height=50%
 *          width=50%
 *
 * "src": textfile of images or directory of images (local or remote)
 *      Local or remote e.g. http://myserver/images/MyPhotos.txt or http://myserver/images/
 *      Possible content of a valid textfile:
 * 	photo-01.jpg; Me and my girlfriend
 * 	photo-02.jpg
 * 	christmas.gif; Merry Christmas!

 *     Inside textfile, filenames and optional descriptions are seperated by
 *     semi-colon on each line. Listed files must be in same directory as textfile 
 *     itself, so don't use relative paths inside textfile.
 *
 * "url": defines the the webpath to the srcdir directory (formerly called weblocation)
 */

/**
 * TODO:
 * - implement WinXP style 'slide' mode, javascript tricks
 * - specify picture(s) as parameter(s)
 * - limit amount of pictures on one page
 * - use PHP to really resize or greyscale images (only where GD library supports it)
 *
 * KNOWN ISSUES:
 * - reading height and width from images with spaces in their names fails.
 *
 * Fixed album location idea by Philip J. Hollenback. Thanks!
 */

class WikiPlugin_PhotoAlbum
extends WikiPlugin
{
    function getName () {
        return _("PhotoAlbum");
    }

    function getDescription () {
        return _("Displays a set of photos listed in a text file with optional descriptions");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.10 $");
    }

// Avoid nameclash, so it's disabled. We allow any url.
// define('allow_album_location', true);
// define('album_location', 'http://kw.jouwfeestje.com/foto/redactie');
// define('album_default_extension', '.jpg');
// define('desc_separator', ';');

    function getDefaultArguments() {
        return array('src'      => '',          // textfile of image list, or local dir.
                     'url'      => '',          // if src=localfs, url prefix (webroot for the links)
                     'mode'	=> 'normal', 	// normal|thumbs|tiles|list
                     	// "normal" - Normal table which shows photos full-size
                     	// "thumbs" - WinXP thumbnail style
                     	// "tiles"  - WinXP tiles style
                     	// "list"   - WinXP list style
                     	// "slide"  - Not yet implemented
                     'numcols'	=> 3,		// photos per row, columns
                     'showdesc'	=> 'both',	// none|name|desc|both
                     	// "none"   - No descriptions next to photos
                     	// "name"   - Only filename shown
                     	// "desc"   - Only description (from textfile) shown
                     	// "both"	 - If no description found, then filename will be used
                     'link'	=> true, 	// show link to original sized photo
                     	// If true, each image will be hyperlinked to a page where the single 
                     	// photo will be shown full-size. Only works when mode != 'normal'
                     'attrib'	=> '',		// 'sort, nowrap, alt'
                     	// attrib arg allows multiple attributes: attrib=sort,nowrap,alt
                     	// 'sort' sorts alphabetically, 'nowrap' for cells, 'alt' to use
                        // descs instead of filenames in image ALT-tags
                     'bgcolor'  => '#eae8e8',	// cell bgcolor (lightgrey)
                     'hlcolor'	=> '#c0c0ff',	// highlight color (lightblue)
                     'align'	=> 'center',	// alignment of table
                     'height'   => 'auto',	// image height (auto|75|100%)
                     'width'    => 'auto',	// image width (auto|75|100%)
                     // Size of shown photos. Either absolute value (e.g. "50") or
                     // HTML style percentage (e.g. "75%") or "auto" for no special
                     // action.
                     'cellwidth'=> 'image',	// cell (auto|equal|image|75|100%)
                     // Width of cells in table. Either absolute value in pixels, HTML
                     // style percentage, "auto" (no special action), "equal" (where
                     // all columns are equally sized) or "image" (take height and
                     // width of the photo in that cell).
                     'tablewidth'=> false,	// table (75|100%)
                     'p'	=> false, 	// "displaythissinglephoto.jpg"
                     'h'	=> false, 	// "highlightcolorofthisphoto.jpg"
                     );
    }
    // descriptions (instead of filenames) for image alt-tags

    function run($dbi, $argstr, &$request, $basepage) {
        extract($this->getArgs($argstr, $request));

        $attributes = $attrib ? explode(",", $attrib) : array();
        $photos = array();
        $html = HTML();

        // check all parameters
        // what type do we have?
        if (!$src) {
            $showdesc  = 'none';
            $src   = $request->getArg('pagename');
            $error = $this->fromLocation($src, $photos);
        } else {
            $error = $this->fromFile($src, $photos, $url);
        }
        if ($error) {
            return $this->error($error);
        }

        if ($numcols < 1) $numcols = 1;
        if ($align != 'left' && $align != 'center' && $align != 'right') {
            $align = 'center';
        }
	if (count($photos) == 0) return;

	if (in_array("sort", $attributes))
	    sort($photos);

	if ($p) {
	    $mode = "normal";
	}

	// set some fixed properties for each $mode
	if ($mode == 'thumbs' || $mode == 'tiles') {
	    $attributes = array_merge($attributes, "alt");
	    $attributes = array_merge($attributes, "nowrap");
	    $cellwidth  = 'auto'; // else cell won't nowrap
	    $showdesc   = 'name';
	    $width      = 50;
	} elseif ($mode == 'list') {
	    $numcols    = 1;
	    $cellwidth  = "auto";
	    if ($showdesc != "none") {
	    	$showdesc = "desc";
	    }
	}

	$row = HTML();
	while (list($key, $value) = each($photos))  {
	    if ($p && basename($value["name"]) != "$p") {
	    	continue;
	    }
	    if ($h && basename($value["name"]) == "$h") {
	    	$color = $hlcolor ? $hlcolor : $bgcolor;
	    } else {
	    	$color = $bgcolor;
	    }
	    // $params will be used for each <img > tag
            $params = array('src'    => $value["name"],
	                    'border' => "0",
	                    'alt'    => ($value["desc"] != "" and in_array("alt", $attributes))
                            		? $value["desc"]
                            		: basename($value["name"]));
	    // check description
	    switch ($showdesc) {
	    	case 'none':
	    	    $value["desc"] = '';
	    	    break;
	    	case 'name':
	    	    $value["desc"] = basename($value["name"]);
	    	    break;
	    	case 'desc':
	    	    break;
	    	default: // 'both'
	    	    if (!$value["desc"]) $value["desc"] = basename($value["name"]);
	    	    break;
	    }

	    // FIXME: get getimagesize to work with names with spaces in it.
            // convert $value["name"] from webpath to local path
	    $size = @getimagesize($value["name"]); // try " " => "\\ "
	    if (!$size and !empty($value["src"])) {
		$size = @getimagesize($value["src"]);
		if (!$size) {
	    	    trigger_error("Unable to getimagesize(".$value["name"].")", 
                                  E_USER_NOTICE);
		}
	    }

	    $newwidth = $this->newSize($size[0], $width);
	    $newheight = $this->newSize($size[1], $height);

	    if ($width != 'auto' && $newwidth > 0) {
	        $params = array_merge($params, array("width" => $newwidth));
	    }
	    if ($height != 'auto' && $newheight > 0) {
	        $params = array_merge($params, array("height" => $newheight));
	    }

	    // cell operations
	    $cell = array('align'   => "center",
                          'valign'  => "top",
                          'bgcolor' => "$color");
	    if ($cellwidth != 'auto') {
	    	if ($cellwidth == 'equal') {
	            $newcellwidth = round(100/$numcols)."%";
	        } else if ($cellwidth == 'image') {
	            $newcellwidth = $newwidth;
	        } else {
	            $newcellwidth = $cellwidth;
	        }
                $cell = array_merge($cell, array("width" => $newcellwidth));
	    }
	    if (in_array("nowrap", $attributes)) {
	        $cell = array_merge($cell, array("nowrap" => "nowrap"));
	    }
	    //create url to display single larger version of image on page
	    $url 	= WikiURL($request->getPage(),
	                  array("p" => basename($value["name"])));
	    $b_url	= WikiURL($request->getPage(),
	                  array("h" => basename($value["name"]))).
	                                        "#".
	                                        basename($value["name"]);
	    $url_text 	= $link ? HTML::a(array("href" => "$url"),
	                                        basename($value["name"])) :
	                                        basename($value["name"]);
	    if (! $p) {
	        $url_image = $link ? HTML::a(array("href" => "$url"),
	                                           HTML::img($params)) :
	                                           HTML::img($params);
	    } else {
	        $url_image = $link ? HTML::a(array("href" => "$b_url"),
	                                           HTML::img($params)) :
	                                           HTML::img($params);
	    }
	    $url_text = HTML::a(array("name" => basename($value["name"])),
	                              $url_text);
	    // here we use different modes
	    if ($mode == 'tiles') {
	    	$row->pushContent(HTML::td($cell,
	             HTML::table(array("cellpadding" => 1, "border" => 0),
	             HTML::tr(
	             	   HTML::td(array("valign" => "top", "rowspan" => 2),
	             	                   $url_image),
	            	   HTML::td(array("valign" => "top", "nowrap" => 0),
	            	                  HTML::small(HTML::strong($url_text)),
	            	                  HTML::br(),
	            	                  HTML::small($size[0].
	            	                              " x ".
	            	                              $size[1].
	            	                              " pixels"))
			      ))));
            } elseif ($mode == 'list') {
            	$desc = ($showdesc != 'none') ? $value["desc"] : '';
	        $row->pushContent(
	            HTML::td(array("valign"  => "top",
	                           "nowrap"  => 0,
	                           "bgcolor" => $color),
	                           HTML::small(HTML::strong($url_text))));
	        $row->pushContent(
	            HTML::td(array("valign"  => "top",
	                           "nowrap"  => 0,
	                           "bgcolor" => $color),
	                           HTML::small($size[0].
	                                       " x ".
	                                       $size[1].
	                                       " pixels")));

	        if ($desc != '') {
	            $row->pushContent(HTML::td(array("valign"  => "top",
	                                             "nowrap"  => 0,
	                                             "bgcolor" => $color),
	                                             HTML::small($desc)));
	        }
	    } elseif ($mode == 'thumbs') {
	        $desc = ($showdesc != 'none') ?
	                HTML::p(HTML::a(array("href" => "$url"),
	                                $url_text)) :
	                                '';
                $row->pushContent(
                    (HTML::td($cell,
                              $url_image,
                              // FIXME: no HtmlElement for fontsizes?
                              // rurban: use ->setAttr("style","font-size:small;")
                              //         but better use a css class
                              HTML::span(array('class'=>'gensmall'),$desc)
                              )));
	    } elseif ($mode == 'normal') {
	        $desc = ($showdesc != 'none') ? HTML::p($value["desc"]) : '';
                $row->pushContent(
                    (HTML::td($cell,
                              $url_image,
                              // FIXME: no HtmlElement for fontsizes?
                              HTML::span(array('class'=>'gensmall'),$desc)
                              )));
            //} elseif ($mode == 'slide') {
            //}
	    } else {
                return $this->error(fmt("Invalid argument: %s=%s", 'mode', $mode));
            }

	    // no more images in one row as defined by $numcols
            if ( ($key + 1) % $numcols == 0 ||
                 ($key + 1) == count($photos) ||
                  $p) {
             	$html->pushcontent(HTML::tr($row));
             	$row->setContent('');
            }
        }

        //create main table
        $html = HTML::table(array("border"      => 0,
	               	          "cellpadding" => 5,
		       		  "cellspacing" => 2,
		                  "width"       => $tablewidth),
		                  $html);
        // align all
	return HTML::div(array("align" => $align), $html);
    }

    /**
     * Calculate the new size in pixels when the original size
     * with a value is given.
     *
     * @param integer $oldSize Absolute no. of pixels
     * @param mixed $value Either absolute no. or HTML percentage e.g. '50%'
     * @return integer New size in pixels
     */
    function newSize($oldSize, $value) {
    	if (trim(substr($value,strlen($value)-1)) != "%") {
    	    return $value;
    	}
    	$value = str_replace("%", "", $value);
    	return round(($oldSize*$value)/100);
    }

    /**
    * fromLocation - read only one picture from fixed album_location
    * and return it in array $photos
    *
    * @param string $src Name of page
    * @param array $photos
    * @return string Error if fixed location is not allowed
    */
    function fromLocation($src, &$photos) {
    	/*if (!allow_album_location) {
    	    return $this->error(_("Fixed album location is not allowed. Please specify parameter src."));
        }*/
        //FIXME!
        if (! IsSafeURL($src)) {
            return $this->error(_("Bad url in src: remove all of <, >, \""));
        }
    	$photos[] = array ("name" => $src, //album_location."/$src".album_default_extension,
                           "desc" => "");
    }

    /**
     * fromFile - read pictures & descriptions (separated by ;)
     *            from $src and return it in array $photos
     *
     * @param string $src path to dir or textfile (local or remote)
     * @param array $photos
     * @return string Error when bad url or file couldn't be opened
     */
    function fromFile($src, &$photos, $webpath='') {
    	$src_bak = $src;
        if (! IsSafeURL($src)) {
            return $this->error(_("Bad url in src: remove all of <, >, \""));
        }
        if (preg_match('/^(http|ftp|https):\/\//i', $src)) {
            $src = url_get_contents($src);
        }
        if (!file_exists($src) and file_exists(PHPWIKI_DIR . "/$src")) {
            $src = PHPWIKI_DIR . "/$src";
        }
        // check if src is a directory
        if (file_exists($src) and filetype($src) == 'dir') {
            //all images
            $list = array();
            foreach (array('jpeg','jpg','png','gif') as $ext) {
                $fileset = new fileSet($src, "*.$ext");
                $list = array_merge($list, $fileset->getFiles());
            }
            // convert dirname($src) (local fs path) to web path
            natcasesort($list);
            if (! $webpath ) {
                // assume relative src. default: "themes/Hawaiian/images/pictures"
                $webpath = DATA_PATH . '/' . $src_bak;
            }
            foreach ($list as $file) {
                // convert local path to webpath
                $photos[] = array ("name" => $webpath . "/$file",
                                   "src"  => $src . "/$file",
                                   "desc" => "",
                                   );
            }
            return;
        }
    	@$fp = fopen ($src, "r");
        if (!$fp) {
            return $this->error(fmt("Unable to read src='%s'", $src));
        }
    	while ($data = fgetcsv ($fp, 1024, ';')) {
    	    if (count($data) == 0 || empty($data[0]))
    	        continue;
    	    if (empty($data[1])) $data[1] = '';
	    $photos[] = array ("name" => dirname($src)."/".trim($data[0]),
                               "desc" => trim($data[1]));
        }
        fclose ($fp);
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.9  2004/07/08 20:30:07  rurban
// plugin->run consistency: request as reference, added basepage.
// encountered strange bug in AllPages (and the test) which destroys ->_dbi
//
// Revision 1.8  2004/06/01 15:28:01  rurban
// AdminUser only ADMIN_USER not member of Administrators
// some RateIt improvements by dfrankow
// edit_toolbar buttons
//
// Revision 1.7  2004/05/03 20:44:55  rurban
// fixed gettext strings
// new SqlResult plugin
// _WikiTranslation: fixed init_locale
//
// Revision 1.6  2004/04/18 00:19:30  rurban
// better default example with local src, don't require weblocation for
// the default setup, better docs, fixed ini_get => get_cfg_var("allow_url_fopen"),
// no HttpClient lib yet.
//
// Revision 1.5  2004/03/09 12:10:23  rurban
// fixed getimagesize problem with local dir.
//
// Revision 1.4  2004/02/28 21:14:08  rurban
// generally more PHPDOC docs
//   see http://xarch.tu-graz.ac.at/home/rurban/phpwiki/xref/
// fxied WikiUserNew pref handling: empty theme not stored, save only
//   changed prefs, sql prefs improved, fixed password update,
//   removed REPLACE sql (dangerous)
// moved gettext init after the locale was guessed
// + some minor changes
//
// Revision 1.3  2004/02/27 08:03:35  rurban
// Update from version 1.2 by Ted Vinke
// implemented the localdir support
//
// Revision 1.2  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.1  2003/01/05 04:21:06  carstenklapp
// New plugin by Ted Vinke (sf tracker patch #661189)
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
