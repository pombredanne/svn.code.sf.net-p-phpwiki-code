<?php
rcs_id('$Id: removepage.php,v 1.1 2001-11-08 16:02:40 dairiki Exp $');

if ($request->getArg('verify') != 'okay') {
    $html = sprintf(gettext ("You are about to remove '%s' permanently!"),
                    htmlspecialchars($pagename));
    $html .= "\n<P>";
    $html .= sprintf(gettext ("Click <a href=\"%s\">here</a> to remove the page now."),
                     htmlspecialchars(WikiURL($pagename, array('action' => 'remove',
                                                               'verify' => 'okay'))));
    $html .= "\n<P>";
    $html .= gettext ("Otherwise press the \"Back\" button of your browser.");
}
else {
    $dbi->deletePage($pagename);
    $html = sprintf(gettext ("Removed page '%s' succesfully."),
                    htmlspecialchars($pagename));
}
require_once('lib/Template.php');
echo GeneratePage('MESSAGE', $html, gettext("Remove page"));

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>   
