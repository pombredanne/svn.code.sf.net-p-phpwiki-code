<?php
rcs_id('$Id: removepage.php,v 1.2 2001-12-01 13:06:13 carstenklapp Exp $');

if ($request->getArg('verify') != 'okay') {
    $html = sprintf(gettext ("You are about to remove '%s' permanently!"),
                    htmlspecialchars($pagename));
    $html .= "\n<P>";
    $html .= sprintf(gettext ("Click here to <a href=\"%s\">remove the page now</a>."),
                     htmlspecialchars(WikiURL($pagename, array('action' => 'remove',
                                                               'verify' => 'okay'))));
    $html .= "\n<P>";
    $html .= gettext ("To cancel press the \"Back\" button of your browser.");
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
