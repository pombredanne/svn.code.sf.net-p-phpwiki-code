<?php

rcs_id('$Id: random.php,v 1.3 2002-01-18 22:46:32 carstenklapp Exp $');

class ImageSet {

    /**
     * Constructor
     */
    function ImageSet ($dirname) {
        if (empty($dirname)) {
            trigger_error(sprintf(_("%s is empty."), 'dirname'),
                          E_USER_NOTICE);
            return; // early return
        }
        $this->dirname = $dirname;
        $this->readAvailableImages($this->dirname);
        //trigger_error(sprintf(_("%s images were found"),
        //              count($this->imageList)),
        //              E_USER_NOTICE);//debugging
        if (empty($this->imageList)) {
            trigger_error(sprintf(_("%s is empty."), $this->dirname),
                          E_USER_NOTICE);
            return; // early return
        }
        $this->_srand(); // Start with a good seed.
    }

    function pickRandomImage() {
        $random_num = mt_rand(0,count($this->imageList)-1);
        $imgname = $this->imageList[$random_num];
        //trigger_error(sprintf(_("random image chosen: %s"), $imgname),
        //              E_USER_NOTICE);//debugging
        //return $this->dirname . "/" . $imgname;

        return $imgname;
    }

    /**
     * Prepare a random seed.
     * 
     * How random do you want it? See
     * http://download.php.net/manual/en/function.srand.php
     * mt_srand ((double) microtime() * 1000000 / pi())
     */
    function _srand($seed = '') {
        static $wascalled = FALSE;
        if (!$wascalled) {
            $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
            srand($seed);
            $wascalled = TRUE;
            //trigger_error("new random seed", E_USER_NOTICE);//debugging
        }
    }


    /**
     * Build an array in $this->imageList of image files from
     * $dirname. Files are considered images when it's suffix matches
     * one from $InlineImages.
     *
     * (This is a variation of function LoadDir in lib/loadsave.php)
     * See also http://www.php.net/manual/en/function.readdir.php
     */
    function readAvailableImages() {
        @ $handle = opendir($dir = $this->dirname);
        if (empty($handle)) {
            trigger_error(sprintf(_("Unable to open directory '%s' for reading"),
                                  $dir), E_USER_NOTICE);
            return; // early return
        }

        $this->imageList = array();
        while ($fn = readdir($handle)) {

            if ($fn[0] == '.' || filetype("$dir/$fn") != 'file')
                continue;
            global $InlineImages;
            if (preg_match("/($InlineImages)$/i", $fn)) {
                array_push($this->imageList, "$fn");
            //trigger_error(sprintf(_("found image %s"), $fn), E_USER_NOTICE);//debugging
            }
        }
        closedir($handle);
    }

};

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>