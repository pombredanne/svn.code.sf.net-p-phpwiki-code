<?php // -*-php-*-
rcs_id('$Id: _BackendInfo.php,v 1.2 2001-12-15 10:54:31 carstenklapp Exp $');
require_once('lib/Template.php');
/**
 */
class WikiPlugin__BackendInfo
extends WikiPlugin
{
    var $name = '_BackendInfo';
    var $description = "Get debugging information for [pagename].";
    
    function WikiPlugin__BackendInfo() {
        $this->_hashtemplate = new Template('
<tr bgcolor="#ffcccc">
  <td colspan="2">${header}</td>
</tr>
<?php foreach ($hash as $key => $val) { ?>
  <tr>
    <td align="right" bgcolor="#cccccc">&nbsp;<?php echo $key;?>&nbsp;</td>
    <td><?php echo $val;?></td>
  </tr>
<?php } ?>
');
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
                         "Querying backend directly for '$page'");

        
        $rows = '';
        $pagedata = $backend->get_pagedata($page);
        if (!$pagedata)
            $html .= QElement('p', "No pagedata for $page\n");
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
