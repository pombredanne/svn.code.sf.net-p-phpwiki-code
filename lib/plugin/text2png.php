<?php // -*-php-*-

/**
 * File loading and saving diagnostic messages, to see whether an
 * image was saved to or loaded from the cache and what the path is
 */
define('text2png_debug', true);


class WikiPlugin_text2png
extends WikiPlugin
{
    function getName () {
        return "text2png";
    }
        
        function getDefaultArguments() {
        global $LANG;
        return array('text' => "Hello WikiWorld!",
                     'l'    => $LANG );
        }

        function run($dbi, $argstr, $request) {
                extract($this->getArgs($argstr, $request));
                return $this->text2png($text,$l);
        }

        function text2png($text,$l) {

     /**
      * Basic image creation and caching
      * You MUST delete the image cache yourself in /images if you change the
      * drawing routines!
      */

        $filename = $text . ".png";

     /* FIXME: need something more elegant, and a way to gettext a different
      * language depending on any individual user's locale preferences.
      */
        if ($l == "C") { $l = "en"; } //english=C
                $filepath = getcwd() . "/images/$l";
 
        if (!file_exists($filepath ."/". $filename)) {
            
            if (!file_exists($filepath)) {
                            $oldumask = umask(0);
             // permissions affected by user the www server is running as
                mkdir($filepath, 0777);
                umask($oldumask);
            }
            
         // add trailing slash to save some keystrokes later
            $filepath .= "/";
            
         /* prepare a new image
          * FIXME: needs a dynamic image size depending on text width and height
          * $im = @ImageCreate(150, 50);
          */
            if (empty($im)) {
                $error_text = _("Unable to create a new GD image stream. PHP must be compiled with support for the GD library version 1.6 or later to create PNG image files.");
             // FIXME: Error manager does not transform URLs passed through it.
                $error_text .= QElement('a', array('href' => "http://www.php.net/manual/en/function.imagecreate.php",
                                        'class' => 'rawurl'), "PHP web page");
                trigger_error( $error_text, E_USER_NOTICE );
            }
         // get ready to draw
            $bg_color = ImageColorAllocate($im, 255, 255, 255);
            $ttfont   = "/System/Library/Frameworks/JavaVM.framework/Versions/1.3.1/Home/lib/fonts/LucidaSansRegular.ttf";

         /* http://download.php.net/manual/en/function.imagettftext.php
          * array imagettftext (int im, int size, int angle, int x, int y, int col, 
          *                     string fontfile, string text)
          */

         // draw shadow
            $text_color = ImageColorAllocate($im, 175, 175, 175);
         // shadow is 1 pixel down and 2 pixels right
            ImageTTFText($im, 10, 0, 12, 31, $text_color, $ttfont, $text);

         // draw text
            $text_color = ImageColorAllocate($im, 0, 0, 0);
            ImageTTFText($im, 10, 0, 10, 30, $text_color, $ttfont, $text);

         /* An alternate text drawing method in case ImageTTFText
          * doesn't work.
          */
            #ImageString($im, 2, 10, 40, $text, $text_color);

         // To dump directly to browser:
            #header("Content-type: image/png");
            #ImagePng($im);

         // to save to file:
            $success = ImagePng($im, $filepath . $filename);

        } else {
            $filepath .= "/";
            $success = 2;
        }

     // create an <img src= tag to show the image!
        $html = "";
        if ($success > 0) {
            if (defined('text2png_debug')) {
                switch($success) { 
                case 1:
                    trigger_error(sprintf(_("Image saved to cache file: %s"),
                                        $filepath . $filename), E_USER_NOTICE);
                case 2:
                    trigger_error(sprintf(_("Image loaded from cache file: %s"),
                                        $filepath . $filename), E_USER_NOTICE);
                }
            }
            $urlpath = DATA_PATH . "/images/$l/";
            $html .= Element('img', array('src' => $urlpath . $filename, 'alt' => $text));
        } else {
            trigger_error(sprintf(_("couldn't open file '%s' for writing"),
                                        $filepath . $filename), E_USER_NOTICE);
        }
    return $html;
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 4
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>