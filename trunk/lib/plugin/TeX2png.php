<?php // -*-php-*-
rcs_id('$Id: TeX2png.php,v 1.1 2004-06-13 09:35:51 rurban Exp $');
/*
 Copyright 2004 Pierrick Meignen
*/

/**
 * This is a simple version of the original TexToPng plugin which uses 
 * the powerful plugincached mechanism.
 * TeX2png uses its own much simplier static cache in images/tex.
 *
 * @author: Pierrick Meignen
 * TODO: use url helpers, windows fixes
 *       use a better imagepath
 */

// needs latex
// LaTeX2HTML ftp://ftp.dante.de/tex-archive/support/latex2html

class WikiPlugin_TeX2png
extends WikiPlugin
{
    var $imagepath = "images/tex";
    var $latexbin = '/usr/bin/latex';
    var $dvipsbin = '/usr/bin/dvips';
    var $pstoimgbin = '/usr/bin/pstoimg';

    function getName () {
        return _("TeX2png");
    }
    
    function getDescription () {
        return _("Convert Tex mathematicals expressions to cached png files." .
		 " This is for small text");
    }
    
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',"\$Revision: 1.1 $");
    }
    
    function getDefaultArguments() {
        return array('text' => "$$(a + b)^2 = a^2 + 2 ab + b^2$$");
    }
    
    function parseArgStr($argstr) {
        // modified from WikiPlugin.php
        $arg_p = '\w+';
        $op_p = '(?:\|\|)?=';
        $word_p = '\S+';
        $opt_ws = '\s*';
	$qq_p = '" ( (?:[^"\\\\]|\\\\.)* ) "';
	//"<--kludge for brain-dead syntax coloring
	$q_p  = "' ( (?:[^'\\\\]|\\\\.)* ) '";
	$gt_p = "_\\( $opt_ws $qq_p $opt_ws \\)";
	$argspec_p = "($arg_p) $opt_ws ($op_p) $opt_ws (?: $qq_p|$q_p|$gt_p|($word_p))";
    
	$args = array();
	$defaults = array();
    
	while (preg_match("/^$opt_ws $argspec_p $opt_ws/x", $argstr, $m)) {
	    @ list(,$arg,$op,$qq_val,$q_val,$gt_val,$word_val) = $m;
	    $argstr = substr($argstr, strlen($m[0]));
      
	    // Remove quotes from string values.
	    if ($qq_val)
	        // we don't remove backslashes in tex formulas
	        // $val = stripslashes($qq_val);
	        $val = $qq_val;
	    elseif ($q_val)
	        $val = stripslashes($q_val);
	    elseif ($gt_val)
	        $val = _(stripslashes($gt_val));
	    else
	        $val = $word_val;
	    
	    if ($op == '=') {
	        $args[$arg] = $val;
	    }
	    else {
	        // NOTE: This does work for multiple args. Use the
	        // separator character defined in your webserver
	        // configuration, usually & or &amp; (See
	        // http://www.htmlhelp.com/faq/cgifaq.4.html)
	        // e.g. <plugin RecentChanges days||=1 show_all||=0 show_minor||=0>
	        // url: RecentChanges?days=1&show_all=1&show_minor=0
	        assert($op == '||=');
		$defaults[$arg] = $val;
	    }
	}
    
	if ($argstr) {
	    $this->handle_plugin_args_cruft($argstr, $args);
	}
    
	return array($args, $defaults);
    }

    function createTexFile($texfile, $text) {
         $fp = fopen($texfile, 'w');
	 $str = "\documentclass{article}\n";
	 $str .= "\usepackage{amsfonts}\n";
	 $str .= "\usepackage{amssymb}\n";
	 $str .= "\pagestyle{empty}\n";
	 $str .= "\begin{document}\n";
	 $str .= $text . "\n";
	 $str .= "\end{document}";
	 fwrite($fp, $str);
	 fclose($fp);
	 return 0;
    }

    function createPngFile($imagepath, $imagename) {
	 $commandes = "$latexbin temp.tex; $dvipsbin temp.dvi -o temp.ps;";
	 $commandes .= "$pstoimgbin -type png -margins 0,0 -crop a -geometry 600x300";
	 $commandes .= " -aaliastext -color 1";
	 $options = " -scale 1.5 ";
	 $commandes .= $commandes . $options . "temp.ps -o " . $imagename;
	 exec("cd $imagepath; $commandes");
	 unlink("$imagepath/temp.dvi");
	 unlink("$imagepath/temp.tex");
	 unlink("$imagepath/temp.aux");
	 unlink("$imagepath/temp.log");
	 unlink("$imagepath/temp.ps");
	 return 0;
    }

    function isMathExp($text) {
      $last = strlen($text) - 1;
      if($text[0] != "$" || $text[$last] != "$")
	return 0;
      else if ($text[1] == "$" && $text[$last - 1] == "$")
	return 2;
      return 1;
    }

    function tex2png($text) {
	if($this->isMathExp($text) == 0){	
            $error_html =_("Sorry, not a full mathematical expression: " . $text);
            trigger_error($error_html, E_USER_NOTICE);
	} else {
	    if (!file_exists($this->imagepath)) {
	        $oldumask = umask(0);
		// permissions affected by user the www server is running as
		mkdir($this->imagepath, 0777);
		umask($oldumask);
	    }
	    if (!file_exists($this->imagepath)) {
	    	trigger_error(sprintf("Failed to mkdir '%s'.", $this->imagepath), E_USER_WARNING);
	        return '';
	    }

	    $imagename = md5($text) . ".png";
	    $url = $this->imagepath . "/" . $imagename;
    
	    if(!file_exists($url)){
	        $texfile = $this->imagepath . "/temp.tex";
		$this->createTexFile($texfile, $text);     
		$this->createPngFile($this->imagepath, $imagename);
	    } 
	}

	switch($this->isMathExp($text)) {
        case 0: 
            $html = HTML::tt(array('class'=>'tex', 'style'=>'color:red;'),
                             $text); 
            break;
        case 1: 
            $html = HTML::img(array('class'=>'tex', 'src' => $url, 'alt' => $text)); 
            break;
        case 2: 
            $html = HTML::img(array('class'=>'tex', 'src' => $url, 'alt' => $text));
            $html = HTML::div(array('align' => 'center'), $html); 
            break;
        default: 
            break;
        }

	return $html;
    }

    function run($dbi, $argstr, &$request, $basepage) {
        // from text2png.php
        if (ImageTypes() & IMG_PNG) {
            // we have gd & png so go ahead.
            extract($this->getArgs($argstr, $request));
	    return $this->tex2png($text);
        } else {
            // we don't have png and/or gd.
            $error_html = _("Sorry, this version of PHP cannot create PNG image files.");
            $link = "http://www.php.net/manual/pl/ref.image.php";
            $error_html .= sprintf(_("See %s"), $link) .".";
            trigger_error($error_html, E_USER_NOTICE);
            return;
        }
    }
};
?>
