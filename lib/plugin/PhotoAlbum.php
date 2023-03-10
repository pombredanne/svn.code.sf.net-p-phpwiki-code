<?php
/**
 * Copyright © 2003,2004,2005,2007 $ThePhpWikiProgrammingTeam
 * Copyright © 2009 Marc-Etienne Vargenau, Alcatel-Lucent
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

/**
 * Display an album of a set of photos with optional descriptions.
 *
 * @author: Ted Vinke <teddy@jouwfeestje.com>
 *          Reini Urban (local fs)
 *          Thomas Harding (slides mode, real thumbnails)
 */

/**
 * TODO:
 * - specify picture(s) as parameter(s)
 * - limit amount of pictures on one page
 *
 * Fixed album location idea by Philip J. Hollenback. Thanks!
 */

class WikiPlugin_PhotoAlbum extends WikiPlugin
{
    public function getDescription()
    {
        return _("Display a set of photos listed in a text file with optional descriptions.");
    }

    public function getDefaultArguments()
    {
        return array('src' => '', // textfile of image list, or local dir.
            'url' => '', // if src=localfs, url prefix (webroot for the links)
            'mode' => 'normal', // normal|thumbs|tiles|list
            // "normal" - Normal table which shows photos full-size
            // "thumbs" - WinXP thumbnail style
            // "tiles"  - WinXP tiles style
            // "list"   - WinXP list style
            // "row"    - inline thumbnails
            // "column" - photos full-size, displayed in 1 column
            // "slide"  - slideshow mode, needs javascript on client
            'numcols' => 3, // photos per row, columns
            'showdesc' => 'both', // none|name|desc|both
            // "none"   - No descriptions next to photos
            // "name"   - Only filename shown
            // "desc"   - Only description (from textfile) shown
            // "both"     - If no description found, then filename will be used
            'link' => true, // show link to original sized photo
            // If true, each image will be hyperlinked to a page where the single
            // photo will be shown full-size. Only works when mode != 'normal'
            'attrib' => '', // 'sort, nowrap, alt'
            // attrib arg allows multiple attributes: attrib=sort,nowrap,alt
            // 'sort' sorts alphabetically, 'nowrap' for cells, 'alt' to use
            // descs instead of filenames in image ALT-tags
            'bgcolor' => '#eae8e8', // cell bgcolor (lightgrey)
            'hlcolor' => '#c0c0ff', // highlight color (lightblue)
            'align' => 'center', // alignment of table
            'height' => 'auto', // image height (auto|75|100%)
            'width' => 'auto', // image width (auto|75|100%)
            // Size of shown photos. Either absolute value (e.g. "50") or
            // HTML style percentage (e.g. "75%") or "auto" for no special
            // action.
            'cellwidth' => 'image', // cell (auto|equal|image|75|100%)
            // Width of cells in table. Either absolute value in pixels, HTML
            // style percentage, "auto" (no special action), "equal" (where
            // all columns are equally sized) or "image" (take height and
            // width of the photo in that cell).
            'tablewidth' => false, // table (75|100%)
            'p' => false, // "displaythissinglephoto.jpg"
            'h' => false, // "highlightcolorofthisphoto.jpg"
            'duration' => 6, // in slide mode, in seconds
            'thumbswidth' => 80 //width of thumbnails
        );
    }

    // descriptions (instead of filenames) for image alt-tags

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        extract($this->getArgs($argstr, $request));

        $attributes = $attrib ? explode(",", $attrib) : array();
        $photos = array();
        $html = HTML();
        $count = 0;
        // check all parameters
        // what type do we have?
        if (!$src) {
            $showdesc = 'none';
            $src = $request->getArg('pagename');
            $error = $this->fromLocation($src, $photos);
        } else {
            $error = $this->fromFile($src, $photos, $url);
        }
        if ($error) {
            return $this->error($error);
        }

        if ($numcols < 1) {
            $numcols = 1;
        }
        if ($align != 'left' && $align != 'center' && $align != 'right') {
            $align = 'center';
        }
        if (count($photos) == 0) {
            return HTML::raw('');
        }

        if (in_array("sort", $attributes)) {
            sort($photos);
        }

        if ($p) {
            $mode = "normal";
        }

        if ($mode == "column") {
            $mode = "normal";
            $numcols = "1";
        }

        // set some fixed properties for each $mode
        if ($mode == 'thumbs' || $mode == 'tiles') {
            $attributes = array_merge($attributes, array('alt' => ''));
            $attributes = array_merge($attributes, array('nowrap' => 'nowrap'));
            $cellwidth = 'auto'; // else cell won't nowrap
            if ($width == 'auto') {
                $width = 70;
            }
        } elseif ($mode == 'list') {
            $numcols = 1;
            $cellwidth = "auto";
            if ($width == 'auto') {
                $width = 50;
            }
        } elseif ($mode == 'slide') {
            $tableheight = 0;
            $cell_width = 0;
            $numcols = count($photos);
            $keep = $photos;
            foreach ($photos as $key => $value) {
                list($x, $y, $s, $t) = @getimagesize($value['src']);
                if ($height != 'auto') {
                    $y = $this->newSize($y, $height);
                }
                if ($width != 'auto') {
                    $y = round($y * $this->newSize($x, $width) / $x);
                }
                if ($x > $cell_width) {
                    $cell_width = $x;
                }
                if ($y > $tableheight) {
                    $tableheight = $y;
                }
            }
            $tableheight += 50;
            $photos = $keep;
            unset($x, $y, $s, $t, $key, $value, $keep);
        }

        $row = HTML();
        $duration = 1000 * $duration;
        if ($mode == 'slide') {
            $row->pushContent(JavaScript("
i = 0;
function display_slides() {
  j = i - 1;
  cell0 = document.getElementById('wikislide' + j);
  cell = document.getElementById('wikislide' + i);
  if (cell0 != null)
    cell0.style.display='none';
  if (cell != null)
    cell.style.display='block';
  i += 1;
  if (cell == null) i = 0;
  setTimeout('display_slides()',$duration);
}
display_slides();"));
        }

        foreach ($photos as $key => $value) {
            if ($p && basename($value["name"]) != "$p") {
                continue;
            }
            if ($h && basename($value["name"]) == "$h") {
                $color = $hlcolor ? $hlcolor : $bgcolor;
            } else {
                $color = $bgcolor;
            }
            // $params will be used for each <img > tag
            $params = array('src' => $value["name"],
                'src_tile' => $value["name_tile"],
                'alt' => ($value["desc"] != "" and in_array("alt", $attributes))
                    ? $value["desc"]
                    : basename($value["name"]));
            if (!@empty($value['location'])) {
                $params = array_merge($params, array("location" => $value['location']));
            }
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
                    if (!$value["desc"]) {
                        $value["desc"] = basename($value["name"]);
                    }
                    break;
            }

            $size = getimagesize($value["src"]); // try " " => "\\ "
            $newwidth = $this->newSize($size[0], $width);
            if ($width != 'auto' && $newwidth > 0) {
                if (is_numeric($newwidth)) {
                    $newwidthpx = $newwidth.'px';
                } else {
                    $newwidthpx = $newwidth;
                }
                $params = array_merge($params, array("style" => 'width: '.$newwidthpx));
            }
            if (($mode == 'thumbs' || $mode == 'tiles' || $mode == 'list')) {
                if (!empty($size[0])) {
                    $newheight = round($newwidth * $size[1] / $size[0]);
                    $params['width'] = $newwidth;
                    $params['height'] = $newheight;
                } else {
                    $newheight = '';
                }
                if ($height == 'auto') {
                    $height = 150;
                }
            } else {
                $newheight = $this->newSize($size[1], $height);
                if ($height != 'auto' && $newheight > 0) {
                    $params = array_merge($params, array("height" => $newheight));
                }
            }

            // cell operations
            $cell = array(
                'class' => 'photoalbum cell align-center top',
                'style' => "background-color: $color");
            if ($cellwidth != 'auto') {
                if ($cellwidth == 'equal') {
                    $newcellwidth = round(100 / $numcols) . "%";
                } elseif ($cellwidth == 'image') {
                    $newcellwidth = $newwidth;
                } else {
                    $newcellwidth = $cellwidth;
                }
                if (is_numeric($newcellwidth)) {
                    $newcellwidth = $newcellwidth.'px';
                }
                $cell = array_merge($cell, array("style" => 'width: '.$newcellwidth));
            }
            if (in_array("nowrap", $attributes)) {
                $cell = array_merge($cell, array("nowrap" => "nowrap"));
            }
            //create url to display single larger version of image on page
            $url = WikiURL(
                $request->getPage(),
                array("p" => basename($value["name"]))
            )
                . "#"
                . rawurlencode(basename($value["name"]));

            $b_url = WikiURL(
                $request->getPage(),
                array("h" => basename($value["name"]))
            )
                . "#"
                . rawurlencode(basename($value["name"]));
            $url_text = $link
                ? HTML::a(array("href" => "$url"), basename($value["desc"]))
                : basename($value["name"]);
            if (!$p) {
                if ($mode == 'normal' || $mode == 'slide') {
                    if (!@empty($params['location'])) {
                        $params['src'] = $params['location'];
                    }
                    unset($params['location'], $params['src_tile']);
                    $url_image = $link ? HTML::a(array("id" => rawurlencode(basename($value["name"])).'-'.rand(),
                        "href" => "$url"), HTML::img($params))
                        : HTML::img($params);
                } else {
                    $keep = $params;
                    if (!@empty($params['src_tile'])) {
                        $params['src'] = $params['src_tile'];
                    }
                    unset($params['location'], $params['src_tile']);
                    $url_image = $link ? HTML::a(
                        array("id" => rawurlencode(basename($value["name"])).'-'.rand(),
                            "href" => "$url"),
                        $this->image_tile($params)
                    )
                        : HTML::img($params);
                    $params = $keep;
                    unset($keep);
                }
            } else {
                if (!@empty($params['location'])) {
                    $params['src'] = $params['location'];
                }
                unset($params['location'], $params['src_tile']);
                $url_image = $link ? HTML::a(array("id" => rawurlencode(basename($value["name"])).'-'.rand(),
                    "href" => "$b_url"), HTML::img($params))
                    : HTML::img($params);
            }
            // here we use different modes
            if ($mode == 'tiles') {
                $row->pushContent(
                    HTML::td(
                        $cell,
                        HTML::div(array('class' => 'top'), $url_image),
                        HTML::div(
                            array('class' => 'bottom'),
                            HTML::div(
                                array('class' => 'boldsmall'),
                                ($url_text)
                            ),
                            HTML::br(),
                            HTML::div(
                                array('class' => 'gensmall'),
                                ($size[0] .
                                    " x " .
                                    $size[1] .
                                    " pixels")
                            )
                        )
                    )
                );
            } elseif ($mode == 'list') {
                $desc = ($showdesc != 'none') ? $value["desc"] : '';
                $row->pushContent(
                    HTML::td(
                        array("class" => "top",
                            "nowrap" => 0,
                            'style' => "background-color: $color"),
                        HTML::div(array('class' => 'boldsmall'), ($url_text))
                    )
                );
                $row->pushContent(
                    HTML::td(
                        array("class" => "top",
                            "nowrap" => 0,
                            'style' => "background-color: $color"),
                        HTML::div(
                            array('class' => 'gensmall'),
                            ($size[0] .
                                " x " .
                                $size[1] .
                                " pixels")
                        )
                    )
                );

                if ($desc != '') {
                    $row->pushContent(
                        HTML::td(
                            array("class" => "top",
                                "nowrap" => 0,
                                'style' => "background-color: $color"),
                            HTML::div(array('class' => 'gensmall'), $desc)
                        )
                    );
                }
            } elseif ($mode == 'thumbs') {
                $desc = ($showdesc != 'none') ?
                    HTML::p($url_text) : '';
                $row->pushContent(
                    (HTML::td(
                        $cell,
                        $url_image,
                        HTML::div(array('class' => 'gensmall'), $desc)
                    ))
                );
            } elseif ($mode == 'normal') {
                $desc = ($showdesc != 'none') ? HTML::p($value["desc"]) : '';
                $row->pushContent(
                    (HTML::td(
                        $cell,
                        $url_image,
                        HTML::div(array('class' => 'gensmall'), $desc)
                    ))
                );
            } elseif ($mode == 'slide') {
                if ($newwidth == 'auto' || !$newwidth) {
                    $newwidth = $this->newSize($size[0], $width);
                }
                if ($newwidth == 'auto' || !$newwidth) {
                    $newwidth = $size[0];
                }
                if ($newheight != 'auto') {
                    $newwidth = round($size[0] * $newheight / $size[1]);
                }
                $desc = ($showdesc != 'none') ? HTML::p($value["desc"]) : '';
                if ($count == 0) {
                    $cell = array('style' => 'display: block; '
                        . 'position: absolute; '
                        . 'left: 50%; '
                        . 'margin-left: -' . round($newwidth / 2) . 'px; '
                        . 'text-align: center; '
                        . 'vertical-align: top',
                        'id' => "wikislide" . $count);
                } else {
                    $cell = array('style' => 'display: none; '
                        . 'position: absolute; '
                        . 'left: 50%; '
                        . 'margin-left: -' . round($newwidth / 2) . 'px; '
                        . 'text-align: center; '
                        . 'vertical-align: top',
                        'id' => "wikislide" . $count);
                }
                if ($align == 'left' || $align == 'right') {
                    if ($count == 0) {
                        $cell = array('style' => 'display: block; '
                            . 'position: absolute; '
                            . $align . ': 50px; '
                            . 'vertical-align: top',
                            'id' => "wikislide" . $count);
                    } else {
                        $cell = array('style' => 'display: none; '
                            . 'position: absolute; '
                            . $align . ': 50px; '
                            . 'vertical-align: top',
                            'id' => "wikislide" . $count);
                    }
                }
                $row->pushContent(
                    (HTML::td(
                        $cell,
                        $url_image,
                        HTML::div(array('class' => 'gensmall'), $desc)
                    ))
                );
                $count++;
            } elseif ($mode == 'row') {
                $desc = ($showdesc != 'none') ? HTML::p($value["desc"]) : '';
                $row->pushContent(
                    HTML::table(
                        array("style" => "display: inline",
                            'class' > "photoalbum row"),
                        HTML::tr(HTML::td($url_image)),
                        HTML::tr(HTML::td(
                            array("class" => "gensmall",
                                "style" => "text-align: center; "
                                    . "background-color: $color"),
                            $desc
                        ))
                    )
                );
            } else {
                return $this->error(fmt("Invalid argument: %s=%s", 'mode', $mode));
            }

            // no more images in one row as defined by $numcols
            if (($key + 1) % $numcols == 0 ||
                ($key + 1) == count($photos) ||
                $p
            ) {
                if ($mode == 'row') {
                    $html->pushContent(HTML::div($row));
                } else {
                    $html->pushContent(HTML::tr($row));
                }
                $row = HTML();
            }
        }

        //create main table
        $table_attributes = array(
            "class" => "photoalbum",
            "style" => $tablewidth ? "width: ".$tablewidth : "width: 100%");

        if (!empty($tableheight)) {
            $table_attributes = array_merge(
                $table_attributes,
                array("height" => $tableheight)
            );
        }
        if ($mode != 'row') {
            $html = HTML::table($table_attributes, $html);
        }
        // align all
        return HTML::div(array("class" => "align-".$align), $html);
    }

    /**
     * Calculate the new size in pixels when the original size
     * with a value is given.
     *
     * @param  integer $oldSize Absolute no. of pixels
     * @param  mixed   $value   Either absolute no. or HTML percentage e.g. '50%'
     * @return integer New size in pixels
     */
    private function newSize($oldSize, $value)
    {
        if (trim(substr($value, strlen($value) - 1)) != "%") {
            return $value;
        }
        $value = str_replace("%", "", $value);
        return round(($oldSize * $value) / 100);
    }

    /**
     * fromLocation - read only one picture from fixed album location
     * and return it in array $photos
     *
     * @param string $src Name of page
     * @param array $photos
     * @return string Error if fixed location is not allowed
     */
    private function fromLocation($src, &$photos)
    {
        //FIXME!
        if (!IsSafeURL($src)) {
            return $this->error(_("Bad URL in src"));
        }
        $photos[] = array("name" => $src, "desc" => "");
        return '';
    }

    /**
     * fromFile - read pictures & descriptions (separated by ;)
     *            from $src and return it in array $photos
     *
     * @param  string $src    path to dir or textfile (local or remote)
     * @param  array  $photos
     * @param  string $webpath
     * @return string Error when bad URL or file couldn't be opened
     */
    private function fromFile($src, &$photos, $webpath = '')
    {
        $src_bak = $src;
        if (preg_match("/^Upload:(.*)$/", $src, $m)) {
            $src = getUploadFilePath() . $m[1];
            $webpath = getUploadDataPath() . $m[1];
        }
        //there has a big security hole... as loading config/config.ini !
        if (!preg_match('/(\.csv|\.jpg|\.jpeg|\.png|\.gif|\/)$/', $src)) {
            return $this->error(_("File extension for csv file has to be '.csv'"));
        }
        if (!IsSafeURL($src)) {
            return $this->error(_("Bad URL in src"));
        }
        if (preg_match('/^(http|ftp|https):\/\//i', $src)) {
            $contents = url_get_contents($src);
            $web_location = 1;
        } else {
            $web_location = 0;
            if (string_ends_with($src, "/")) {
                $src = substr($src, 0, -1);
            }
        }
        if (!file_exists($src)
            && defined('PHPWIKI_DIR')
            && file_exists(PHPWIKI_DIR . "/$src")) {
            $src = PHPWIKI_DIR . "/$src";
        }
        // check if src is a directory
        if (file_exists($src) and filetype($src) == 'dir') {
            //all images
            $list = array();
            foreach (array('jpeg', 'jpg', 'png', 'gif') as $ext) {
                $fileset = new FileSet($src, "*.$ext");
                $list = array_merge($list, $fileset->getFiles());
            }
            // convert dirname($src) (local fs path) to web path
            natcasesort($list);
            if (!$webpath) {
                // assume relative src. default: "themes/Hawaiian/images/pictures"
                $webpath = DATA_PATH . '/' . $src_bak;
            }
            foreach ($list as $file) {
                // convert local path to webpath
                $photos[] = array(
                    "name" => $webpath . "/$file",
                    "name_tile" => $src . "/$file",
                    "src" => $src . "/$file",
                    "desc" => "");
            }
            return '';
        }
        // check if $src is an image
        foreach (array('jpeg', 'jpg', 'png', 'gif') as $ext) {
            if (preg_match("/\.$ext$/", $src)) {
                if (!file_exists($src)
                    && defined('PHPWIKI_DIR')
                    && file_exists(PHPWIKI_DIR . "/$src")) {
                    $src = PHPWIKI_DIR . "/$src";
                }
                if ($web_location == 1 and !empty($contents)) {
                    $photos[] = array("src" => $src,
                        "name" => $src,
                        "name_tile" => $src,
                        "desc" => "");
                    return '';
                }
                if (!file_exists($src)) {
                    return $this->error(fmt("Unable to find src=“%s”", $src));
                }
                $photos[] = array("src" => $src,
                    "name" => "../" . $src,
                    "name_tile" => $src,
                    "desc" => "");
                return '';
            }
        }
        if ($web_location == 0) {
            $fp = fopen($src, "r");
            if (!$fp) {
                return $this->error(fmt("Unable to read src=“%s”", $src));
            }
            while ($data = fgetcsv($fp, 1024, ';')) {
                if (count($data) == 0 || empty($data[0])
                    || preg_match('/^#/', $data[0])
                    || preg_match('/^[[:space:]]*$/', $data[0])
                ) {
                    continue;
                }
                if (empty($data[1])) {
                    $data[1] = '';
                }
                $photos[] = array("name" => dirname($src) . "/" . trim($data[0]),
                    "location" => "../" . dirname($src) . "/" . trim($data[0]),
                    "desc" => trim($data[1]),
                    "name_tile" => dirname($src) . "/" . trim($data[0]));
            }
            fclose($fp);
        } elseif ($web_location == 1) {
            //TODO: check if the file is an image
            $contents = preg_split('/\n/', $contents);
            foreach ($contents as $key => $value) {
                $data = preg_split('/\;/', $value);
                if (count($data) == 0 || empty($data[0])
                    || preg_match('/^#/', $data[0])
                    || preg_match('/^[[:space:]]*$/', $data[0])
                ) {
                    continue;
                }
                if (empty($data[1])) {
                    $data[1] = '';
                }
                $photos[] = array("name" => dirname($src) . "/" . trim($data[0]),
                    "src" => dirname($src) . "/" . trim($data[0]),
                    "desc" => trim($data[1]),
                    "name_tile" => dirname($src) . "/" . trim($data[0]));
            }
        }
        return '';
    }

    private function image_tile($params)
    {
        if (IsSafeURL($params['src'], true)) {
            $src = $params['src'];
        } else {
            $src = '/'.$params['src'];
        }
        $width = $params['width'];
        if (is_numeric($width)) {
            $width = $width.'px';
        }
        if (array_key_exists('width', $params)) {
            return HTML::img(array('src' => $src,
                                   'style' => 'width: '.$width,
                                   'alt' => $params['alt']));
        } else {
            return HTML::img(array('src' => $src,
                                   'alt' => $params['alt']));
        }
    }
}
