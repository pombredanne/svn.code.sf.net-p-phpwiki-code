<?php // -*-php-*-
rcs_id('$Id: FrameInclude.php,v 1.1 2002-08-23 18:32:12 rurban Exp $');
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
 * FrameInclude:  include text from another wiki page in this one
 * usage:   <?plugin FrameInclude src=http://www.internet-technology.de/fourwins_de.htm ?>
 * author:  Reini Urban <rurban@x-ray.at>
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
        return array( 'src'         => false, // the src url to include
                      'name'        => '',
                      'rows'        => '10%,*,10%',
                      'cols'        => '10%,*',  // only used on Theme "Sidebar"
                      'frameborder' => 0,
                      'marginwidth'  => false,
                      'marginheight' => false,
                      'noresize'    => false,
                      'scrolling'   => 'auto',  // '[ yes | no | auto ]'
                    );
    }

    function run($dbi, $argstr, $request) {
    	global $Theme;

        extract($this->getArgs($argstr, $request));

        if (!$src)
            return $this->error(sprintf(_("%s parameter missing"), 'src'));
        // FIXME: unmunged url
        $src = preg_replace('/src=(.*)\Z/','$1',$argstr);

        // how to normalize url's?
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
        if ($Theme == 'Sidebar') {
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
        // 1) either change the whole output stream to 
        //    head, $frameset, <nobody>body</nobody>
        // 2) or redirect to ?frameset=pagename
        //    $request->setArg('framesrc', $src);
        //    $request->redirect('frameset', $request->getName());
        return $frameset; 
    }
};

// This is an excerpt from the css file I use:
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

// KNOWN ISSUES:

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
