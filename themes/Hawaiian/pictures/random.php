<?php

rcs_id('$Id: random.php,v 1.1 2002-01-01 06:24:50 carstenklapp Exp $');

// mt_srand ((double) microtime() * 1000000 / pi()); #not random enough
// Hmm is this random enough?
// see http://download.php.net/manual/en/function.srand.php


$pictures = array(
    "SwimmingPoolWater.jpg",
    "LoihiSeamount.jpg",
    "Coastline.jpg",
    "LavaTwilight.jpg",
    "SubmersiblePiscesV.jpg",
    "HawaiiMauiFromSpace.jpg",
    "BeachPalmDusk.jpg",
    "WhaleRainbow.jpg",
    "SteamVolcanoDusk.jpg",
    "Waterfall.jpg"
);
function my_srand($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled){
        $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
        srand($seed);
        $wascalled = TRUE;
    }
}
my_srand();
$SignatureImg = "themes/$theme/pictures/".$pictures[mt_rand(0,9)];

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
