<?php rcs_id('$Id: ButtonFactory.php,v 1.1 2002-01-15 23:40:25 dairiki Exp $');

class Button {
    function Button ($text, $url, $class) {
        $this->_attr = array('href' => $url,
                             'class' => $class);
        $this->_text = $text;
    }

    function addTooltip ($tooltip_text) {
        $attr = &$this->_attr;
        $attr['title'] = $tooltip_text;

        // FIXME: this should be initialized from title by an onLoad() function.
        //        (though, that may not be possible.)
        $qtooltip = str_replace("'", "\\'", $tooltip_text);
        $attr['onmouseover'] = "window.status='$qtooltip';return true;";
        $attr['onmouseout']  = "window.status='';return true;";
    }
    
    function asHTML () {
        return Element('a', $this->_attr,
                       $this->linkContents());
    }

    function linkContents () {
        return htmlspecialchars($this->_text);
    }
};

class ImageButton {
    function ImageButton ($text, $url, $class, $img_url, $img_attr = false) {
        $this->Button($text, $url, $class);
        
        if (is_array($img_attr))
            $this->_img_attr = $img_attr;
        $this->_img_attr['src'] = $img_url;
        $this->_img_attr['alt'] = $text;
    }

    function linkContents() {
        return Element('img', $this->_img_attr);
    }
};

class ButtonFactory {
    
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

    function makeLinkButton ($page_or_rev) {
        extract($this->_get_name_and_rev($page_or_rev));

        $attr = $version ? array('version' => $version) : false;
        
        return $this->makeButton($pagename, WikiURL($pagename, $attr), 'wiki');
    }

    function makeActionPageButton ($action_page, $page_or_rev = false) {
        extract($this->_get_name_and_rev($page_or_rev));
        $attr['page'] = $pagename;
        if ($version)
            $attr['rev'] = $version;
        
        return $this->makeButton($action_page, WikiURL($action_page, $attr), 'wikiaction');
    }

    function makeButton($text, $url, $class) {
        global $THEME;

        $button_class = empty($THEME['ButtonClass']) ? 'Button' : $THEME['ButtonClass'];
        
        return new $button_class($text, $url, $class);
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
            // 'setprefs'
            // 'upload' 'dumpserial' 'loadfile' 'zip'
            // 'edit' 'save' 'diff' 'browse'
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
