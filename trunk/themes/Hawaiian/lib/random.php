<?php rcs_id('$Id: random.php,v 1.8 2002-01-26 06:58:27 carstenklapp Exp $');
/**
 */
class randomImage {
    /**
     * Usage:
     *
     * $imgSet = new randomImage($Theme->file("images/pictures"));
     * $imgFile = "pictures/" . $imgSet->filename;
     */
    function randomImage ($dirname) {

        $this->filename = ""; // Pick up your filename here.

        $_imageSet  = new imageSet($dirname);
        $this->imageList = $_imageSet->getFiles();
        unset($_imageSet);

        if (empty($this->imageList)) {
            trigger_error(sprintf(_("%s is empty."), $dirname),
                          E_USER_NOTICE);
        } else {
            $dummy = $this->pickRandom();
        }
    }

    function pickRandom() {
        better_srand(); // Start with a good seed.
        $this->filename = $this->imageList[array_rand($this->imageList)];
        //trigger_error(sprintf(_("random image chosen: %s"),
        //                      $this->filename),
        //              E_USER_NOTICE); //debugging
        return $this->filename;
    }
};


/**
 * Seed the random number generator.
 *
 * better_srand() ensures the randomizer is seeded only once.
 * 
 * How random do you want it? See:
 * http://www.php.net/manual/en/function.srand.php
 * http://www.php.net/manual/en/function.mt-srand.php
 */
function better_srand($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled) {
        $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
        srand($seed);
        $wascalled = TRUE;
        //trigger_error("new random seed", E_USER_NOTICE); //debugging
    }
}


class imageSet extends fileSet {
    /**
     * A file is considered an image when the suffix matches one from
     * $InlineImages.
     */
    function _filenameSelector($filename) {
        global $InlineImages;
        return preg_match("/($InlineImages)$/i", $filename);
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