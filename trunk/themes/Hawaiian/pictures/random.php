<?php

rcs_id('$Id: random.php,v 1.2 2002-01-02 00:14:51 carstenklapp Exp $');

// mt_srand ((double) microtime() * 1000000 / pi()); #not random enough
// Hmm is this random enough?
// see http://download.php.net/manual/en/function.srand.php


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
    "WhaleRainbow.jpg",
);

if isset($RandomPictures) {
    function my_srand($seed = '') {
        static $wascalled = FALSE;
            if (!$wascalled) {
                $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
                srand($seed);
                $wascalled = TRUE;
            }
    }

    my_srand();
    $SignatureImg = "themes/$theme/pictures/".$pictures[mt_rand(0,count($RandomPictures)-1)];
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
