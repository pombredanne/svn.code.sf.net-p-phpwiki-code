<?php // -*-php-*-
rcs_id('$Id: _BackendInfo.php,v 1.9 2002-01-21 06:55:47 dairiki Exp $');
require_once('lib/Template.php');
/**
 */
class WikiPlugin__BackendInfo
extends WikiPlugin
{
    function getName () {
        return _("DebugInfo");
    }

    function getDescription () {
        return sprintf(_("Get debugging information for %s."),'[pagename]');
    }
    
    function WikiPlugin__BackendInfo() {
        $html = '<tr bgcolor="#ffcccc">'."\n";
        $html .= '  <td colspan="2"><?=$header?></td>'."\n";
        $html .= '</tr>'."\n";
        $html .= '<?php foreach ($hash as $key => $val) { ?>'."\n";
        $html .= '  <tr>'."\n";
        $html .= '    <td align="right" bgcolor="#cccccc">&nbsp;<?=$key?>&nbsp;</td>'."\n";
        $html .= '    <td><?=$val?>&nbsp;</td>'."\n";
        $html .= '  </tr>'."\n";
        $html .= '<?php } ?>'."\n";
        $this->_hashtemplate = new Template($html);

    }
    
    function getDefaultArguments() {
        return array('page'	=> false);
        
    }
    
    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            return '';
        
        $backend = &$dbi->_backend;

        $html = QElement('h3',
                         sprintf(_("Querying backend directly for '%s'"), $page));

        
        $rows = '';
        $pagedata = $backend->get_pagedata($page);
        if (!$pagedata)
            $html .= QElement('p', sprintf(_("No pagedata for %s"), $page) . "\n");
        else {
            ksort($pagedata);
            $rows .= $this->_hashtemplate->
                getExpansion(array('header' => "get_pagedata('$page')",
                                   'hash'   => $pagedata));
        }
        
        for ($version = $backend->get_latest_version($page);
             $version;
             $version = $backend->get_previous_version($page, $version)) {

            $vdata = $backend->get_versiondata($page, $version, true);

            $content = &$vdata['%content'];
            if ($content === true)
                $content = '<true>';
            elseif (strlen($content) > 40)
                $content = substr($content,0,40) . " ...";

            $rows .= Element('tr', Element('td', array('colspan' => 2))) . "\n";
            ksort($vdata);
            $rows .= $this->_hashtemplate->
                getExpansion(array('header' => "get_versiondata('$page',$version)",
                                   'hash'   => $vdata));
            
        }

        $html .= Element('table', array('border' => 1,
                                        'cellpadding' => 2,
                                        'cellspacing' => 0),
                         $rows) . "\n";
        return new RawXml($html); // FIXME: avoid RawXml.
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
