<?php // -*-php-*-
rcs_id('$Id: PhotoAlbum.php,v 1.1 2003-01-05 04:21:06 carstenklapp Exp $');

/**
 * WikiPlugin which makes an 'album' of a set of photos with optional
 * descriptions.
 *
 * @author: Ted Vinke <teddy@jouwfeestje.com>
 *
 * Usage:
 * <?plugin PhotoAlbum
 *          src=http://server/textfile
 *          mode=[column|row]
 *          desc=true
 *          sort=false
 *          height=50%
 *          width=50%
 * ?>
 *
 * 'src' is a CSV textfile which separates filename and description of
 * each photo. Photos listed in textfile have to be in same directory
 * as the file. Descriptions are optional. The 'mode' specifies how
 * it's displayed, 'column' means vertically, 'row' means
 * horizontally.
 *
 * E.g. possible content of a valid textfile:
 *
 * photo-01.jpg; Me and my girlfriend
 * photo-02.jpg
 * christmas.gif; Merry Christmas!
 *
 * Only 'src' parameter is mandatory. Height and width are calculated
 * compared to original metrics retrieved by php function
 * getimagesize() and can be absolute or a percentage (e.g. "50%)
 *
 * TODO:
 *
 * - parse any local directory for pictures
 * - specify picture(s) as parameter(s)
 * - 'advanced' options such as limit pictures on one page, use more
 *   pages, thumbnail function which enlarges pictures on same page...
 * - implement more layout options, such as usage of tables. Currently
 *   row mode with descriptions isn't very nice. :(
 *
 */

define('DESC_SEP', ";");

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
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array('src'      => '',          // textfile
                     'mode'     => 'row',       // 'column' or 'row'
                     'desc'     => true,        // show descriptions
                     'sort'     => false,       // sorted alphabetically
                     'align'    => 'center',    // only for column mode
                     'height'   => '',
                     'width'    => ''
                     );
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        if (!$src) {
            return $this->error(fmt("%s parameter missing", "'src'"));
        }

        if (! IsSafeURL($src)) {
            return $this->error(_("Bad url in src: remove all of <, >, \""));
        }

        @$fp = fopen($src, "r");

        if (!$fp) {
            return $this->error(fmt("Unable to read %s ", $src));
        }

        $photos = array();
        while ($data = fgetcsv ($fp, 1024, DESC_SEP)) {
            if (count($data) == 0 || empty($data[0]))
                continue;

            // otherwise when empty 'undefined index 1' PHP warning appears
            if (empty($data[1]))
                $data[1] = '';

            $photos[count($photos)] = array ("name" => dirname($src) . "/"
                                                       . trim("$data[0]"),
                                             "desc" => trim("$data[1]"),
                                             );
        }

        fclose ($fp);

        if (count($photos) == 0) {
            return;
        }

        if ($sort) {
            sort($photos);
        }

        $bundle = HTML("\n  "); // nicer html ouput

        while (list($key, $value) = each($photos)) {

            if (!$desc) { // no desciptions
                $value["desc"] = '';
            }
            $params = array('alt'    => $value["desc"],
                            'src'    => $value["name"],
                            'border' => "0",
                            );

            $size = @getimagesize($value["name"]);

            // only if width or height are given, we add it to the
            // image parameters
            if (!empty($width)) {
                $newwidth = $this->newSize($size[0], $width);
                $params = array_merge($params, array("width" => $newwidth));
            }

            if (!empty($height)) {
                $newheight = $this->newSize($size[1], $height);
                $params = array_merge($params, array("height" => $newheight));
            }

            $bundle->pushcontent(HTML::img($params), "\n"); // wrap html ouput

            if ($mode == 'column') {
                $bundle->pushcontent(HTML::p($value["desc"]));
            }
            else if ($mode == 'row') {
                $bundle->pushcontent(" ".$value["desc"]." ");
            }
        }

        if ($mode == 'column') {
            return HTML::div(array("align" => $align), $bundle);
        }
        else {
            return $bundle;
        }
    }

    /**
     * Calculate the new size in pixels when the original size with a
     * value is given.
     *
     * Value is either absolute value or HTML percentage e.g. "50%".
     */
    function newSize($oldSize, $value) {

        if (substr($value, strlen($value) - 1) != "%") {
            return $value;
        }
        substr_replace($value, "%", "");
        return ($oldSize*$value)/100;
    }

};

// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
