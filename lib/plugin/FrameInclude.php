<?php // -*-php-*-
rcs_id('$Id: FrameInclude.php,v 1.7 2003-02-26 22:27:19 dairiki Exp $');
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
 *  <?plugin FrameInclude src=http://www.internet-technology.de/fourwins_de.htm ?>
 *  <?plugin FrameInclude page=OtherPage ?>
 *  at the VERY BEGINNING in the content!
 *
 * Author:  Reini Urban <rurban@x-ray.at>, rewrite by Jeff Dairiki <dairiki@dairiki.org>
 *
 * KNOWN ISSUES:
 *
 * This is a dirty hack into the whole system. To display the page as
 * frameset we:
 *
 *  1. Discard any output buffered so far.
 *  2. Recursively call displayPage with magic arguments to generate
 *     the frameset (or individual frame contents.)
 *  3. Exit early.  (So this plugin is usually a no-return.)
 *
 *  In any cases we can now serve only specific templates with the new
 *  frame argument. The whole page is now ?frame=html (before it was
 *  named "top") For the Sidebar theme (or derived from it) we provide
 *  a left frame also, otherwise only top, content and bottom.
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
                            "\$Revision: 1.7 $");
    }

    function getDefaultArguments() {
        return array( 'src'         => false,       // the src url to include
                      'page'        => false,
                      'name'        => 'content',   // name of our frame
                      'title'       => false,
                      'rows'        => '18%,*,15%', // names: top, $name, bottom
                      'cols'        => '20%,*',     // names: left, $name
                                                    // only useful on Theme "Sidebar"
                      'frameborder' => 1,
                      'marginwidth'  => false,
                      'marginheight' => false,
                      'noresize'    => false,
                      'scrolling'   => 'auto',  // '[ yes | no | auto ]'
                    );
    }

    function run($dbi, $argstr, &$request) {
        global $Theme;

        $args = ($this->getArgs($argstr, $request));
        extract($args);

        if ($request->getArg('action') != 'browse')
            return $this->disabled("(action != 'browse')");
        if (! $request->isGetOrHead())
            return $this->disabled("(method != 'GET')");
        
        if (!$src and $page) {
            if ($page == $request->get('pagename')) {
                return $this->error(sprintf(_("recursive inclusion of page %s"),
                                            $page));
            }
            $src = WikiURL($page);
        }
        if (!$src) {
            return $this->error(sprintf(_("%s or %s parameter missing"),
                                        'src', 'page'));
        }

        // FIXME: How to normalize url's to compare against recursion?
        if ($src == $request->getURLtoSelf() ) {
            return $this->error(sprintf(_("recursive inclusion of url %s"),
                                        $src));
        }

        static $noframes = false;
        if ($noframes) {
            // Content for noframes version of page.
            return HTML::p(fmt("See %s",
                               HTML::a(array('href' => $src), $src)));
        }
        $noframes = true;

        if (($which = $request->getArg('frame'))) {
            $request->discardOutput();
            displayPage($request, new Template("frame-$which", $request));
            $request->finish(); //noreturn
        }
        
        $frame = HTML::frame(array('name' => $name,
                                   'src' => $src,
                                   'title' => $title,
                                   'frameborder' => (int)$frameborder,
                                   'scrolling' => (string)$scrolling,
                                   'noresize' => (bool)$noresize,
                                   ));
        
        if ($marginwidth)
            $frame->setArg('marginwidth', $marginwidth);
        if ($marginheight)
            $frame->setArg('marginheight', $marginheight);
        
        $tokens = array('CONTENT_FRAME' => $frame,
                        'ROWS' => $rows,
                        'COLS' => $cols,
                        'FRAMEARGS' => sprintf('frameborder="%d"', $frameborder),
                        );

        // Produce the frameset.
        $request->discardOutput();
        displayPage($request, new Template('frameset', $request, $tokens));
        $request->finish(); //noreturn

        
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

        $tokens['FRAMESET'] = $frameset;

        $request->discardOutput();
        return printXML(new Template('frameset', $request, $tokens));
        $request->finish();
    }

    function _generateFrame(&$request, $content) {
        $request->discardOutput();
        $head = new Template('head', $request);
        printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", CHARSET);
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
              "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
              <html xmlns="http://www.w3.org/1999/xhtml">';
        echo "</html>\n";
        $head->printExpansion();
        echo "<body>\n";
        printXML($content);
        echo "</body>\n";
        $request->finish();
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.6  2003/01/18 21:41:01  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
