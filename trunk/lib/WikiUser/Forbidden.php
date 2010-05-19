<?php //-*-php-*-
// rcs_id('$Id$');
/* Copyright (C) 2004 ReiniUrban
 * This file is part of PhpWiki. Terms and Conditions see LICENSE. (GPL2)
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

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
