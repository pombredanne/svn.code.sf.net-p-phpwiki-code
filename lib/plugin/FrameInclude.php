<?php // -*-php-*-
rcs_id('$Id: FrameInclude.php,v 1.3 2002-08-27 21:51:31 rurban Exp $');
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * FrameInclude:  Displays a url in a seperate frame inside our body.
 * Usage:   <?plugin FrameInclude src=http://www.internet-technology.de/fourwins_de.htm ?>
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * KNOWN ISSUES:
 *  This is a dirty hack into the whole system. To display the page as frameset
 *  we must know in advance about the plugin existence.
 *  1. We can buffer the output stream (which in certain cases is not doable).
 *  2. Check the page content for the start string '<?plugin FrameInclude'
 *     which we currently do.
 *  3. Redirect to a new page with the frameset only. ?frameset=pagename
 *      $request->setArg('framesrc', $src);
 *      $request->redirect('frameset', $request->getName());
 *  In any cases we can now serve only specific templates with the new frame 
 *  argument. The whole page is now ?frame=html (before it was named "top")
 *  For the Sidebar theme we provide a left frame also, otherwise 
 *  only top, content and bottom.
 *
 *  This plugin doesn't return a typical html stream inside a <body>, only a 
 *  <frameset> which has to go before <body>, right after <head>.
 *
 */

class WikiPlugin_FrameInclude
extends WikiPlugin
{
    function getName() {
        return _("FrameInclude");
    }

    function getDescription() {
        return _("Displays a url in a seperate frame inside our body. Only one frame allowed.");
    }

    function getDefaultArguments() {
        return array( 'src'         => false,       // the src url to include
                      'name'        => 'content',   // name of our frame
                      'title'       => false,
                      'rows'        => '10%,*,10%', // names: top, $name, bottom
                      'cols'        => '10%,*',     // names: left, $name
                                                    // only useful on Theme "Sidebar"
                      'frameborder' => 0,
                      'marginwidth'  => false,
                      'marginheight' => false,
                      'noresize'    => false,
                      'scrolling'   => 'auto',  // '[ yes | no | auto ]'
                    );
    }

    function run($dbi, $argstr, $request) {
    	global $Theme;

        $args = ($this->getArgs($argstr, $request));
        extract($args);

        if (!$src)
            return $this->error(sprintf(_("%s parameter missing"), 'src'));
        // FIXME: unmunged url hack
        $src = preg_replace('/src=(.*)\Z/','$1',$argstr);
        // How to normalize url's to compare against recursion?
        if ($src == $request->getURLtoSelf() ) {
            return $this->error(sprintf(_("recursive inclusion of url %s"), $src));
        }

        // pass FRAMEPARAMS directly to the Template call in Template.php:214
        // which goes right after <HEAD>
        $topuri = $request->getURLtoSelf('frame=top');
        $bottomuri = $request->getURLtoSelf('frame=bottom');
        $top = "<frame name=\"top\" src=\"$topuri\" />";
        $bottom = "<frame name=\"bottom\" src=\"$bottomuri\" />";

        $content = "<frame name=\"$name\" src=\"$src\" frameborder=\"$frameborder\" ";
        if ($marginwidth)  $content .= "marginwidth=\"$marginwidth\" ";
        if ($marginheight) $content .= "marginheight=\"$marginheight\" ";
        if ($noresize) $content .= "noresize=\"noresize\" ";
        $content .= "scrolling=\"$scrolling\" />";

        // include this into top.tmpl instead
        //$memo = HTML(HTML::p(array('class' => 'transclusion-title'),
        //                     fmt("Included frame from %s", $src)));
        if (isa($Theme,'Theme_Sidebar')) {
            // left also"\n".
            $lefturi = $request->getURLtoSelf('frame=navbar');
            $frameset = "\n".
                        "<FRAMESET ROWS=\"$rows\">\n".
                        "  $top\n".
                        "  <FRAMESET COLS=\"$cols\">\n".
                        "    <frame name=\"left\" src=\"$lefturi\" />\n".
                        "    $content\n".
                        "  </FRAMESET>\n".
                        "</FRAMESET>\n";
        } else {
            // only top, body, bottom
            $frameset = "\n".
                        "<FRAMESET ROWS=\"$rows\">\n".
                        "  $top\n".
                        "  $content\n".
                        "</FRAMESET>\n";
        }
        $args['FRAMESET'] = $frameset;
        return printXML(new Template('frameset', $request, $args));
    }
};

// This is an excerpt from the CSS file. (from IncludePage)
//
// .transclusion-title {
//   font-style: oblique;
//   font-size: 0.75em;
//   text-decoration: underline;
//   text-align: right;
// }
//
// DIV.transclusion {
//   background: lightgreen;
//   border: thin;
//   border-style: solid;
//   padding-left: 0.8em;
//   padding-right: 0.8em;
//   padding-top: 0px;
//   padding-bottom: 0px;
//   margin: 0.5ex 0px;
// }

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
