<?php
rcs_id('$Id: pageinfo.php,v 1.11 2001-09-18 19:16:23 dairiki Exp $');
require_once('lib/Template.php');

global $datetimeformat;

// Display the internal structure of a page.
$pagename = $request->getArg('pagename');
$page = $dbi->getPage($pagename);

$rows[] = Element('tr',
                  "\n"
                  . Element('th', 'Version') . "\n"
                  . Element('th', 'Newer') . "\n"
                  . Element('th', 'Older') . "\n"
                  . Element('th', 'Created') . "\n"
                  . Element('th', 'Summary') . "\n"
                  . Element('th', 'Author') . "\n"
                  );

// Get all versions of a page, then iterate over them to make version list
$iter = $page->getAllRevisions();
$i = 0;
$last_author_id = false;

function bold_if($cond, $text) {
    return (bool)$cond ? QElement('b', $text) : htmlspecialchars($text);
}


while ($rev = $iter->next()) {
    $version = $rev->getVersion();
    $cols = array();
    $is_major_edit = ! $rev->get('is_minor_edit');
    
    $cols[] = Element('td', array('align' => 'right'),
                      Element('a', array('href'
                                          => WikiURL($pagename,
                                                     array('version' => $version))),
                              bold_if($is_major_edit, $version)));
    

    $cols[] = Element('td', array('align' => 'center'),
                      QElement('input', array('type' => 'radio',
                                              'name' => 'version',
                                              'value' => $version,
                                              'checked' => $i == 0)));
    
    $cols[] = Element('td', array('align' => 'center'),
                      QElement('input', array('type' => 'radio',
                                              'name' => 'previous',
                                              'value' => $version,
                                              'checked' => $i++ == 1)));
    
    $cols[] = QElement('td', array('align' => 'right'),
                       strftime($datetimeformat, $rev->get('mtime'))
                       . "\xa0");

    
    $cols[] = Element('td', bold_if($is_major_edit, $rev->get('summary')));
    
    $author_id = $rev->get('author_id');
    $cols[] = Element('td', bold_if($author_id !== $last_author_id,
                                    $rev->get('author')));
    $last_author_id = $author_id;
    $rows[] = Element('tr', "\n" . join("\n", $cols) . "\n");
}

$table = ("\n"
         . Element('table', join("\n", $rows)) . "\n"
         . Element('input', array('type' => 'hidden',
                                  'name' => 'action',
                                  'value' => 'diff')) . "\n"
         . Element('input', array('type' => 'submit', 'value' => 'Run Diff')) . "\n");

$formargs['action'] = USE_PATH_INFO ? WikiURL($pagename) : SCRIPT_NAME;
$formargs['method'] = 'post';

$html = Element('p',
                htmlspecialchars(gettext("Currently archived versions of"))
                . " "
                . LinkExistingWikiWord($pagename));
$html .= Element('form', $formargs, $table);

echo GeneratePage('MESSAGE', $html, gettext("Revision History: ") . $pagename);


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
