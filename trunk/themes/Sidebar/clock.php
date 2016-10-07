<?php
/**
 * Copyright © 2004 Reini Urban
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0+
 *
 */

$ora = isset($GLOBALS['WikiTheme']) ? DATA_PATH . '/' . $GLOBALS['WikiTheme']->_findFile("ora.swf") : "ora.swf";
?>
<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
        codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0" width="160"
        height="160" align="middle">
    <param name="movie" value="<?php echo $ora ?>">
    <param name="quality" value="high">
    <embed src="<?php echo $ora ?>" quality="high" type="application/x-shockwave-flash" width="160"
           height="160"></embed>
</object>
