<?php // -*-php-*-

class WikiPlugin_text2png
extends WikiPlugin
{
    var $name = 'text2png';
    function getDefaultArguments() {
        return array('text'    => 'Hello WikiWorld!');
    }
   function run($args) {
        //FIXME: are quotes needed for the argument string text= or no?
        //FIXME: the next two lines aren't the correct way to extract a text argument for a WikiPlugin
        $args = &$this->args;
        $t = $args['text'];
        return $this->text2png($t);
        
//        return sprintf("<tt>%s %s</tt>", $salutation, $name);
    }

    function text2png($text) {
        //FIXME: once this accepts a text argument the next line should be removed
        $text="Hello WikiWorld!";
        
        $text or die ("?text string required");
        $im = @ImageCreate(150, 75) or die ("Cannot Initialize new GD image stream. PHP must be compiled with support for GD 1.6 or later to create png files.");

        $bg_color   = ImageColorAllocate($im, 255, 255, 255);
        $text_color = ImageColorAllocate($im, 50, 50, 200);
        $ttfont     = "/System/Library/Frameworks/JavaVM.framework/Versions/1.3.1/Home/lib/fonts/LucidaSansRegular.ttf";

        ImageTTFText($im, 10, 0, 10, 30, $text_color, $ttfont, $text);
        ImageString($im, 2, 10, 40, $text, $text_color);

        // dump directly to browser:
        //        header("Content-type: image/png");
        //        ImagePng($im);

        // save to file:
        $filename = $text . ".png";
        $success = ImagePng($im, "../" . $filename);

        //FIXME: the link generated doesn't work. The image file is dumped in the same directory as index.php
        if($success = 1) {
            $s = "<p>png image saved as <a href=\"/$filename\">$filename</a>.</p>";
         } else {
            $s = "<p>Error creating png file.</p>";
        }  
        return $s;
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
