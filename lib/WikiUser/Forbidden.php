<?php //-*-php-*-
rcs_id('$Id: Forbidden.php,v 1.1 2004-11-05 18:11:38 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
 */

/** 
 * The PassUser name gets created automatically. 
 * That's why this class is empty, but must exist.
 */
class _ForbiddenPassUser
extends _ForbiddenUser
{
    function dummy() {
        return;
    }
}

// $Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>