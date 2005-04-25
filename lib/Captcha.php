<?php
/*
  Session Captcha v1.0
  by Gavin M. Roy <gmr@bteg.net>
  Modified by Benjamin Drieu <bdrieu@april.org> - 2005 for PhpWiki

  This File is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
  
  This File is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
  General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with This File; if not, write to the Free Software Foundation,
  Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


function get_captcha_word () {
    // Load In the Word List
    $fp = fopen(FindFile("lib/captcha/dictionary"), "r");
    while ( !feof($fp) )
	$text[] = Trim(fgets($fp, 1024));
    fclose($fp);

    // Pick a Word
    $word = "";
    while ( strlen(Trim($word)) == 0 ) {
	$x = rand(0, Count($text));
	return $text[$x];
    }
}

// Draw the Spiral
function spiral( &$im, $origin_x = 100, $origin_y = 100, $r = 0, $g = 0, $b = 0 ) {
    $theta = 1;
    $thetac = 6;  
    $radius = 15;  
    $circles = 10;  
    $points = 35;  
    $lcolor = imagecolorallocate( $im, $r, $g, $b );
    for( $i = 0; $i < ( $circles * $points ) - 1; $i++ ) {
	$theta = $theta + $thetac;
	$rad = $radius * ( $i / $points );
	$x = ( $rad * cos( $theta ) ) + $origin_x;
	$y = ( $rad * sin( $theta ) ) + $origin_y;
	$theta = $theta + $thetac;
	$rad1 = $radius * ( ( $i + 1 ) / $points );
	$x1 = ( $rad1 * cos( $theta ) ) + $origin_x;
	$y1 = ( $rad1 * sin( $theta ) ) + $origin_y;
	imageline( $im, $x, $y, $x1, $y1, $lcolor );
	$theta = $theta - $thetac;
    }
}

function captcha_image ( $word ) {
    $width = 250;
    $height = 80;
    
    // Create the Image
    $jpg = ImageCreate($width,$height);
    $bg = ImageColorAllocate($jpg,255,255,255);
    $tx = ImageColorAllocate($jpg,185,140,140);
    ImageFilledRectangle($jpg,0,0,$width,$height,$bg);

    $x = rand(0, $width);
    $y = rand(0, $height);
    spiral($jpg, $x, $y, 225, 190, 190);

    $angle = rand(-25, 25);
    $size = rand(14,20);
    if ( $angle >= 0 )
	$y = rand(50,$height-20);
    else 
	$y = rand(25, 50);
    $x = rand(10, $width-100);

    imagettftext($jpg, $size, $angle, $x, $y, $tx, FindFile("lib/captcha/Vera.ttf"), $word);

    $x = rand(0, 280);
    $y = rand(0, 115);
    spiral($jpg, $x, $y, 255,190,190);

    imageline($jpg, 0,0,$width-1,0,$tx);
    imageline($jpg, 0,0,0,$height-1,$tx);
    imageline($jpg, 0,$height-1,$width-1,$height-1,$tx);
    imageline($jpg, $width-1,0,$width-1,$height-1,$tx);

    header("Content-type: image/jpeg");
    ImageJpeg($jpg);
}
?>