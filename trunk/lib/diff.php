<?php
rcs_id('$Id: diff.php,v 1.18 2001-12-13 18:29:24 dairiki Exp $');
// diff.php
//
// PhpWiki diff output code.
//
// Copyright (C) 2000, 2001 Geoffrey T. Dairiki <dairiki@dairiki.org>
// You may copy this code freely under the conditions of the GPL.
//

require_once('lib/difflib.php');

class HtmlUnifiedDiffFormatter extends UnifiedDiffFormatter
{
    function HtmlUnifiedDiffFormatter($context_lines = 4) {
        $this->UnifiedDiffFormatter($context_lines);
    }

    function _start_diff() {
        echo "<div class='diff'>\n";
    }
    function _end_diff() {
        echo "</div>\n";
    }
    
    function _start_block($header) {
        echo "<div class='block'>\n";
        echo QElement('tt', $header);
    }

    function _end_block() {
        echo "</div>\n";
    }

    function _lines($lines, $class, $prefix = '&nbsp;', $elem = false) {
        foreach ($lines as $line) {
            if (!trim($line))
                $line = '&nbsp;';
            elseif ($elem)
                $line = QElement($elem, $line);
                    
            echo Element('div', array('class' => $class),
                         Element('tt', array('class' => 'prefix'), $prefix)
                         . " $line") . "\n";
        }
    }

    function _context($lines) {
        $this->_lines($lines, 'context');
    }
    function _deleted($lines) {
        $this->_lines($lines, 'deleted', '-', 'del');
    }
    function _added($lines) {
        $this->_lines($lines, 'added', '+', 'ins');
    }

    function _pack_in_span($chars, $elem) {
        $end_span = "";
        $packed = '';
        foreach ($chars as $c) {
            if ($c == "\n") {
                $packed .= $end_span;
                $end_span = "";
            }
            elseif (!$end_span) {
                $packed .= "<$elem>";
                $end_span = "</$elem>";
            }
            $packed .= htmlspecialchars($c);
        }
        return $packed . $end_span;
    }
        
    function _split_to_chars($lines) {
        // Split into characters --- there must be a better way ...
        $joined = implode("\n", $lines);
        $split = array();
        for ($i = 0; $i < strlen($joined); $i++)
            $split[$i] = $joined[$i];
        return $split;
    }
    
            
    function _changed($orig, $final) {
        // Compute character-wise diff in changed region.
        $diff = new Diff($this->_split_to_chars($orig),
                         $this->_split_to_chars($final));
        
        $orig = $final = '';
        foreach ($diff->edits as $edit) {
            switch ($edit->type) {
            case 'copy':
                $packed = implode('', $edit->orig);
                $orig .= $packed;
                $final .= $packed;
                break;
            case 'add':
                $final .= $this->_pack_in_span($edit->final, 'ins');
                break;
            case 'delete':
                $orig .= $this->_pack_in_span($edit->orig, 'del');
                break;
            case 'change':
                $orig .= $this->_pack_in_span($edit->orig, 'del');
                $final .= $this->_pack_in_span($edit->final, 'ins');
                break;
            }
        }

        $this->_lines(explode("\n", $orig),  'changed', '-');
        $this->_lines(explode("\n", $final), 'changed', '+');
    }
}

class TableUnifiedDiffFormatter extends HtmlUnifiedDiffFormatter
{
    function TableUnifiedDiffFormatter($context_lines = 4) {
        $this->HtmlUnifiedDiffFormatter($context_lines);
    }

    function _start_diff() {
        echo "\n<table width='100%' class='diff'";
        echo " cellspacing='1' cellpadding='1' border='1'>\n";
    }
    function _end_diff() {
        echo "</table>\n";
    }
    
    function _start_block($header) {
        echo "<tr><td><table width='100%' class='block'";
        echo " cellspacing='0' cellpadding='1' border='0'>\n";
	echo Element('tr',
                     Element('td', array('colspan' => 2),
                             QElement('tt', $header))) . "\n";
    }

    function _end_block() {
        echo "</table></td></tr>\n";
    }

    function _lines($lines, $class, $prefix = '&nbsp;', $elem = false) {
        $prefix = Element('td', array('class' => 'prefix', 'width' => "1%"), $prefix);
        foreach ($lines as $line) {
            if (! trim($line))
                $line = '&nbsp';
            elseif ($elem)
                $line = QElement($elem, $line);

	    echo Element('tr', array('valign' => 'top'), 
                         $prefix . Element('td', array('class' => $class),
                                           $line)) . "\n";
        }
    }
}

    
/////////////////////////////////////////////////////////////////

function PageInfoRow ($pagename, $label, $rev)
{
   global $datetimeformat;
   
   $cols = QElement('td', array('align' => 'right'), $label);
   
   if ($rev) {
       $url = WikiURL($pagename, array('version' => $rev->getVersion()));
       $linked_version = QElement('a', array('href' => $url), $rev->getVersion());
       $cols .= Element('td',
                        gettext("version") . " " . $linked_version);

       $cols .= QElement('td',
                         sprintf(gettext ("last modified on %s"),
                                 strftime($datetimeformat, $rev->get('mtime'))));
       $cols .= QElement('td',
                         sprintf(gettext ("by %s"), $rev->get('author')));
   } else {
       $cols .= QElement('td', array('colspan' => '3'),
                         gettext ("None"));
   }
   return Element('tr', $cols);
}

function showDiff ($dbi, $request) {
    $pagename = $request->getArg('pagename');
    if (is_array($versions = $request->getArg('versions'))) {
        // Version selection from pageinfo.php display:
        rsort($versions);
        list ($version, $previous) = $versions;
    }
    else {
        $version = $request->getArg('version');
        $previous = $request->getArg('previous');
    }
    
    $page = $dbi->getPage($pagename);
    if ($version) {
        if (!($new = $page->getRevision($version)))
            NoSuchRevision($page, $version);
        $new_version = sprintf(gettext("version %d"), $version);
    }
    else {
        $new = $page->getCurrentRevision();
        $new_version = gettext('current version');
    }

    if (preg_match('/^\d+$/', $previous)) {
        if ( !($old = $page->getRevision($previous)) )
            NoSuchRevision($page, $previous);
        $old_version = sprintf(gettext("version %d"), $previous);
        $others = array('major', 'minor', 'author');
    }
    else {
        switch ($previous) {
        case 'major':
            $old = $new;
            while ($old = $page->getRevisionBefore($old)) {
                if (! $old->get('is_minor_edit'))
                    break;
            }
            $old_version = gettext("previous major revision");
            $others = array('minor', 'author');
            break;
        case 'author':
            $old = $new;
            while ($old = $page->getRevisionBefore($old)) {
                if ($old->get('author') != $new->get('author'))
                    break;
            }
            $old_version = gettext("revision by previous author");
            $others = array('major', 'minor');
            break;
        case 'minor':
        default:
            $previous='minor';
            $old = $page->getRevisionBefore($new);
            $old_version = gettext("previous revision");
            $others = array('major', 'author');
            break;
        }
    }

    $new_url = WikiURL($pagename, array('version' => $new->getVersion()));
    $new_link = QElement('a', array('href' => $new_url), $new_version);
    $old_url = WikiURL($pagename, array('version' => $old ? $old->getVersion() : 0));
    $old_link = QElement('a', array('href' => $old_url), $old_version);
    $page_link = LinkExistingWikiWord($pagename);
    
    $html = Element('p',
                    sprintf(htmlspecialchars(gettext("Differences between %s and %s of %s.")),
                            $new_link, $old_link, $page_link));

    $otherdiffs='';
    $label = array('major' => gettext("Previous Major Revision"),
                   'minor' => gettext("Previous Revision"),
                   'author'=> gettext("Previous Author"));
    foreach ($others as $other) {
        $args = array('action' => 'diff', 'previous' => $other);
        if ($version)
            $args['version'] = $version;
        $otherdiffs .= ' ' . QElement('a', array('href' => WikiURL($pagename, $args),
                                                 'class' => 'wikiaction'),
                                      $label[$other]);
    }
    $html .= Element('p',
                     htmlspecialchars(gettext("Other diffs:"))
                     . $otherdiffs);
            
            
    if ($old and $old->getVersion() == 0)
        $old = false;
    
    $html .= Element('table',
                    PageInfoRow($pagename, gettext ("Newer page:"), $new)
                    . PageInfoRow($pagename, gettext ("Older page:"), $old));


    if ($new && $old) {
        $diff = new Diff($old->getContent(), $new->getContent());
        
        if ($diff->isEmpty()) {
            $html .= Element('hr');
            $html .= QElement('p', '[' . gettext ("Versions are identical") . ']');
        }
        else {
            // New CSS formatted unified diffs (ugly in NS4).
            $fmt = new HtmlUnifiedDiffFormatter;

            // Use this for old table-formatted diffs.
            //$fmt = new TableUnifiedDiffFormatter;
            $html .= $fmt->format($diff);
        }
    }
    
    include_once('lib/Template.php');
    echo GeneratePage('MESSAGE', $html,
                      sprintf(gettext ("Diff: %s"), $pagename));
}
  
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
