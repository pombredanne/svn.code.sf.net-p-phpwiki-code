<?php

rcs_id('$Id: random.php,v 1.2 2002-01-18 20:46:50 carstenklapp Exp $');

class RandomImage {

    /**
     * Constructor
     */
    function RandomImage ($dirname) {
        if (empty($dirname)) {
            trigger_error(sprintf(_("%s is empty."), 'dirname'),
                          E_USER_NOTICE);
            return ""; // early return
        }

        $this->readAvailableImages($dirname);
        //echo "count is " . count($this->imageList) ."<br>\n";//tempdebugcode

        if (empty($this->imageList)) {
            trigger_error(sprintf(_("%s is empty."), $dirname),
                          E_USER_NOTICE);
            return ""; // early return
        }
        $this->_srand(); // Start with a good seed.

        $random_num = mt_rand(0,count($this->imageList)-1);
        //echo "random_num is " . $random_num;

//FIXME: Help! This is where it all craps out.
trigger_error("The random image class doesn't quite work yet. Help!", E_USER_NOTICE);

        $imgname = $this->imageList[$random_num];

        return "$dirname/" . $imgname;
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
    function readAvailableImages($dirname) {
        @ $handle = opendir($dir = $dirname);
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
            //echo $fn."<br>\n"; //debug
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