<?php rcs_id('$Id: random.php,v 1.5 2002-01-23 11:32:18 carstenklapp Exp $');
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

        $this->filename ="";

        $imageSet  = new imageSet($dirname);
        $imageList = $imageSet->getFiles();
        if (empty($imageList)) {
            trigger_error(sprintf(_("%s is empty."), $dirname),
                          E_USER_NOTICE);
            return; // early return
        }

        //FIXME:
        //srand(seed()); // Start with a good seed.

        if ($imageList) {
            $this->filename = $imageList[array_rand($imageList)];
            //trigger_error(sprintf(_("random image chosen: %s"), $imgname),
            //              E_USER_NOTICE);//debugging
        }
    }
};


/**
 * Prepare a random seed.
 * 
 * How random do you want it? See
 * http://download.php.net/manual/en/function.srand.php
 * mt_srand ((double) microtime() * 1000000 / pi())
 */
function seed($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled) {
        $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
        srand($seed);
        $wascalled = TRUE;
        //trigger_error("new random seed", E_USER_NOTICE);//debugging
    }
}


class imageSet extends fileSet {
    /**
     * Files are considered images when it's suffix matches one from $InlineImages.
     */
    function _filenameSelector($filename) {
        global $InlineImages;
        return preg_match("/($InlineImages)$/i", $filename);
    }
};

class fileSet {
    /**
     * Build an array in $this->_fileList of files from $dirname.
     *
     * (This is a variation of function LoadDir in lib/loadsave.php)
     * See also http://www.php.net/manual/en/function.readdir.php
     */
    function getFiles() {
        return $this->_fileList;
    }

    function _fileSelector($filename) {
        // Default selects all filenames, override as needed.
        return true;
    }

    function fileSet($directory) {
        $this->_fileList = array();

        if (empty($directory)) {
            trigger_error(sprintf(_("%s is empty."), 'dirname'),
                          E_USER_NOTICE);
            return; // early return
        }

        @ $dir_handle = opendir($dir=$directory);
        if (empty($dir_handle)) {
            trigger_error(sprintf(_("Unable to open directory '%s' for reading"),
                                  $dir), E_USER_NOTICE);
            return; // early return
        }

        while ($filename = readdir($dir_handle)) {
            if ($filename[0] == '.' || filetype("$dir/$filename") != 'file')
                continue;
            if ($this->_filenameSelector($filename)) {
                array_push($this->_fileList, "$filename");
            //trigger_error(sprintf(_("found file %s"), $filename), E_USER_NOTICE);//debugging
            }
        }
        closedir($dir_handle);
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