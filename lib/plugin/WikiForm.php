<?php // -*-php-*-
rcs_id('$Id: WikiForm.php,v 1.1 2002-02-07 21:17:26 dairiki Exp $');
/**
 * This is a replacement for MagicPhpWikiURL forms.
 *
 * 
 */
class WikiPlugin_WikiForm
extends WikiPlugin
{
    function getName () {
        return _("WikiForm");
    }

    function getDefaultArguments() {
        return array('action' => 'upload', // 'upload', 'loadfile', or 'dumpserial'
                     'default' => false,
                     'buttontext' => false);
    }

                                 
    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $form = HTML::form(array('action' => USE_PATH_INFO ? WikiURL($request->getPage()) : SCRIPT_NAME,
                                 'method' => 'post',
                                 'class'  => 'wikiadmin',
                                 'accept-charset' => CHARSET),
                           HTML::input(array('type' => 'hidden',
                                             'name' => 'action',
                                             'value' => $action)));

        $input = array('type' => 'text',
                       'value' => $default,
                       'size' => 50);

        switch ($action) {
        case 'loadfile':
            $input['name'] = 'source';
            if (!$default)
                $input['value'] = '/tmp/wikidump';
            if (!$buttontext)
                $buttontext = _("Load File");
            break;
        case 'dumpserial':
            $input['name'] = 'directory';
            if (!$default)
                $input['value'] = '/tmp/wikidump';
            if (!$buttontext)
                $buttontext = _("Dump Pages");
            break;
        case 'upload':
            $form->setAttr('enctype', 'multipart/form-data');
            $form->pushContent(HTML::input(array('name' => 'MAX_FILE_SIZE',
                                                 'value' =>  MAX_UPLOAD_SIZE,
                                                 'type'  => 'hidden')));
            $input['name'] = 'file';
            $input['type'] = 'file';
            if (!$buttontext)
                $buttontext = _("Upload");
            break;
        default:
            return HTML::p(fmt("WikiForm: %s: unknown action", $action));
        }

        
        $input = HTML::input($input);
        $input->addTooltip($buttontext);
        $button = Button('submit:', $buttontext);

        $form->pushContent(HTML::table(array('cellspacing' => 0,
                                             'cellpadding' => 2,
                                             'border' => 0),
                                       HTML::tr(HTML::td($input),
                                                HTML::td($button))));

        return $form;
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
