<?php rcs_id('$Id: ButtonFactory.php,v 1.4 2002-01-17 20:35:44 dairiki Exp $');

/**
 * A class representing a clickable "button".
 *
 * In it's simplest (default) form, a "button" is just a link associated
 * with some sort of wiki-action.
 */
class Button {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $url string The url (href) for the button.
     * @param $class string The CSS class for the button.
     */
    function Button ($text, $url, $class) {
        $this->_attr = array('href' => $url,
                             'class' => $class);
        $this->_text = $text;
    }

    /** Add a "tooltip" to a button.
     *
     * @param $tooltip_text string The tooltip text.
     */
    function addTooltip ($tooltip_text) {
        $attr = &$this->_attr;
        $attr['title'] = $tooltip_text;

        // FIXME: this should be initialized from title by an onLoad() function.
        //        (though, that may not be possible.)
        $qtooltip = str_replace("'", "\\'", $tooltip_text);
        $attr['onmouseover'] = "window.status='$qtooltip';return true;";
        $attr['onmouseout']  = "window.status='';return true;";
    }
    
    /** Get HTML for the button.
     *
     * @return string HTML markup.
     */
    function asHTML () {
        return Element('a', $this->_attr,
                       $this->linkContents());
    }

    /** Get the HTML text of the button.
     *
     * (Subclasses implementing image buttons will probably want
     * to override this method so that it returns an <img> tag.)
     *
     * @return string HTML markup.
     */
    function linkContents () {
        return htmlspecialchars($this->_text);
    }
};

/**
 * A clickable image button.
 */
class ImageButton extends Button {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $url string The url (href) for the button.
     * @param $class string The CSS class for the button.
     * @param $img_url string URL for button's image.
     * @param $img_attr array Additional attributes for the &lt;img&gt; tag.
     */
    function ImageButton ($text, $url, $class, $img_url, $img_attr = false) {
        $this->Button($text, $url, $class);
        
        if (is_array($img_attr))
            $this->_img_attr = $img_attr;
        $this->_img_attr['src'] = $img_url;
        $this->_img_attr['alt'] = $text;
        $this->_img_attr['border'] = 0;
    }

    /** Get img tag.
     *
     * @return string The image tag for this button.
     */
    function linkContents() {
        return Element('img', $this->_img_attr);
    }
};

/**
 * A factory class used to aid in the construction of <code>Button</code>s.
 */
class ButtonFactory {

    /**
     * Action on current page.
     *
     * This constructs a button which performs an action on the
     * currently selected version of the current page.
     *
     * @param $action string The action to perform (e.g. 'edit', 'lock').
     * @param $label string Textual label for the button.
     * @param $page_or_rev mixed FIXME: need doc.
     * @return object A Button object.
     */
    function makeActionButton ($action, $label = false, $page_or_rev = false) {
        extract($this->_get_name_and_rev($page_or_rev));

        if (is_array($action)) {
            $attr = $action;
            $action = $attr['action'];
        }
        else
            $attr['action'] = $action;

        $class = is_safe_action($action) ? 'wikiaction' : 'wikiadmin';
        if (!$label)
            $label = $this->_labelForAction($action);

        if ($version)
            $attr['version'] = $version;

        if ($action == 'browse')
            unset($attr['action']);

        return $this->makeButton($label, WikiURL($pagename, $attr), $class);
    }

    /**
     * Link to wiki page.
     *
     * This constructs a button which simple links to
     * another page within the wiki.
     *
     * @param $page_or_rev mixed The page to link to.  This can be
     * given as a string (the page name), a WikiDB_Page object, or as
     * WikiDB_PageRevision object.  If given as a WikiDB_PageRevision
     * object, the button will link to a specific version of the
     * designated page, otherwise the button links to the most recent
     * version of the page.
     *
     * @return object A Button object.
     */
    function makeLinkButton ($page_or_rev) {
        extract($this->_get_name_and_rev($page_or_rev));

        $attr = $version ? array('version' => $version) : false;
        
        return $this->makeButton($pagename, WikiURL($pagename, $attr), 'wiki');
    }

    /**
     * Throw page at an "action page"
     *
     * This constructs a button which "throws" the current page
     * at another "action page" or "magic page".
     *
     * @param $action_page string Name of the action page to apply to this page.
     * @param $page_or_rev mixed FIXME: need docs.
     *
     * @return object A Button object.
     */
    function makeActionPageButton ($action_page, $page_or_rev = false) {
        extract($this->_get_name_and_rev($page_or_rev));
        $attr['page'] = $pagename;
        if ($version)
            $attr['rev'] = $version;
        
        return $this->makeButton($action_page, WikiURL($action_page, $attr), 'wikiaction');
    }

    /**
     * Construct a button
     *
     * This constructs a button of a type specified by the selected theme.
     *
     * @param $text string The text for the button.
     * @param $url string The url (href) for the button.
     * @param $class string The CSS class for the button.
     *
     * @return object A Button object.
     */
    function makeButton($text, $url, $class) {
        // FIXME: can probably eliminate this method.
        global $Theme;
        return $Theme->makeButton($text, $url, $class);
    }

    function _get_name_and_rev ($page_or_rev) {
        $version = false;
        
        if (empty($page_or_rev)) {
            global $request;
            $pagename = $request->getArg("pagename");
            $version = $request->getArg("version");
        }
        elseif (is_object($page_or_rev)) {
            if (isa($page_or_rev, 'WikiDB_PageRevision')) {
                $rev = $page_or_rev;
                $page = $rev->getPage();
                $version = $rev->getVersion();
            }
            else {
                $page = $page_or_rev;
            }
            $pagename = $page->getName();
        }
        else {
            $pagename = (string) $page_or_rev;
        }
        return compact('pagename', 'version');
    }
    
    function _labelForAction ($action) {
        switch ($action) {
        case 'edit':
            return _("Edit");
        case 'diff':
            return _("Diff");
            
        case 'logout':
            return _("SignOut");
        case 'login':
            return _("SignIn");

            
        case 'lock':
            return _("Lock Page");
        case 'unlock':
            return _("Unlock Page");
        case 'remove':
            return _("Remove Page");

        default:
            // I don't think the rest of these actually get used.
            // 'setprefs'
            // 'upload' 'dumpserial' 'loadfile' 'zip'
            // 'save' 'browse'
            return ucfirst($action);
        }
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
