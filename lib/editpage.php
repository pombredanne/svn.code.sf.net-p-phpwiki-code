<?php
rcs_id('$Id: editpage.php,v 1.35 2002-02-04 20:40:25 carstenklapp Exp $');

require_once('lib/Template.php');

class PageEditor
{
    function PageEditor (&$request) {
        $this->request = &$request;

        $this->user = $request->getUser();
        $this->page = $request->getPage();

        $this->current = $this->page->getCurrentRevision();

        $this->meta = array('author' => $this->user->getId(),
                            'locked' => $this->page->get('locked'),
                            'author_id' => $this->user->getAuthenticatedId());

        $version = $request->getArg('version');
        if ($version !== false) {
            $this->selected = $this->page->getRevision($version);
            $this->version = $version;
        }
        else {
            $this->selected = $this->current;
            $this->version = $this->current->getVersion();
        }

        if ($this->_restoreState()) {
            $this->_initialEdit = false;
        }
        else {
            $this->_initializeState();
            $this->_initialEdit = true;
        }
    }

    function editPage () {
        $saveFailed = false;
        $tokens = array();

        if ($this->canEdit()) {
            if ($this->isInitialEdit())
                return $this->viewSource();
            $tokens['PAGE_LOCKED_MESSAGE'] = $this->getLockedMessage();
        }
        elseif ($this->editaction == 'save') {
            if ($this->savePage())
                return true;    // Page saved.
            $saveFailed = true;
        }

        if ($saveFailed || $this->isConcurrentUpdate())
            $tokens['CONCURRENT_UPDATE_MESSAGE'] = $this->getConflictMessage();

        if ($this->editaction == 'preview')
            $tokens['PREVIEW_CONTENT'] = $this->getPreview(); // FIXME: convert to _MESSAGE?

        // FIXME: NOT_CURRENT_MESSAGE?

        $tokens = array_merge($tokens, $this->getFormElements());

        return $this->output('editpage', _("Edit: %s"), $tokens);
    }

    function output ($template, $title_fs, $tokens) {
        $selected = &$this->selected;
        $current = &$this->current;

        if ($selected && $selected->getVersion() != $current->getVersion()) {
            $rev = $selected;
            $pagelink = WikiLink($selected);
        }
        else {
            $rev = $current;
            $pagelink = WikiLink($this->page);
        }


        $title = new FormattedText ($title_fs, $pagelink);
        $template = Template($template, $tokens);

        GeneratePage($template, $title, $rev);
        return true;
    }


    function viewSource ($tokens = false) {
        assert($this->isInitialEdit());
        assert($this->selected);

        $tokens['PAGE_SOURCE'] = $this->_content;

        return $this->output('viewsource', _("View Source: %s"), $tokens);
    }

    function setPageLockChanged($isadmin, $lock, &$page) {
        if ($isadmin) {
            if (! $page->get('locked') == $lock) {
                $request = &$this->request;
                $request->setArg('lockchanged', true); //is it safe to add new args to $request like this?
            }
            $page->set('locked', $lock);
        }
    }

    function savePage () {
        $request = &$this->request;

        if ($this->isUnchanged()) {
            // Allow admin lock/unlock even if
            // no text changes were made.
            if ($isadmin = $this->user->isadmin()) {
                $page = &$this->page;
                $lock = $this->meta['locked'];
                $this->setPageLockChanged($isadmin, $lock, &$page);
            }
            // Save failed. No changes made.
            include_once('lib/display.php');
            // force browse of current version:
            $request->setArg('version', false);
            displayPage($request, 'nochanges');
            return true;
        }

        $page = &$this->page;
        $lock = $this->meta['locked'];
        $this->meta['locked'] = ''; // hackish

        // Save new revision
        $newrevision = $page->createRevision($this->_currentVersion + 1,
                                             $this->_content,
                                             $this->meta,
                                             ExtractWikiPageLinks($this->_content));
        if (!is_object($newrevision)) {
            // Save failed.  (Concurrent updates).
            return false;
        }
        // New contents successfully saved...
        if ($isadmin = $this->user->isadmin())
            $this->setPageLockChanged($isadmin, $lock, &$page);

        // Clean out archived versions of this page.
        include_once('lib/ArchiveCleaner.php');
        $cleaner = new ArchiveCleaner($GLOBALS['ExpireParams']);
        $cleaner->cleanPageRevisions($page);

        $dbi = $request->getDbh();
        $warnings = $dbi->GenericWarnings();

        global $Theme;
        if (empty($warnings) && ! $Theme->getImageURL('signature')) {
            // Do redirect to browse page if no signature has
            // been defined.  In this case, the user will most
            // likely not see the rest of the HTML we generate
            // (below).
            $request->redirect(WikiURL($page, false, 'absolute_url'));
        }

        // Force browse of current page version.
        $request->setArg('version', false);
        include_once('lib/BlockParser.php');
        $template = Template('savepage',
                             array('CONTENT' => TransformRevision($newrevision)));
        if (!empty($warnings))
            $template->replace('WARNINGS', $warnings);

        $pagelink = WikiLink($page);

        GeneratePage($template, fmt("Saved: %s", $pagelink), $newrevision);
        return true;
    }

    function isConcurrentUpdate () {
        assert($this->current->getVersion() >= $this->_currentVersion);
        return $this->current->getVersion() != $this->_currentVersion;
    }

    function canEdit () {
        return $this->page->get('locked') && !$this->user->isAdmin();
    }

    function isInitialEdit () {
        return $this->_initialEdit;
    }

    function isUnchanged () {
        $current = &$this->current;

        if ($this->meta['markup'] !=  $current->get('markup'))
            return false;

        return $this->_content == $current->getPackedContent();
    }

    function getPreview () {
        if ($this->meta['markup'] == 'new') {
            include_once('lib/BlockParser.php');
            $trfm = 'NewTransform';
        }
        else {
            include_once('lib/transform.php');
            $trfm = 'do_transform';
        }
        return $trfm($this->_content);
    }

    function getLockedMessage () {
        return
        HTML(HTML::h2(_("Page Locked")),
             HTML::p(_("This page has been locked by the administrator so your changes can not be saved.")),
             HTML::p(_("(Copy your changes to the clipboard. You can try editing a different page or save your text in a text editor.)")),
             HTML::p(_("Sorry for the inconvenience.")));
    }

    function getConflictMessage () {
        /*
         xgettext only knows about c/c++ line-continuation strings
         it does not know about php's dot operator.
         We want to translate this entire paragraph as one string, of course.
         */

        $re_edit_link = Button('edit', _("Edit the new version"), $this->page);

        $steps = HTML::ol(HTML::li(_("Copy your changes to the clipboard or to another temporary place (e.g. text editor).")),
                          HTML::li(fmt("%s of the page. You should now see the most current version of the page. Your changes are no longer there.",
                                       $re_edit_link)),
                          HTML::li(_("Make changes to the file again. Paste your additions from the clipboard (or text editor).")),
                          HTML::li(_("Save your updated changes.")));

        return HTML(HTML::h2(_("Conflicting Edits!")),
                    HTML::p(_("In the time since you started editing this page, another user has saved a new version of it.  Your changes can not be saved, since doing so would overwrite the other author's changes.")),
                    HTML::p(_("In order to recover from this situation, follow these steps:")),
                    $steps,
                    HTML::p(_("Sorry for the inconvenience.")));
    }


    function getTextArea () {
        $request = &$this->request;

        // wrap=virtual is not HTML4, but without it NS4 doesn't wrap long lines
        $readonly = $this->canEdit() || $this->isConcurrentUpdate();

        return HTML::textarea(array('class'    => 'wikiedit',
                                    'name'     => 'edit[content]',
                                    'rows'     => $request->getPref('editHeight'),
                                    'cols'     => $request->getPref('editWidth'),
                                    'readonly' => (bool) $readonly,
                                    'wrap'     => 'virtual'),
                              $this->_content);
    }

    function getFormElements () {
        $request = &$this->request;
        $page = &$this->page;


        $h = array('action'   => 'edit',
                   'pagename' => $page->getName(),
                   'version'  => $this->version,
                   'edit[current_version]' => $this->_currentVersion);

        $el['HIDDEN_INPUTS'] = HiddenInputs($h);


        $el['EDIT_TEXTAREA'] = $this->getTextArea();

        $el['SUMMARY_INPUT']
            = HTML::input(array('type'  => 'text',
                                'class' => 'wikitext',
                                'name'  => 'edit[summary]',
                                'size'  => 50,
                                'value' => $this->meta['summary']));
        $el['MINOR_EDIT_CB']
            = HTML::input(array('type' => 'checkbox',
                                'name'  => 'edit[minor_edit]',
                                'checked' => (bool) $this->meta['is_minor_edit']));
        $el['NEW_MARKUP_CB']
            = HTML::input(array('type' => 'checkbox',
                                'name' => 'edit[markup]',
                                'value' => 'new',
                                'checked' => $this->meta['markup'] == 'new'));

        $el['LOCKED_CB']
            = HTML::input(array('type' => 'checkbox',
                                'name' => 'edit[locked]',
                                'disabled' => (bool) !$this->user->isadmin(),
                                'checked'  => (bool) $this->meta['locked']));

        $el['PREVIEW_B'] = Button('submit:edit[preview]', _("Preview"));

        if (!$this->isConcurrentUpdate() && !$this->canEdit())
            $el['SAVE_B'] = Button('submit:edit[save]', _("Save"));

        $el['IS_CURRENT'] = $this->version == $this->current->getVersion();

        return $el;
    }


    function _restoreState () {
        $request = &$this->request;

        $posted = $request->getArg('edit');
        $request->setArg('edit', false);

        if (!$posted || !$request->isPost() || $request->getArg('action') != 'edit')
            return false;

        if (!isset($posted['content']) || !is_string($posted['content']))
            return false;
        $this->_content = preg_replace('/[ \t\r]+\n/', "\n",
                                       rtrim($posted['content']));

        $this->_currentVersion = (int) $posted['current_version'];

        if ($this->_currentVersion < 0)
            return false;
        if ($this->_currentVersion > $this->current->getVersion())
            return false;       // FIXME: some kind of warning?

        $is_new_markup = !empty($posted['markup']) && $posted['markup'] == 'new';
        $meta['markup'] = $is_new_markup ? 'new' : false;
        $meta['summary'] = trim($posted['summary']);
        $meta['locked'] = !empty($posted['locked']);
        $meta['is_minor_edit'] = !empty($posted['minor_edit']);

        $this->meta = array_merge($this->meta, $meta);

        if (!empty($posted['preview']))
            $this->editaction = 'preview';
        elseif (!empty($posted['save']))
            $this->editaction = 'save';
        else
            $this->editaction = 'edit';

        return true;
    }

    function _initializeState () {
        $request = &$this->request;
        $current = &$this->current;
        $selected = &$this->selected;
        $user = &$this->user;

        if (!$selected)
            NoSuchRevision($request, $this->page, $this->version); // noreturn

        $this->_currentVersion = $current->getVersion();
        $this->_content = $selected->getPackedContent();

        $this->meta['summary'] = '';
        $this->meta['locked'] = $this->page->get('locked');

        // If author same as previous author, default minor_edit to on.
        $age = time() - $current->get('mtime');
        $this->meta['is_minor_edit'] = ( $age < MINOR_EDIT_TIMEOUT
                                         && $current->get('author') == $user->getId()
                                         );

        // Default for new pages is new-style markup.
        if ($selected->hasDefaultContents())
            $this->meta['markup'] = 'new';
        else
            $this->meta['markup'] = $selected->get('markup');

        $this->editaction = 'edit';
    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
