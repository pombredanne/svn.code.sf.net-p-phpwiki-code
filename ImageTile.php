<?php

if (!file_exists($_REQUEST['url'])) {
    header ("Content-type: text/html");
    echo "<html><head></head><body>Not an image</body></html>";
    exit();
}

list ($a, $b, $type, $attr) = @getimagesize ($_REQUEST['url']);

if ($type == 0) {

    $type = basename ($_REQUEST['url']);
    $type = preg_split ('/\./',$type);
    $type = array_pop ($type);

}


switch ($type) {
    case '2':
        if (function_exists("imagecreatefromjpeg"))
            $img = @imagecreatefromjpeg ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    case '3':
        if (function_exists("imagecreatefrompng"))
            $img = @imagecreatefrompng ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    case '1':
        if (function_exists("imagecreatefromgif"))
            $img = @imagecreatefromgif ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    case '15':
        if (function_exists("imagecreatefromwbmp"))
            $img = @imagecreatefromwbmp ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    case '16':
        if (function_exists("imagecreatefromxbm"))
            $img = @imagecreatefromxbm ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    case 'xpm':
        if (function_exists("imagecreatefromxpm"))
            $img = @imagecreatefromxpm ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    case 'gd':
        if (function_exists("imagecreatefromgd"))
            $img = @imagecreatefromgd ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    case 'gd2':
        if (function_exists("imagecreatefromgd2"))
            $img = @imagecreatefromgd2 ($_REQUEST['url']);
        else
            show_plain ($_REQUEST['url']);
        break;
    default:
        //we are not stupid...
        header ("Content-type: text/html");
        echo "<html><head></head><body>Not an image</body></html>";
        exit;
        break;
}    

$width  = @imagesx($img);
$height = @imagesy($img);

$newwidth = $_REQUEST['width'];
if (empty($newidth)) $newidth = 50;
    
$newheight = $_REQUEST['height'];
if (empty($newheight)) $newheight = round($newwidth * ($height / $width)) ;

$thumb = imagecreate($newwidth, $newheight);
$img = imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);


header ("Content-type: image/png");
imagepng($thumb);

function show_plain () {
    $mime = mime_content_type ($_REQUEST['url']);
    header ("Content-type: $mime");
    readfile($_REQUEST['url']);
    exit ();
}

?>
