<?php // -*-php-*-
rcs_id('$Id: ViewSource.php,v 1.2 2002-01-12 02:58:34 carstenklapp Exp $');
require_once('lib/Template.php');
/**
 * A handy plugin for viewing the Wiki markup code of locked (and
 * unlocked) pages.
 *
 * Comments/Discussion:
 *
 *  In the long run, it may be cleaner to include this functionality
 *  in with the EditPage code.  (i.e. if you try to edit locked pages,
 *  you get a slightly modified version of the normal page editing
 *  form.  Mostly: no save button.)  --JeffDairiki
 *  Agreed. --CarstenKlapp
 */
class WikiPlugin_ViewSource
extends WikiPlugin
{
    function getName () {
        return _("ViewSource");
    }

    function getDescription () {
        return sprintf(_("View Wiki code for page '%s'."), '[pagename]');
    }
    
    function WikiPlugin_ViewSource() {
    }

    function getDefaultArguments() {
        return array('page' => false,
                     'rev'  => false);
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (empty($args['page']))
            return '';

        $page = $dbi->getPage($args['page']);
        if (empty($args['rev'])) {
            $rev = $page->getCurrentRevision();
            $link = QElement('a',
                             array('href' => WikiURL($args['page'])),
                             $args['page']);
        }
        else {
            $rev = $page->getRevision($args['rev']);

            if (!$rev) {
                return QElement('p', array('class' => 'error'),
                                __sprintf("I'm sorry.  Version %d of %s is not in my database.",
                                          $args['rev'], $args['page']));
            }
            $link = QElement('a',
                             array('href' =>
                                   WikiURL($args['page'],
                                           array('version' => $args['rev']))),
                             __sprintf("version %d of %s",
                                       $args['rev'], $args['page']));
        }

        $html = Element('h2', __sprintf("Page source for %s", $link));

        // <tt> seems to be a good compromise in IE and OmniWeb it
        // doesn't combine newlines and <br />, and renders monospaced
        $html .= Element('tt',
                         nl2br(htmlspecialchars($rev->getPackedContent())));

        /**
         * Display page source in a <textarea>:
         *  o Same appearance as when editing page.
         *  o Easiest to cut and paste from.
         */
        global $user;
        $prefs = $user->getPreferences();
        $html .= Element('p',
                         QElement('textarea',
                                  array('class'  => 'wikiedit',
                                        'rows'   => $prefs['edit_area.height'],
                                        'cols'   => $prefs['edit_area.width'],
                                        'wrap'   => 'virtual',
                                        'readonly' => true),
                                  $rev->getPackedContent()));

        return $html;
    }
};

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
