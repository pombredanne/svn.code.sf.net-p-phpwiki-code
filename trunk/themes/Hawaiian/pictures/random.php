<?php

rcs_id('$Id: random.php,v 1.5 2002-01-05 06:17:02 carstenklapp Exp $');

// FIXME: This whole file could be refactored and turned in a
//        RandomImage plugin.
//
//        Performace issues? $SignatureImg does not appear on
//        every page but index.php is loaded every time:
//        index.php -> themeinfo.php -> random.php
//

/*
$RandomPictures = array(
                        "BeachPalmDusk.jpg",
                        "Coastline.jpg",
                        "HawaiiMauiFromSpace.jpg",
                        "LavaTwilight.jpg",
                        "LoihiSeamount.jpg",
                        "SteamVolcanoDusk.jpg",
                        "SubmersiblePiscesV.jpg",
                        "SwimmingPoolWater.jpg",
                        "Waterfall.jpg",
                        "WhaleRainbow.jpg"
                        );
*/

// Mac users take note:
//
// This function relies on all the image files having filename
// suffixes, even if the web server uses a "MAGIC files" capability
// (to automatically assign a mime-type based on the the first few
// bytes of the file).
//
// This code is a variation of function LoadDir in lib/loadsave.php
// See http://www.php.net/manual/en/function.readdir.php

function imagelist($dirname) {
    if (empty($dirname)) {
        // ignore quietly
        //trigger_error(("dirname is empty"),
        //E_USER_NOTICE); $imagelist = "";
    } else {
        $handle = opendir($dir = $dirname);
        if (empty($handle)) {
            // FIXME: gettext doesn't work in index.php or
            // themeinfo.php
            trigger_error(sprintf(("Unable to open directory '%s' for reading"),
                                  $dir), E_USER_NOTICE);
            //$imagelist = "";
        } else {
            $imagelist = array();
            while ($fn = readdir($handle)) {

                if ($fn[0] == '.' || filetype("$dir/$fn") != 'file')
                    continue;
                // FIXME: Use $InlineImages instead of just ".jpg"
                //        hardcoded.
                if (substr($fn,-4) == ".jpg")
                    array_push($imagelist, "$fn");

            }
            closedir($handle);
        }
    }
    return $imagelist;
}

// FIXME: Will this need to be changed to work on WindowsOS?
$RandomPictures = imagelist( getcwd() ."/" ."themes/$theme/pictures/" );

if (!empty($RandomPictures)) {

    // mt_srand ((double) microtime() * 1000000 / pi())
    //
    // Not random enough... Hmm is this random enough? See
    // http://download.php.net/manual/en/function.srand.php

    function my_srand($seed = '') {
        static $wascalled = FALSE;
            if (!$wascalled) {
                $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
                srand($seed);
                $wascalled = TRUE;
            }
    }

    my_srand();

    // For testing the randomization out, just use $logo instead of
    // #Signature

    // $logo = "themes/$theme/pictures/"
    $SignatureImg = "themes/$theme/pictures/"
        .$RandomPictures[mt_rand(0,count($RandomPictures)-1)];
}

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
