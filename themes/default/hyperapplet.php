<?php 
  /* Download hyperapplet.jar (or hyperwiki.jar) and GraphXML.dtd from 
   *   http://hypergraph.sourceforge.net/download.html
   *   and place it into your theme directory.
   * Include this php file and adjust the width/height.
   * Requires the actionpage "LinkDatabase"
  */
global $WikiTheme;
?>
<applet code="hypergraph.applications.hexplorer.HExplorerApplet" align="baseline" 
        archive="<?= $WikiTheme->_finddata("hyperapplet.jar") ?>"
        width="160" height="360" >
  <param name="file" value="<?= WikiURL("LinkDatabase", array('format'=>'xml')) ?>" >
</applet >
<?php
 /* and via the RPC interface it would go like this...
<applet code="hypergraph.applications.hwiki.HWikiApplet.class" 
        archive="<?= $WikiTheme->_finddata("hyperwiki.jar");?>" 
        width="100%" height="500">">
  <param name="startPage" value="HomePage" />
  <param name="wikiurl" value="<?= DATA_PATH . "/RPC2.php" ?>" />
</applet>
 */
?>