<?php
/**
 * Copyright © 2000 Steve Wainstead
 * Copyright © 2000-2001 Arno Hollosi
 * Copyright © 2001 Joel Uckelman
 * Copyright © 2001-2003 Jeff Dairiki
 * Copyright © 2001-2003 Carsten Klapp
 * Copyright © 2002 Lawrence Akka
 * Copyright © 2004-2009 Reini Urban
 * Copyright © 2007 Sabri Labbenes
 * Copyright © 2008-2015 Marc-Etienne Vargenau, Alcatel-Lucent
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

require_once 'lib/Template.php';
require_once 'lib/WikiUser.php';

class PageEditor
{
    public $request;
    public $user;
    public $page;
    /**
     * @var WikiDB_PageRevision $current
     */
    public $current;
    public $editaction;
    public $locked;
    public $public;
    public $external;
    public $_currentVersion;
    /**
     * @var UserPreferences $_prefs
     */
    private $_prefs;
    private $_isSpam;
    private $_wikicontent;

    /**
     * @param WikiRequest $request
     */
    public function __construct(&$request)
    {
        $this->request = &$request;

        $this->user = $request->getUser();
        $this->page = $request->getPage();

        $this->current = $this->page->getCurrentRevision(false);

        // HACKish short circuit to browse on action=create
        if ($request->getArg('action') == 'create') {
            if (!$this->current->hasDefaultContents()) {
                $request->redirect(WikiURL($this->page->getName()));
            } // noreturn
        }

        $this->meta = array('author' => $this->user->getId(),
            'author_id' => $this->user->getAuthenticatedId(),
            'mtime' => time());

        $this->tokens = array();

        if (defined('ENABLE_WYSIWYG') and ENABLE_WYSIWYG) {
            $backend = WYSIWYG_BACKEND;
            // TODO: error message
            require_once("lib/WysiwygEdit/$backend.php");
            $class = "WysiwygEdit_$backend";
            $this->WysiwygEdit = new $class();
        }
        if (defined('ENABLE_CAPTCHA' and ENABLE_CAPTCHA)) {
            require_once 'lib/Captcha.php';
            $this->Captcha = new Captcha($this->meta);
        }

        $version = $request->getArg('version');
        if ($version !== false) {
            $this->selected = $this->page->getRevision($version);
            $this->version = $version;
        } else {
            $this->version = $this->current->getVersion();
            $this->selected = $this->page->getRevision($this->version);
        }

        if ($this->_restoreState()) {
            $this->_initialEdit = false;
        } else {
            $this->_initializeState();
            $this->_initialEdit = true;

            // The edit request has specified some initial content from a template
            if (($template = $request->getArg('template'))
                and $request->_dbi->isWikiPage($template)
            ) {
                $page = $request->_dbi->getPage($template);
                $current = $page->getCurrentRevision();
                $this->_content = $current->getPackedContent();
            } elseif ($initial_content = $request->getArg('initial_content')) {
                $this->_content = $initial_content;
                $this->_redirect_to = $request->getArg('save_and_redirect_to');
            }
        }
        if (!headers_sent()) {
            header("Content-Type: text/html; charset=UTF-8");
        }
    }

    public function editPage()
    {
        $saveFailed = false;
        $tokens = &$this->tokens;
        $tokens['PAGE_LOCKED_MESSAGE'] = '';
        $tokens['LOCK_CHANGED_MSG'] = '';
        $tokens['CONCURRENT_UPDATE_MESSAGE'] = '';
        $r =& $this->request;

        if (isset($r->args['pref']['editWidth'])
            and ($r->getPref('editWidth') != $r->args['pref']['editWidth'])
        ) {
            $r->_prefs->set('editWidth', $r->args['pref']['editWidth']);
        }
        if (isset($r->args['pref']['editHeight'])
            and ($r->getPref('editHeight') != $r->args['pref']['editHeight'])
        ) {
            $r->_prefs->set('editHeight', $r->args['pref']['editHeight']);
        }

        if ($this->isModerated()) {
            $tokens['PAGE_LOCKED_MESSAGE'] = $this->getModeratedMessage();
        }

        if (!$this->canEdit()) {
            if ($this->isInitialEdit()) {
                return $this->viewSource();
            }
            $tokens['PAGE_LOCKED_MESSAGE'] = $this->getLockedMessage();
        } elseif ($r->getArg('save_and_redirect_to') != "") {
            if (defined('ENABLE_CAPTCHA') and ENABLE_CAPTCHA && $this->Captcha->Failed()) {
                $this->tokens['PAGE_LOCKED_MESSAGE'] =
                    HTML::p(HTML::h1($this->Captcha->failed_msg));
            } elseif ($this->savePage()) {
                // noreturn
                global $request;
                $request->setArg('action', false);
                $r->redirect(WikiURL($r->getArg('save_and_redirect_to')));
                return true; // Page saved.
            }
            $saveFailed = true;
        } elseif ($this->editaction == 'save') {
            if (defined('ENABLE_CAPTCHA') and ENABLE_CAPTCHA && $this->Captcha->Failed()) {
                $this->tokens['PAGE_LOCKED_MESSAGE'] =
                    HTML::p(HTML::h1($this->Captcha->failed_msg));
            } elseif ($this->savePage()) {
                return true; // Page saved.
            } else {
                $saveFailed = true;
            }
        } // coming from loadfile conflicts
        elseif ($this->editaction == 'keep_old') {
            // keep old page and do nothing
            $this->_redirectToBrowsePage();
            return true;
        } elseif ($this->editaction == 'overwrite') {
            // take the new content without diff
            $source = $this->request->getArg('loadfile');
            require_once 'lib/loadsave.php';
            $this->request->setArg('loadfile', 1);
            $this->request->setArg('overwrite', 1);
            $this->request->setArg('merge', 0);
            LoadFileOrDir($this->request);
            $this->_redirectToBrowsePage();
            return true;
        } elseif ($this->editaction == 'upload') {
            // run plugin UpLoad
            $plugin = new WikiPluginLoader();
            $plugin->run();
            // add link to content
        }

        if ($saveFailed and $this->isConcurrentUpdate()) {
            // Get the text of the original page, and the two conflicting edits
            // The diff3 class takes arrays as input.  So retrieve content as
            // an array, or convert it as necesary.
            $orig = $this->page->getRevision($this->_currentVersion);
            // FIXME: what if _currentVersion has be deleted?
            $orig_content = $orig->getContent();
            $this_content = explode("\n", $this->_content);
            $other_content = $this->current->getContent();
            require_once 'lib/diff3.php';
            $diff = new diff3($orig_content, $this_content, $other_content);
            $output = $diff->merged_output(_("Your version"), _("Other version"));
            // Set the content of the textarea to the merged diff
            // output, and update the version
            $this->_content = implode("\n", $output);
            $this->_currentVersion = $this->current->getVersion();
            $this->version = $this->_currentVersion;
            $unresolved = $diff->ConflictingBlocks;
            $tokens['CONCURRENT_UPDATE_MESSAGE']
                = $this->getConflictMessage($unresolved);
        } elseif ($saveFailed && !$this->_isSpam) {
            $tokens['CONCURRENT_UPDATE_MESSAGE'] =
                HTML(
                    HTML::h2(_("Some internal editing error")),
                    HTML::p(_("Your are probably trying to edit/create an invalid version of this page.")),
                    HTML::p(HTML::em(_("&version=-1 might help.")))
                );
        }

        if ($this->editaction == 'edit_convert') {
            $tokens['PREVIEW_CONTENT'] = $this->getConvertedPreview();
        }
        if ($this->editaction == 'preview') {
            $tokens['PREVIEW_CONTENT'] = $this->getPreview();
        } // FIXME: convert to _MESSAGE?
        if ($this->editaction == 'diff') {
            $tokens['PREVIEW_CONTENT'] = $this->getDiff();
        }

        // FIXME: NOT_CURRENT_MESSAGE?
        $tokens = array_merge($tokens, $this->getFormElements());

        return $this->output('editpage', _("Edit: %s"));
    }

    public function output($template, $title_fs)
    {
        global $WikiTheme;
        $selected = &$this->selected;
        $current = &$this->current;

        if ($selected && $selected->getVersion() != $current->getVersion()) {
            $rev = $selected;
            $pagelink = WikiLink($selected);
        } else {
            $rev = $current;
            $pagelink = WikiLink($this->page);
        }

        $title = new FormattedText($title_fs, $pagelink);
        // not for dumphtml or viewsource
        if (defined('ENABLE_WYSIWYG') and ENABLE_WYSIWYG and $template == 'editpage') {
            $WikiTheme->addMoreHeaders($this->WysiwygEdit->Head());
            //$tokens['PAGE_SOURCE'] = $this->WysiwygEdit->ConvertBefore($this->_content);
        }
        $template = Template($template, $this->tokens);
        /* Tell google (and others) not to take notice of edit links */
        if (defined('GOOGLE_LINKS_NOFOLLOW') and GOOGLE_LINKS_NOFOLLOW) {
            $args = array('ROBOTS_META' => "noindex,nofollow");
        }
        GeneratePage($template, $title, $rev);
        return true;
    }

    public function viewSource()
    {
        assert($this->isInitialEdit());
        assert($this->selected);

        $this->tokens['PAGE_SOURCE'] = $this->_content;
        $this->tokens['HIDDEN_INPUTS'] = HiddenInputs($this->request->getArgs());
        return $this->output('viewsource', _("View Source: %s"));
    }

    private function updateLock()
    {
        $changed = false;
        if (!ENABLE_PAGE_PUBLIC && !ENABLE_EXTERNAL_PAGES) {
            if ((bool)$this->page->get('locked') == (bool)$this->locked) {
                return false;
            } // Not changed.
        }

        if (!$this->user->isAdmin()) {
            // FIXME: some sort of message
            return false; // not allowed.
        }
        if ((bool)$this->page->get('locked') != (bool)$this->locked) {
            $this->page->set('locked', (bool)$this->locked);
            $this->tokens['LOCK_CHANGED_MSG']
                .= ($this->locked
                ? _("Page now locked.")
                : _("Page now unlocked."));
            $changed = true;
        }
        if (defined('ENABLE_PAGE_PUBLIC') and ENABLE_PAGE_PUBLIC
            and (bool)$this->page->get('public') != (bool)$this->public) {
            $this->page->set('public', (bool)$this->public);
            $this->tokens['LOCK_CHANGED_MSG']
                .= ($this->public
                ? _("Page now public.")
                : _("Page now not-public."));
            $changed = true;
        }

        if (defined('ENABLE_EXTERNAL_PAGES') and ENABLE_EXTERNAL_PAGES
            and (bool)$this->page->get('external') != (bool)$this->external) {
            $this->page->set('external', (bool)$this->external);
            $this->tokens['LOCK_CHANGED_MSG']
                .= ($this->external
                ? _("Page now external.")
                : _("Page now not-external."));
            $changed = true;
        }
        return $changed; // lock changed.
    }

    public function savePage()
    {
        $request = &$this->request;

        $lock_changed = false;
        if ($this->isUnchanged()) {
            // Allow admin lock/unlock even if
            // no text changes were made.
            if ($this->updateLock()) {
                $lock_changed = true;
            } else {
                // Save failed. No changes made.
                $this->_redirectToBrowsePage();
                return true;
            }
        }

        if (!$this->user->isAdmin() and $this->isSpam()) {
            $this->_isSpam = true;
            return false;
        }

        $page = &$this->page;

        // Include any meta-data from original page version which
        // has not been explicitly updated.
        $meta = $this->selected->getMetaData();
        $meta = array_merge($meta, $this->meta);
        if ($lock_changed) {
            $meta['summary'] = $this->tokens['LOCK_CHANGED_MSG'];
        }

        // Save new revision
        $this->_content = $this->getContent();
        $newrevision = $page->save(
            $this->_content,
            $this->version == -1
                ? -1
                : $this->_currentVersion + 1,
            // force new?
            $meta
        );
        if (!is_a($newrevision, 'WikiDB_PageRevision')) {
            // Save failed.  (Concurrent updates).
            return false;
        }

        // New contents successfully saved...
        $this->updateLock();

        /* generate notification emails done in WikiDB::save to catch
         all direct calls (admin plugins) */

        // look at the errorstack
        $errors = $GLOBALS['ErrorManager']->_postponed_errors;
        $warnings = $GLOBALS['ErrorManager']->getPostponedErrorsAsHTML();
        $GLOBALS['ErrorManager']->_postponed_errors = $errors;

        $dbi = $request->getDbh();
        $dbi->touch();

        global $WikiTheme;
        if (empty($warnings->_content) && !$WikiTheme->getImageURL('signature')) {
            // Do redirect to browse page if no signature has
            // been defined.  In this case, the user will most
            // likely not see the rest of the HTML we generate
            // (below).
            $request->setArg('action', false);
            $this->_redirectToBrowsePage();
            return true;
        }

        // Force browse of current page version.
        $request->setArg('version', false);
        // testme: does preview and more need action=edit?
        $request->setArg('action', false);

        $template = Template('savepage', $this->tokens);
        $template->replace('CONTENT', $newrevision->getTransformedContent());
        if (!empty($warnings->_content)) {
            $template->replace('WARNINGS', $warnings);
            unset($GLOBALS['ErrorManager']->_postponed_errors);
        }

        $pagelink = WikiLink($page);

        GeneratePage($template, fmt("Saved: %s", $pagelink), $newrevision);
        return true;
    }

    protected function isConcurrentUpdate()
    {
        assert($this->current->getVersion() >= $this->_currentVersion);
        return $this->current->getVersion() != $this->_currentVersion;
    }

    protected function canEdit()
    {
        return !$this->page->get('locked') || $this->user->isAdmin();
    }

    protected function isInitialEdit()
    {
        return $this->_initialEdit;
    }

    private function isUnchanged()
    {
        $current = &$this->current;
        return $this->_content == $current->getPackedContent();
    }

    /**
     * Handle AntiSpam here. How? http://wikiblacklist.blogspot.com/
     * Need to check dynamically some blacklist wikipage settings
     * (plugin WikiAccessRestrictions) and some static blacklist.
     * DONE:
     *   More than NUM_SPAM_LINKS (default: 20) new external links.
     *        Disabled if NUM_SPAM_LINKS is 0
     *   ENABLE_SPAMASSASSIN:  content patterns by babycart (only php >= 4.3 for now)
     *   ENABLE_SPAMBLOCKLIST: content domain blacklist
     */
    private function isSpam()
    {
        $current = &$this->current;
        $request = &$this->request;

        $oldtext = $current->getPackedContent();
        $newtext =& $this->_content;
        $numlinks = $this->numLinks($newtext);
        $newlinks = $numlinks - $this->numLinks($oldtext);
        // FIXME: in longer texts the NUM_SPAM_LINKS number should be increased.
        //        better use a certain text : link ratio.

        // 1. Not more than NUM_SPAM_LINKS (default: 20) new external links
        if ((NUM_SPAM_LINKS > 0) and ($newlinks >= NUM_SPAM_LINKS)) {
            // Allow strictly authenticated users?
            // TODO: mail the admin?
            $this->tokens['PAGE_LOCKED_MESSAGE'] =
                HTML(
                    $this->getSpamMessage(),
                    HTML::p(HTML::strong(_("Too many external links.")))
                );
            return true;
        }
        // 2. external babycart (SpamAssassin) check
        // This will probably prevent from discussing sex or viagra related topics. So beware.
        if (ENABLE_SPAMASSASSIN) {
            require_once 'lib/spam_babycart.php';
            if ($babycart = check_babycart($newtext, $request->get("REMOTE_ADDR"))) {
                // TODO: mail the admin
                if (is_array($babycart)) {
                    $this->tokens['PAGE_LOCKED_MESSAGE'] =
                        HTML(
                            $this->getSpamMessage(),
                            HTML::p(HTML::em(
                                _("SpamAssassin reports: "),
                                join("\n", $babycart)
                            ))
                        );
                }
                return true;
            }
        }
        // 3. extract (new) links and check surbl for blocked domains
        if (defined('ENABLE_SPAMBLOCKLIST') and ENABLE_SPAMBLOCKLIST and ($newlinks > 5)) {
            require_once 'lib/SpamBlocklist.php';
            require_once 'lib/InlineParser.php';
            $parsed = TransformLinks($newtext);
            $oldparsed = TransformLinks($oldtext);
            $oldlinks = array();
            foreach ($oldparsed->_content as $link) {
                if (is_a($link, 'Cached_ExternalLink') and !is_a($link, 'Cached_InterwikiLink')) {
                    $uri = $link->_getURL($this->page->getName());
                    $oldlinks[$uri]++;
                }
            }
            unset($oldparsed);
            foreach ($parsed->_content as $link) {
                if (is_a($link, 'Cached_ExternalLink') and !is_a($link, 'Cached_InterwikiLink')) {
                    $uri = $link->_getURL($this->page->getName());
                    // only check new links, so admins may add blocked links.
                    if (!array_key_exists($uri, $oldlinks) and ($res = IsBlackListed($uri))) {
                        // TODO: mail the admin
                        $this->tokens['PAGE_LOCKED_MESSAGE'] =
                            HTML(
                                $this->getSpamMessage(),
                                HTML::p(
                                    HTML::strong(_("External links contain blocked domains:")),
                                    HTML::ul(HTML::li(sprintf(
                                        _("%s is listed at %s with %s"),
                                        $uri . " [" . $res[2] . "]",
                                        $res[0],
                                        $res[1]
                                    )))
                                )
                            );
                        return true;
                    }
                }
            }
            unset($oldlinks);
            unset($parsed);
            unset($oldparsed);
        }

        return false;
    }

    /** Number of external links in the wikitext
     */
    private function numLinks(&$text)
    {
        return substr_count($text, "http://") + substr_count($text, "https://");
    }

    /** Header of the Anti Spam message
     */
    private function getSpamMessage()
    {
        return
            HTML(
                HTML::h2(_("Spam Prevention")),
                HTML::p(
                    _("This page edit seems to contain spam and was therefore not saved."),
                    HTML::br(),
                    _("Sorry for the inconvenience.")
                ),
                HTML::p("")
            );
    }

    protected function getPreview()
    {
        require_once 'lib/PageType.php';
        $this->_content = $this->getContent();
        return new TransformedText($this->page, $this->_content, $this->meta);
    }

    protected function getConvertedPreview()
    {
        require_once 'lib/PageType.php';
        $this->_content = $this->getContent();
        return new TransformedText($this->page, $this->_content, $this->meta);
    }

    private function getDiff()
    {
        require_once 'lib/diff.php';
        $html = HTML();

        $diff = new Diff($this->current->getContent(), explode("\n", $this->getContent()));
        if ($diff->isEmpty()) {
            $html->pushContent(
                HTML::hr(),
                HTML::p(
                                   array('class' => 'warning_msg'),
                                   _("Versions are identical")
                               )
            );
        } else {
            // New CSS formatted unified diffs
            $fmt = new HtmlUnifiedDiffFormatter();
            $html->pushContent($fmt->format($diff));
        }
        return $html;
    }

    // possibly convert HTMLAREA content back to Wiki markup
    private function getContent()
    {
        if (ENABLE_WYSIWYG) {
            // don't store everything as html
            if (!WYSIWYG_DEFAULT_PAGETYPE_HTML) {
                // Wikiwyg shortcut to avoid the InlineTransformer:
                if (WYSIWYG_BACKEND == "Wikiwyg") {
                    return $this->_content;
                }
                $xml_output = $this->WysiwygEdit->ConvertAfter($this->_content);
                $this->_content = join("", $xml_output->_content);
            } else {
                $this->meta['pagetype'] = 'html';
            }
            return $this->_content;
        } else {
            return $this->_content;
        }
    }

    protected function getLockedMessage()
    {
        return
            HTML(
                HTML::h2(_("Page Locked")),
                HTML::p(_("This page has been locked by the administrator so your changes cannot be saved.")),
                HTML::p(_("(Copy your changes to the clipboard. You can try editing a different page or save your text in a text editor.)")),
                HTML::p(_("Sorry for the inconvenience."))
            );
    }

    private function isModerated()
    {
        return $this->page->get('moderation');
    }

    private function getModeratedMessage()
    {
        return
            HTML(
                HTML::h2(WikiLink(__("ModeratedPage"))),
                HTML::p(fmt("You can edit away, but your changes will have to be approved by the defined moderators at the definition in %s", WikiLink(_("ModeratedPage")))),
                HTML::p(fmt(
                    "The approval has a grace period of 5 days. If you have your e-mail defined in your %s, you will get a notification of approval or rejection.",
                    WikiLink(__("UserPreferences"))
                ))
            );
    }

    protected function getConflictMessage($unresolved = false)
    {
        /*
         xgettext only knows about c/c++ line-continuation strings
         it does not know about php's dot operator.
         We want to translate this entire paragraph as one string, of course.
         */

        //$re_edit_link = Button('edit', _("Edit the new version"), $this->page);

        if ($unresolved) {
            $message = HTML::p(fmt(
                "Some of the changes could not automatically be combined.  Please look for sections beginning with “%s”, and ending with “%s”.  You will need to edit those sections by hand before you click Save.",
                "<<<<<<< " . _("Your version"),
                ">>>>>>> " . _("Other version")
            ));
        } else {
            $message = HTML::p(_("Please check it through before saving."));
        }

        /*$steps = HTML::ol(HTML::li(_("Copy your changes to the clipboard or to another temporary place (e.g. text editor).")),
          HTML::li(fmt("%s of the page. You should now see the most current version of the page. Your changes are no longer there.",
                       $re_edit_link)),
          HTML::li(_("Make changes to the file again. Paste your additions from the clipboard (or text editor).")),
          HTML::li(_("Save your updated changes.")));
        */
        return
            HTML(
                HTML::h2(_("Conflicting Edits!")),
                HTML::p(_("In the time since you started editing this page, another user has saved a new version of it.")),
                HTML::p(_("Your changes cannot be saved as they are, since doing so would overwrite the other author's changes. So, your changes and those of the other author have been combined. The result is shown below.")),
                $message
            );
    }

    private function getTextArea()
    {
        global $WikiTheme;

        $request = &$this->request;

        $readonly = !$this->canEdit(); // || $this->isConcurrentUpdate();

        // WYSIWYG will need two pagetypes: raw wikitest and converted html
        if (ENABLE_WYSIWYG) {
            $this->_wikicontent = $this->_content;
            $this->_content = $this->WysiwygEdit->ConvertBefore($this->_content);
            //                $this->getPreview();
            //$this->_htmlcontent = $this->_content->asXML();
        }

        $textarea = HTML::textarea(
            array('class' => 'wikiedit',
                'name' => 'edit[content]',
                'id' => 'edit-content',
                'rows' => $request->getPref('editHeight'),
                'cols' => $request->getPref('editWidth'),
                'readonly' => (bool)$readonly),
            $this->_content
        );

        if (defined('JS_SEARCHREPLACE') and JS_SEARCHREPLACE) {
            $this->tokens['JS_SEARCHREPLACE'] = 1;
            $undo_btn = $WikiTheme->getImageURL("ed_undo.png");
            $undo_d_btn = $WikiTheme->getImageURL("ed_undo_d.png");
            // JS_SEARCHREPLACE from walterzorn.de
            $js = JavaScript("
uri_undo_btn   = '" . $undo_btn . "'
msg_undo_alt   = '" . _("Undo") . "'
uri_undo_d_btn = '" . $undo_d_btn . "'
msg_undo_d_alt = '" . _("Undo disabled") . "'
msg_do_undo    = '" . _("Operation undone") . "'
msg_replfound  = '" . _("Substring “\\1” found \\2 times. Replace with “\\3”?") . "'
msg_replnot    = '" . _("String “%s” not found.") . "'
msg_repl_title     = '" . _("Search & Replace") . "'
msg_repl_search    = '" . _("Search for") . "'
msg_repl_replace_with = '" . _("Replace with") . "'
msg_repl_ok        = '" . _("OK") . "'
msg_repl_close     = '" . _("Close") . "'
");
            if (empty($WikiTheme->_headers_printed)) {
                $WikiTheme->addMoreHeaders($js);
                $WikiTheme->addMoreAttr('body', "SearchReplace", " onload='define_f()'");
            } else { // from an actionpage: WikiBlog, AddComment, WikiForum
                PrintXML($js);
            }
        } else {
            $WikiTheme->addMoreAttr('body', "editfocus", "document.getElementById('edit-content]').editarea.focus()");
        }

        if (defined('ENABLE_WYSIWYG') and ENABLE_WYSIWYG) {
            return $this->WysiwygEdit->Textarea(
                $textarea,
                $this->_wikicontent,
                $textarea->getAttr('name')
            );
        } elseif (defined('ENABLE_EDIT_TOOLBAR') and ENABLE_EDIT_TOOLBAR) {
            $init = JavaScript("var data_path = '" . javascript_quote_string(DATA_PATH) . "';\n");
            $js = JavaScript('', array('src' => $WikiTheme->_findData("toolbar.js")));
            if (empty($WikiTheme->_headers_printed)) {
                $WikiTheme->addMoreHeaders($init);
                $WikiTheme->addMoreHeaders($js);
            } else { // from an actionpage: WikiBlog, AddComment, WikiForum
                PrintXML($init);
                PrintXML($js);
                PrintXML(JavaScript('define_f()'));
            }
            $toolbar = HTML::div(array('class' => 'edit-toolbar', 'id' => 'toolbar'));
            $toolbar->pushContent(HTML::input(array('src' => $WikiTheme->getImageURL("ed_save.png"),
                                                    'name' => 'edit[save]',
                                                    'class' => 'toolbar',
                                                    'alt' => _('Save'),
                                                    'title' => _('Save'),
                                                    'type' => 'image')));
            $toolbar->pushContent(HTML::input(array('src' => $WikiTheme->getImageURL("ed_preview.png"),
                                                    'name' => 'edit[preview]',
                                                    'class' => 'toolbar',
                                                    'alt' => _('Preview'),
                                                    'title' => _('Preview'),
                                                    'type' => 'image')));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_format_bold.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Bold text'),
                                                  'title' => _('Bold text'),
                                                  'onclick' => "insertTags('**','**','"._('Bold text')."'); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_format_italic.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Italic text'),
                                                  'title' => _('Italic text'),
                                                  'onclick' => "insertTags('//','//','"._('Italic text')."'); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_format_strike.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Strike-through text'),
                                                  'title' => _('Strike-through text'),
                                                  'onclick' => "insertTags('<s>','</s>','"._('Strike-through text')."'); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_format_color.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Color text'),
                                                  'title' => _('Color text'),
                                                  'onclick' => "insertTags('%color=green%','%%','"._('Color text')."'); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_pagelink.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Link to page'),
                                                  'title' => _('Link to page'),
                                                  'onclick' => "insertTags('[[',']]','"._('PageName|optional label')."'); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_link.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('External link (remember http:// prefix)'),
                                                  'title' => _('External link (remember http:// prefix)'),
                                                  'onclick' => "insertTags('[[',']]','"._('http://www.example.com|optional label')."'); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_headline.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Level 1 headline'),
                                                  'title' => _('Level 1 headline'),
                                                  'onclick' => 'insertTags("\n== "," ==\n","'._("Headline text").'"); return true;')));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_nowiki.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Ignore wiki formatting'),
                                                  'title' => _('Ignore wiki formatting'),
                                                  'onclick' => 'insertTags("<verbatim>\n","\n</verbatim>","'._("Insert non-formatted text here").'"); return true;')));
            global $request;
            $username = $request->_user->UserName();
            $signature = " ––[[" . $username . "]] " . CTime() . '\n';
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_sig.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Your signature'),
                                                  'title' => _('Your signature'),
                                                  'onclick' => "insertTags('".$signature."','',''); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_hr.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Horizontal line'),
                                                  'title' => _('Horizontal line'),
                                                  'onclick' => 'insertTags("\n----\n","",""); return true;')));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_table.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Sample table'),
                                                  'title' => _('Sample table'),
                                                  'onclick' => 'insertTags("\n{| class=\"bordered\"\n|+ This is the table caption\n|-\n! Header A !! Header B !! Header C\n|-\n| Cell A1 || Cell B1 || Cell C1\n|-\n| Cell A2 || Cell B2 || Cell C2\n|-\n| Cell A3 || Cell B3 || Cell C3\n|}\n","",""); return true;')));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_tab_to_table.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Convert tab to table'),
                                                  'title' => _('Convert tab to table'),
                                                  'onclick' => "convert_tab_to_table();")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_enumlist.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Enumeration'),
                                                  'title' => _('Enumeration'),
                                                  'onclick' => 'insertTags("\n# Item 1\n# Item 2\n# Item 3\n","",""); return true;')));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_list.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('List'),
                                                  'title' => _('List'),
                                                  'onclick' => 'insertTags("\n* Item 1\n* Item 2\n* Item 3\n","",""); return true;')));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_toc.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Table of Contents'),
                                                  'title' => _('Table of Contents'),
                                                  'onclick' => 'insertTags("<<CreateToc with_toclink||=1>>\n","",""); return true;')));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_redirect.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Redirect'),
                                                  'title' => _('Redirect'),
                                                  'onclick' => "insertTags('<<RedirectTo page=\"','\">>','"._('Page Name')."'); return true;")));
            $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_templateplugin.png"),
                                                  'class' => 'toolbar',
                                                  'alt' => _('Insert Dynamic Template'),
                                                  'title' => _('Insert Dynamic Template'),
                                                  'onclick' => "insertTags('{{','}}','"._('Template Name')."'); return true;")));
            if (defined('TOOLBAR_TEMPLATE_PULLDOWN') and TOOLBAR_TEMPLATE_PULLDOWN) {
                $toolbar->pushContent($this->templatePulldown());
            }
            $toolbar->pushContent($this->categoriesPulldown());
            $toolbar->pushContent($this->pluginPulldown());
            if (defined('TOOLBAR_PAGELINK_PULLDOWN') and TOOLBAR_PAGELINK_PULLDOWN) {
                $toolbar->pushContent($this->pagesPulldown());
            }
            if (defined('TOOLBAR_IMAGE_PULLDOWN') and TOOLBAR_IMAGE_PULLDOWN) {
                $toolbar->pushContent($this->imagePulldown());
            }
            if (defined('JS_SEARCHREPLACE') and JS_SEARCHREPLACE) {
                $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_replace.png"),
                                                      'class' => 'toolbar',
                                                      'alt' => _('Search & Replace'),
                                                      'title' => _('Search & Replace'),
                                                      'onclick' => "replace();")));
                $toolbar->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("ed_undo_d.png"),
                                                      'class' => 'toolbar',
                                                      'id' => 'sr_undo',
                                                      'alt' => _('Undo Search & Replace'),
                                                      'title' => _('Undo Search & Replace'),
                                                      'onclick' => "do_undo();")));
            }
            return HTML($toolbar, $textarea);
        } else {
            return $textarea;
        }
    }

    private function categoriesPulldown()
    {
        /**
         * @var WikiRequest $request
         */
        global $request;
        global $WikiTheme;

        require_once 'lib/TextSearchQuery.php';
        $dbi =& $request->_dbi;
        // KEYWORDS formerly known as $KeywordLinkRegexp
        $pages = $dbi->titleSearch(new TextSearchQuery(KEYWORDS, true));
        if ($pages) {
            $categories = array();
            while ($p = $pages->next()) {
                $page = $p->getName();
                $categories[] = "['$page', '%0A----%0A%5B%5B" . $page . "%5D%5D']";
            }
            if (!$categories) {
                return '';
            }
            // Ensure this to be inserted at the very end. Hence we added the id to the function.
            return HTML::img(array('class' => "toolbar",
                'id' => 'tb-categories',
                'src' => $WikiTheme->getImageURL("ed_category.png"),
                'title' => _("Insert Categories"),
                'alt' => _("Insert Categories"), // to detect this at js
                'onclick' => "showPulldown('" .
                    _("Insert Categories")
                    . "',[" . join(",", $categories) . "],'"
                    . _("Insert") . "','"
                    . _("Close") . "','tb-categories')"));
        }
        return '';
    }

    private function pluginPulldown()
    {
        global $WikiTheme;
        global $AllAllowedPlugins;

        $plugin_dir = 'lib/plugin';
        if (defined('PHPWIKI_DIR')) {
            $plugin_dir = PHPWIKI_DIR . "/$plugin_dir";
        }
        $pd = new FileSet($plugin_dir, '*.php');
        $plugins = $pd->getFiles();
        unset($pd);
        sort($plugins);
        if (!empty($plugins)) {
            $plugin_js = '';
            require_once 'lib/WikiPlugin.php';
            $w = new WikiPluginLoader();
            foreach ($plugins as $plugin) {
                $pluginName = str_replace(".php", "", $plugin);
                if (in_array($pluginName, $AllAllowedPlugins)) {
                    $p = $w->getPlugin($pluginName);
                    // trap php files which aren't WikiPlugin~s
                    if (strtolower(substr(get_parent_class($p), 0, 10)) == 'wikiplugin') {
                        $plugin_args = '';
                        $desc = $p->getArgumentsDescription();
                        $src = array("\n", '"', "'", '|', '[', ']', '\\');
                        $replace = array('%0A', '%22', '%27', '%7C', '%5B', '%5D', '%5C');
                        $desc = str_replace("<br />", ' ', $desc->asXML());
                        if ($desc) {
                            $plugin_args = ' ' . str_replace($src, $replace, $desc);
                        }
                        $toinsert = "%0A<<" . $pluginName . $plugin_args . ">>"; // args?
                        $plugin_js .= ",['$pluginName','$toinsert']";
                    }
                }
            }
            $plugin_js = substr($plugin_js, 1);
            return HTML::img(array('class' => "toolbar",
                'id' => 'tb-plugins',
                'src' => $WikiTheme->getImageURL("ed_plugins.png"),
                'title' => _("Insert Plugin"),
                'alt' => _("Insert Plugin"),
                'onclick' => "showPulldown('" .
                    _("Insert Plugin")
                    . "',[" . $plugin_js . "],'"
                    . _("Insert") . "','"
                    . _("Close") . "','tb-plugins')"));
        }
        return '';
    }

    private function pagesPulldown()
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        require_once 'lib/TextSearchQuery.php';
        $dbi =& $request->_dbi;
        $page_iter = $dbi->titleSearch(new TextSearchQuery(TOOLBAR_PAGELINK_PULLDOWN, false, 'auto'));
        if ($page_iter->count() > 0) {
            global $WikiTheme;
            $pages = array();
            while ($p = $page_iter->next()) {
                $page = $p->getName();
                $pages[] = "['$page', '%5B%5B" . $page . "%5D%5D']";
            }
            return HTML::img(array('class' => "toolbar",
                'id' => 'tb-pages',
                'src' => $WikiTheme->getImageURL("ed_pages.png"),
                'title' => _("Insert PageLink"),
                'alt' => _("Insert PageLink"),
                'onclick' => "showPulldown('" .
                    _("Insert PageLink")
                    . "',[" . join(",", $pages) . "],'"
                    . _("Insert") . "','"
                    . _("Close") . "','tb-pages')"));
        }
        return '';
    }

    private function templatePulldown()
    {
        global $request;
        require_once 'lib/TextSearchQuery.php';
        $dbi =& $request->_dbi;
        $page_iter = $dbi->titleSearch(new TextSearchQuery(TOOLBAR_TEMPLATE_PULLDOWN, false, 'auto'));
        if ($page_iter->count()) {
            global $WikiTheme;
            $pages_js = '';
            while ($p = $page_iter->next()) {
                $rev = $p->getCurrentRevision();
                $toinsert = str_replace(array("\n", '"'), array('__nl__', '__quot__'), $rev->_get_content());
                $pages_js .= ",['" . $p->getName() . "','__nl__$toinsert']";
            }
            $pages_js = substr($pages_js, 1);
            if (!empty($pages_js)) {
                return HTML::img(array('class' => "toolbar",
                    'id' => 'tb-templates',
                    'src' => $WikiTheme->getImageURL("ed_template.png"),
                    'title' => _("Insert Static Template"),
                    'alt' => _("Insert Static Template"),
                    'onclick' => "showPulldown('" .
                        _("Insert Static Template")
                        . "',[" . $pages_js . "],'"
                        . _("Insert") . "','"
                        . _("Close") . "','tb-templates')"));
            }
        }
        return '';
    }

    private function imagePulldown()
    {
        global $WikiTheme, $request;

        $image_dir = getUploadFilePath();
        $pd = new ImageOrVideoSet($image_dir, '*');
        $images = $pd->getFiles();
        unset($pd);
        if (defined('UPLOAD_USERDIR') and UPLOAD_USERDIR) {
            $image_dir .= "/" . $request->_user->_userid;
            $pd = new ImageOrVideoSet($image_dir, '*');
            $userimages = $pd->getFiles();
            unset($pd);
            foreach ($userimages as $image) {
                $images[] = $request->_user->_userid . '/' . $image;
            }
        }
        sort($images);
        if (!empty($images)) {
            $image_js = '';
            foreach ($images as $image) {
                $image_js .= ",['$image','{{" . $image . "}}']";
            }
            $image_js = substr($image_js, 1);
            $more_buttons = HTML::img(array('class' => "toolbar",
                'id' => 'tb-images',
                'src' => $WikiTheme->getImageURL("ed_image.png"),
                'title' => _("Insert Image or Video"),
                'alt' => _("Insert Image or Video"),
                'onclick' => "showPulldown('" .
                    _("Insert Image or Video")
                    . "',[" . $image_js . "],'"
                    . _("Insert") . "','"
                    . _("Close") . "','tb-images')"));
            return $more_buttons;
        }
        return '';
    }

    protected function getFormElements()
    {
        global $WikiTheme;
        $request = &$this->request;
        $page = &$this->page;

        $h = array('action' => 'edit',
            'pagename' => $page->getName(),
            'version' => $this->version,
            'edit[pagetype]' => $this->meta['pagetype'],
            'edit[current_version]' => $this->_currentVersion);

        $el['HIDDEN_INPUTS'] = HiddenInputs($h);
        $el['EDIT_TEXTAREA'] = $this->getTextArea();
        if (ENABLE_CAPTCHA) {
            $el = array_merge($el, $this->Captcha->getFormElements());
        }
        $el['SUMMARY_INPUT']
            = HTML::input(array('type' => 'text',
            'class' => 'wikitext',
            'id' => 'edit-summary',
            'name' => 'edit[summary]',
            'size' => 50,
            'maxlength' => 256,
            'value' => $this->meta['summary']));
        $el['MINOR_EDIT_CB']
            = HTML::input(array('type' => 'checkbox',
            'name' => 'edit[minor_edit]',
            'id' => 'edit-minor_edit',
            'checked' => (bool)$this->meta['is_minor_edit']));
        $el['LOCKED_CB']
            = HTML::input(array('type' => 'checkbox',
            'name' => 'edit[locked]',
            'id' => 'edit-locked',
            'disabled' => (bool)!$this->user->isAdmin(),
            'checked' => (bool)$this->locked));
        if (ENABLE_PAGE_PUBLIC) {
            $el['PUBLIC_CB']
                = HTML::input(array('type' => 'checkbox',
                'name' => 'edit[public]',
                'id' => 'edit-public',
                'disabled' => (bool)!$this->user->isAdmin(),
                'checked' => (bool)$this->page->get('public')));
        }
        if (ENABLE_EXTERNAL_PAGES) {
            $el['EXTERNAL_CB']
                = HTML::input(array('type' => 'checkbox',
                'name' => 'edit[external]',
                'id' => 'edit-external',
                'disabled' => (bool)!$this->user->isAdmin(),
                'checked' => (bool)$this->page->get('external')));
        }
        if (ENABLE_WYSIWYG) {
            if (($this->version == 0) and ($request->getArg('mode') != 'wysiwyg')) {
                $el['WYSIWYG_B'] = Button(array("action" => "edit", "mode" => "wysiwyg"), "Wysiwyg Editor");
            }
        }
        $el['PREVIEW_B'] = Button(
            'submit:edit[preview]',
            _("Preview"),
            'wikiaction',
            array('title' => _('Preview the current content'))
        );
        $el['SAVE_B'] = Button(
            'submit:edit[save]',
            _("Save"),
            'wikiaction',
            array('title' => _('Save the current content as wikipage'))
        );
        $el['CHANGES_B'] = Button(
            'submit:edit[diff]',
            _("Changes"),
            'wikiaction',
            array('title' => _('Preview the current changes as diff'))
        );
        $el['IS_CURRENT'] = $this->version == $this->current->getVersion();
        $el['SEP'] = $WikiTheme->getButtonSeparator();
        $el['AUTHOR_MESSAGE'] = fmt(
            "Author will be logged as %s.",
            HTML::em($this->user->getId())
        );

        return $el;
    }

    private function _redirectToBrowsePage()
    {
        $this->request->redirect(WikiURL($this->page, array(), 'absolute_url'));
    }

    private function _restoreState()
    {
        $request = &$this->request;

        $posted = $request->getArg('edit');
        $request->setArg('edit', false);

        if (!$posted
            || !$request->isPost()
            || !in_array($request->getArg('action'), array('edit', 'loadfile'))
        ) {
            return false;
        }

        if (!isset($posted['content']) || !is_string($posted['content'])) {
            return false;
        }
        $this->_content = preg_replace(
            '/[ \t\r]+\n/',
            "\n",
            rtrim($posted['content'])
        );
        $this->_content = $this->getContent();

        $this->_currentVersion = (int)$posted['current_version'];

        if ($this->_currentVersion < 0) {
            return false;
        }
        if ($this->_currentVersion > $this->current->getVersion()) {
            return false;
        } // FIXME: some kind of warning?

        $meta['summary'] = trim(substr($posted['summary'], 0, 256));
        $meta['is_minor_edit'] = !empty($posted['minor_edit']);
        $meta['pagetype'] = !empty($posted['pagetype']) ? $posted['pagetype'] : false;
        if (ENABLE_CAPTCHA) {
            $meta['captcha_input'] = !empty($posted['captcha_input']) ?
                $posted['captcha_input'] : '';
        }

        $this->meta = array_merge($this->meta, $meta);
        $this->locked = !empty($posted['locked']);
        if (ENABLE_PAGE_PUBLIC) {
            $this->public = !empty($posted['public']);
        }
        if (ENABLE_EXTERNAL_PAGES) {
            $this->external = !empty($posted['external']);
        }

        foreach (array('preview', 'save', 'edit_convert',
                     'keep_old', 'overwrite', 'diff', 'upload') as $o) {
            if (!empty($posted[$o])) {
                $this->editaction = $o;
            }
        }
        if (empty($this->editaction)) {
            $this->editaction = 'edit';
        }

        return true;
    }

    private function _initializeState()
    {
        $request = &$this->request;
        $current = &$this->current;
        $selected = &$this->selected;
        $user = &$this->user;

        if (!$selected) {
            NoSuchRevision($request, $this->page, $this->version);
        } // noreturn

        $this->_currentVersion = $current->getVersion();
        $this->_content = $selected->getPackedContent();

        $this->locked = $this->page->get('locked');

        // If author same as previous author, default minor_edit to on.
        $age = $this->meta['mtime'] - $current->get('mtime');
        $this->meta['is_minor_edit'] = (
            $age < MINOR_EDIT_TIMEOUT
            && $current->get('author') == $user->getId()
        );

        $this->meta['pagetype'] = $selected->get('pagetype');
        if ($this->meta['pagetype'] == 'wikiblog') {
            $this->meta['summary'] = $selected->get('summary');
        } // keep blog title
        else {
            $this->meta['summary'] = '';
        }
        $this->editaction = 'edit';
    }
}

class LoadFileConflictPageEditor extends PageEditor
{
    public function editPage($saveFailed = true)
    {
        $tokens = &$this->tokens;

        if (!$this->canEdit()) {
            if ($this->isInitialEdit()) {
                return $this->viewSource();
            }
            $tokens['PAGE_LOCKED_MESSAGE'] = $this->getLockedMessage();
        } elseif ($this->editaction == 'save') {
            if ($this->savePage()) {
                return true; // Page saved.
            }
            $saveFailed = true;
        }

        if ($saveFailed || $this->isConcurrentUpdate()) {
            // Get the text of the original page, and the two conflicting edits
            // The diff class takes arrays as input.  So retrieve content as
            // an array, or convert it as necesary.
            $orig = $this->page->getRevision($this->_currentVersion);
            $this_content = explode("\n", $this->_content);
            $other_content = $this->current->getContent();
            require_once 'lib/diff.php';
            $diff2 = new Diff($other_content, $this_content);
            $context_lines = max(
                4,
                count($other_content) + 1,
                count($this_content) + 1
            );
            $fmt = new BlockDiffFormatter($context_lines);

            $this->_content = $fmt->format($diff2);
            // FIXME: integrate this into class BlockDiffFormatter
            $this->_content = str_replace(
                ">>>>>>>\n<<<<<<<\n",
                "=======\n",
                $this->_content
            );
            $this->_content = str_replace(
                "<<<<<<<\n>>>>>>>\n",
                "=======\n",
                $this->_content
            );

            $this->_currentVersion = $this->current->getVersion();
            $this->version = $this->_currentVersion;
            $tokens['CONCURRENT_UPDATE_MESSAGE'] = $this->getConflictMessage();
        }

        if ($this->editaction == 'edit_convert') {
            $tokens['PREVIEW_CONTENT'] = $this->getConvertedPreview();
        }
        if ($this->editaction == 'preview') {
            $tokens['PREVIEW_CONTENT'] = $this->getPreview();
        } // FIXME: convert to _MESSAGE?

        // FIXME: NOT_CURRENT_MESSAGE?
        $tokens = array_merge($tokens, $this->getFormElements());
        // we need all GET params for loadfile overwrite
        if ($this->request->getArg('action') == 'loadfile') {
            $this->tokens['HIDDEN_INPUTS'] =
                HTML(
                    HiddenInputs(array('source' => $this->request->getArg('source'),
                        'merge' => 1)),
                    $this->tokens['HIDDEN_INPUTS']
                );
            // add two conflict resolution buttons before preview and save.
            $tokens['PREVIEW_B'] = HTML(
                Button(
                    'submit:edit[keep_old]',
                    _("Keep old"),
                    'wikiaction'
                ),
                $tokens['SEP'],
                Button(
                    'submit:edit[overwrite]',
                    _("Overwrite with new"),
                    'wikiaction'
                ),
                $tokens['SEP'],
                $tokens['PREVIEW_B']
            );
        }
        return $this->output('editpage', _("Merge and Edit: %s"));
    }

    public function output($template, $title_fs)
    {
        $selected = &$this->selected;
        $current = &$this->current;

        if ($selected && $selected->getVersion() != $current->getVersion()) {
            $pagelink = WikiLink($selected);
        } else {
            $pagelink = WikiLink($this->page);
        }

        $title = new FormattedText($title_fs, $pagelink);
        $this->tokens['HEADER'] = $title;
        //hack! there's no TITLE in editpage, but in the previous top template
        if (empty($this->tokens['PAGE_LOCKED_MESSAGE'])) {
            $this->tokens['PAGE_LOCKED_MESSAGE'] = HTML::h3($title);
        } else {
            $this->tokens['PAGE_LOCKED_MESSAGE'] = HTML(
                HTML::h3($title),
                $this->tokens['PAGE_LOCKED_MESSAGE']
            );
        }
        $template = Template($template, $this->tokens);

        //GeneratePage($template, $title, $rev);
        PrintXML($template);
        return true;
    }

    protected function getConflictMessage($unresolved = false)
    {
        return HTML(HTML::p(
            fmt(
            "Some of the changes could not automatically be combined.  Please look for sections beginning with “%s”, and ending with “%s”.  You will need to edit those sections by hand before you click Save.",
            "<<<<<<<",
            "======="
        ),
            HTML::p(_("Please check it through before saving."))
        ));
    }
}
