<?php
rcs_id('$Id: diff.php,v 1.24 2001-12-21 08:05:17 carstenklapp Exp $');
// diff.php
//
// PhpWiki diff output code.
//
// Copyright (C) 2000, 2001 Geoffrey T. Dairiki <dairiki@dairiki.org>
// You may copy this code freely under the conditions of the GPL.
//

require_once('lib/difflib.php');

/**
 * HTML unified diff formatter.
 *
 * This class formats a diff into a CSS-based
 * unified diff format.
 *
 * Within groups of changed lines, diffs are highlit
 * at the character-diff level.
 */
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
            if ($elem)
                $line = QElement($elem, $line);
                    
            echo Element('div', array('class' => $class),
                         Element('tt', array('class' => 'prefix'), $prefix)
                         . " $line&nbsp;") . "\n";
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

    function _pack($bits, $tag) {
        $packed = htmlspecialchars(implode("", $bits));
        return "<$tag>"
            . str_replace("\n", "<tt>&nbsp;</tt></$tag>\n<$tag>",
                          $packed)
            . "</$tag>";
    }

    function _split($lines) {
        preg_match_all('/ ( [^\S\n]+ | [[:alnum:]]+ | . ) (?: (?!< \n) [^\S\n])? /xs',
                       implode("\n", $lines),
                       $m);
        return array($m[0], $m[1]);
    }
    
    function _changed($orig, $final) {
        list ($orig_words, $orig_stripped) = $this->_split($orig);
        list ($final_words, $final_stripped) = $this->_split($final);
        
        // Compute character-wise diff in changed region.
        $diff = new MappedDiff($orig_words, $final_words,
                               $orig_stripped, $final_stripped);

        $orig = $final = '';
        foreach ($diff->edits as $edit) {
            if ($edit->type == 'copy') {
                $orig .= implode('', $edit->orig);
                $final .= implode('', $edit->final);
            }
            else {
                if ($edit->orig)
                    $orig .= $this->_pack($edit->orig, 'del');
                if ($edit->final)
                    $final .= $this->_pack($edit->final, 'ins');
            }
        }

        $this->_lines(explode("\n", $orig),  'original', '-');
        $this->_lines(explode("\n", $final), 'final', '+');
    }
}

/**
 * HTML table-based unified diff formatter.
 *
 * This class formats a diff into a table-based
 * unified diff format.  (Similar to what was produced
 * by previous versions of PhpWiki.)
 *
 * Within groups of changed lines, diffs are highlit
 * at the character-diff level.
 */
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
                        sprintf(_("version %d"),$linked_version));

       $cols .= QElement('td',
                         sprintf(_("last modified on %s"),
                                 strftime($datetimeformat, $rev->get('mtime'))));
       $cols .= QElement('td',
                         sprintf(_("by %s"), $rev->get('author')));
   } else {
       $cols .= QElement('td', array('colspan' => '3'), _("None"));
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
        $new_version = sprintf(_("version %d"), $version);
    }
    else {
        $new = $page->getCurrentRevision();
        $new_version = _("current version");
    }

    if (preg_match('/^\d+$/', $previous)) {
        if ( !($old = $page->getRevision($previous)) )
            NoSuchRevision($page, $previous);
        $old_version = sprintf(_("version %d"), $previous);
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
            $old_version = _("previous major revision");
            $others = array('minor', 'author');
            break;
        case 'author':
            $old = $new;
            while ($old = $page->getRevisionBefore($old)) {
                if ($old->get('author') != $new->get('author'))
                    break;
            }
            $old_version = _("revision by previous author");
            $others = array('major', 'minor');
            break;
        case 'minor':
        default:
            $previous='minor';
            $old = $page->getRevisionBefore($new);
            $old_version = _("previous revision");
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
                    __sprintf("Differences between %s and %s of %s.",
                              $new_link, $old_link, $page_link));

    $otherdiffs='';
    $label = array('major' => _("Previous Major Revision"),
                   'minor' => _("Previous Revision"),
                   'author'=> _("Previous Author"));
    foreach ($others as $other) {
        $args = array('action' => 'diff', 'previous' => $other);
        if ($version)
            $args['version'] = $version;
        $otherdiffs .= ', ' . QElement('a', array('href' => WikiURL($pagename, $args),
                                                 'class' => 'wikiaction'),
                                      $label[$other]);
    }
    $html .= Element('p',
                     htmlspecialchars(_("Other diffs:"))
                     . $otherdiffs . '.');
            
            
    if ($old and $old->getVersion() == 0)
        $old = false;
    
    $html .= Element('table',
                    PageInfoRow($pagename, _("Newer page:"), $new)
                    . PageInfoRow($pagename, _("Older page:"), $old));


    if ($new && $old) {
        $diff = new Diff($old->getContent(), $new->getContent());
        
        if ($diff->isEmpty()) {
            $html .= Element('hr');
            $html .= QElement('p', '[' . _("Versions are identical") . ']');
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
                      sprintf(_("Diff: %s"), $pagename));
}
  
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
