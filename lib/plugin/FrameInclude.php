<?php // -*-php-*-
rcs_id('$Id: FrameInclude.php,v 1.6 2003-01-18 21:41:01 carstenklapp Exp $');
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
 * FrameInclude:  Displays a url or page in a seperate frame inside our body.
 *
 * Usage:
 *  <?plugin-head FrameInclude src=http://www.internet-technology.de/fourwins_de.htm ?>
 *  <?plugin-head FrameInclude page=OtherPage ?>
 *  at the VERY BEGINNING in the content! Otherwise it will be ignored.
 *
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * KNOWN ISSUES:
 *
 * This is a dirty hack into the whole system. To display the page as
 * frameset we must know in advance about the plugin existence.
 *
 *  1. Check the page content for the start string '<?plugin-head '
 *     which we currently do.
 *
 * 2. We can buffer the output stream (which in certain cases is not
      doable).
 *
 *  3. Redirect to a new page with the frameset only. ?frameset=pagename
 *      $request->setArg('framesrc', $src);
 *      $request->redirect('frameset', $request->getName());
 *
 *  In any cases we can now serve only specific templates with the new
 *  frame argument. The whole page is now ?frame=html (before it was
 *  named "top") For the Sidebar theme (or derived from it) we provide
 *  a left frame also, otherwise only top, content and bottom.
 *
 *  This plugin doesn't return a typical html stream inside a <body>,
 *  only a <frameset> which has to go before <body>, right after
 *  <head>.
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

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.6 $");
    }

    function getDefaultArguments() {
        return array( 'src'         => false,       // the src url to include
                      'page'        => false,
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

        if (!$src) {
            if (!$page) {
                return
                    $this->error(sprintf(_("%s or %s parameter missing"),
                                         'src', 'page'));
            } else {
                if ($page == $request->get('pagename')) {
                    return
                        $this->error(sprintf(_("recursive inclusion of page %s"),
                                             $page));
                }
                $src = WikiURL($page);
            }
        }

        // How to normalize url's to compare against recursion?
        if ($src == $request->getURLtoSelf() ) {
            return $this->error(sprintf(_("recursive inclusion of url %s"),
                                        $src));
        }

        // pass FRAMEPARAMS directly to the Template call in Template.php:214
        // which goes right after <HEAD>
        $topuri = $request->getURLtoSelf(array('frame' => 'top'));
        $bottomuri = $request->getURLtoSelf(array('frame' => 'bottom'));
        $top = HTML::frame(array('name' => "top", "src" => $topuri));
        $bottom = HTML::frame(array('name' => "bottom", "src" => $bottomuri));
        //$bottom = "<frame name=\"bottom\" src=\"$bottomuri\" />";

        $content_opts = array('name' => $name, "src" => $src,
                              'frameborder' => $frameborder);
        //        $content = "<frame name=\"$name\" src=\"$src\" frameborder=\"$frameborder\" ";
        if ($marginwidth)
            $content_opts['marginwidth'] = $marginwidth;
        if ($marginheight)
            $content_opts['marginheight'] = $marginheight;
        if ($noresize)
            $content_opts['noresize'] = "noresize";
        $content_opts['scrolling'] = $scrolling;
        $content = HTML::frame($content_opts);

        // include this into top.tmpl instead
        //$memo = HTML(HTML::p(array('class' => 'transclusion-title'),
        //                     fmt("Included frame from %s", $src)));
        if (isa($Theme, 'Theme_Sidebar')) {
            // left also.
            $lefturi = $request->getURLtoSelf(array('frame' => 'navbar'));
            $frameset = HTML::frameset(array('rows' => $rows));
            $frameset->pushContent($top);
            $colframeset = HTML::frameset(array('cols' => $cols));
            $colframeset->pushContent(HTML::frame(array('name' => "left",
                                                        "src" => $lefturi)));
            $colframeset->pushContent($content);
            $frameset->pushContent($colframeset);
            $frameset->pushContent($bottom);
        } else {
            unset($args['cols']);
            // only top, body, bottom
            $frameset = HTML::frameset(array('rows' => $rows));
            $frameset->pushContent($top);
            $frameset->pushContent($content);
            $frameset->pushContent($bottom);
        }
        $args['FRAMESET'] = $frameset;
        return printXML(new Template('frameset', $request, $args));
    }
};

// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
