<?php // -*-php-*-

define('text2png_debug', true);


class WikiPlugin_text2png
extends WikiPlugin
{
    function getName () {
        return "text2png";
    }
	
	function getDefaultArguments() {
        global $LANG;
        return array('text'	=> "Hello WikiWorld!",
                     'l'    => $LANG );
	}

	function run($dbi, $argstr, $request) {
		extract($this->getArgs($argstr, $request));
		return $this->text2png($text,$l);
	}

	function text2png($text,$l) {

        //basic image creation and caching
		//you MUST delete the image cache yourself if you change the drawing routines!
		
        //uncomment debug string above to see whether image was saved to or loaded from cache
		//and what the path is.
		
        //locale test
		//http://download.php.net/manual/en/function.dcgettext.php
		//dcgettext and dgettext aren't available functions on my system.?? -carsten
		//this doesn't seem to work anyway, always get english. ??
        //$oldlang=$LANG;
		//putenv("LANG=$l");
        //$LANG=$l;
        //if (!$l == "C") {include("locale/$l/LC_MESSAGES/phpwiki.php");}
		//$text = gettext($text);
        //putenv("LANG=$oldlang");
		
		$filename = $text . ".png";
		
        if ($l == "C") { $l = "en"; } //FIXME: hack for english, C=en ??
		$filepath = getcwd() . "/images/$l";
 
        if (!file_exists($filepath ."/". $filename)) {
            
            if (!file_exists($filepath)) {
			    $oldumask = umask(0);
                mkdir($filepath, 0777);    //permissions affected by user the www server is running as
                umask($oldumask);
            }
            
			// add trailing slash to save some keystrokes later
            $filepath .= "/";
            
			// prepare a new image
            $im = @ImageCreate(150, 50) or die ("Cannot Initialize new GD image stream. PHP must be compiled with support for GD 1.6 or later to create png files.");

            // get ready to draw
		    $bg_color   = ImageColorAllocate($im, 255, 255, 255);
		    $ttfont     = "/System/Library/Frameworks/JavaVM.framework/Versions/1.3.1/Home/lib/fonts/LucidaSansRegular.ttf";

            // http://download.php.net/manual/en/function.imagettftext.php
			// array imagettftext (int im, int size, int angle, int x, int y, int col, string fontfile, string text)

            //draw shadow
		    $text_color = ImageColorAllocate($im, 175, 175, 175);
            //shadow is 1 pixel down and 2 pixels right
		    ImageTTFText($im, 10, 0, 12, 31, $text_color, $ttfont, $text);
            //draw text
		    $text_color = ImageColorAllocate($im, 0, 0, 0);
		    ImageTTFText($im, 10, 0, 10, 30, $text_color, $ttfont, $text);

            //maybe an alternate text drawing method in case ImageTTFText doesn't work
		    //ImageString($im, 2, 10, 40, $text, $text_color);

		    // to dump directly to browser:
		    //header("Content-type: image/png");
		    //ImagePng($im);

		    // to save to file:
            $success = ImagePng($im, $filepath . $filename);

        } else {
            $filepath .= "/";
            $success = 2;
        }

        // create an <img src= tag to show the image!
        // this could use some better error reporting
		$html = "";
		if ($success > 0) {
            if (defined('text2png_debug')) {
                   switch($success) { 
                   case 1:
                        $html .= Element('p', "Image saved to cache file: " . $filepath . $filename) . "\n" ;
                   case 2:
                        $html .= Element('p', "Image loaded from cache file: " . $filepath . $filename) . "\n" ;
                     }
            }
            $urlpath = DATA_PATH . "/images/$l/";
            $html .= Element('img', array('src' => $urlpath . $filename, 'alt' => $text));
		} else {
			$html .= Element('p', "Error writing png file: " . $filepath . $filename) . "\n";
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
