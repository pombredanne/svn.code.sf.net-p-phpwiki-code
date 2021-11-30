<?php
/*
 * Copyright © 2005,2007 $ThePhpWikiProgrammingTeam
 * Copyright © 2008-2009 Marc-Etienne Vargenau, Alcatel-Lucent
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
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

/**
 * This plugin displays the date, size, path, etc. of an uploaded file.
 * Only files relative and below to the uploads path can be handled.
 *
 * Usage:
 *   <<FileInfo file=Upload:image.png display=size,date >>
 *   <<FileInfo file=Upload:image.png display=name,size,date
 *                     format="%s (size: %s, date: %s)" >>
 *
 * @author: Reini Urban
 */

// posix_getpwuid() and posix_getgrgid() are not impremented in CentOS 7
// you need to do:
// yum install php-process
if (!function_exists('posix_getpwuid')) {
    function posix_getpwuid()
    {
        return false;
    }
}

if (!function_exists('posix_getgrgid')) {
    function posix_getgrgid()
    {
        return false;
    }
}

class WikiPlugin_FileInfo
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Display file information like size, date... of uploaded files.");
    }

    function getDefaultArguments()
    {
        return array(
            'file' => false, // relative path from PHPWIKI_DIR. (required)
            'display' => false, // size,phonysize,date,mtime,owner,group,name,path,dirname,mime,link (required)
            'format' => false, // printf format string with %s only, all display modes
            'quiet' => false // print no error if file not found
            // from above vars return strings (optional)
        );
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    function run($dbi, $argstr, &$request, $basepage)
    {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (!$file) {
            return $this->error(sprintf(_("A required argument “%s” is missing."), 'file'));
        }
        if (!$display) {
            return $this->error(sprintf(_("A required argument “%s” is missing."), 'display'));
        }
        if (string_starts_with($file, "Upload:")) {
            $file = preg_replace("/^Upload:(.*)$/", getUploadFilePath() . "\\1", $file);
            $is_Upload = 1;
        }
        $dir = getcwd();
        if (defined('PHPWIKI_DIR')) {
            chdir(PHPWIKI_DIR);
        }
        if (!file_exists($file)) {
            if ($quiet) {
                return HTML::raw('');
            } else {
                return $this->error(sprintf(_("File “%s” not found."), $file));
            }
        }
        // sanify $file name
        $realfile = realpath($file);
        // Hmm, allow ADMIN to check a local file? Only if its locked
        if (string_starts_with($realfile, realpath(getUploadDataPath()))) {
            $isuploaded = 1;
        } else {
            $page = $dbi->getPage($basepage);
            $user = $request->getUser();
            if ($page->getOwner() != ADMIN_USER or !$page->get('locked')) {
                // For convenience we warn the admin
                if ($quiet and $user->isAdmin())
                    return HTML::span(array('title' => _("Output suppressed. FileInfoPlugin with local files require a locked page.")),
                        HTML::em(_("page not locked")));
                else
                    return $this->error("Invalid path \"$file\". Only ADMIN can allow local paths, and the page must be locked.");
            }
        }
        $s = array();
        $modes = explode(",", $display);
        foreach ($modes as $mode) {
            switch ($mode) {
                case 'size':
                    $s[] = filesize($file);
                    break;
                case 'phonysize':
                    $s[] = $this->phonysize(filesize($file));
                    break;
                case 'date':
                    $s[] = strftime("%Y-%m-%d %T", filemtime($file));
                    break;
                case 'mtime':
                    $s[] = filemtime($file);
                    break;
                case 'owner':
                    $o = posix_getpwuid(fileowner($file));
                    if ($o === false) {
                        $s[] = 'not implemented';
                    } else {
                        $s[] = $o['name'];
                    }
                    break;
                case 'group':
                    $o = posix_getgrgid(filegroup($file));
                    if ($o === false) {
                        $s[] = 'not implemented';
                    } else {
                        $s[] = $o['name'];
                    }
                    break;
                case 'name':
                    $s[] = basename($file);
                    break;
                case 'path':
                    $s[] = $file;
                    break;
                case 'dirname':
                    $s[] = dirname($file);
                    break;
                case 'mime':
                    $s[] = $this->mime($file);
                    break;
                case 'link':
                    if ($is_Upload) {
                        $s[] = " [" . $args['file'] . "]";
                    } elseif ($isuploaded) {
                        // will fail with user uploads
                        $s[] = " [Upload:" . basename($file) . "]";
                    } else {
                        $s[] = " [" . basename($file) . "] ";
                    }
                    break;
                default:
                    if (!$quiet) {
                        return $this->error(sprintf(_("Unsupported argument: %s=%s"), 'display', $mode));
                    } else {
                        return HTML::raw('');
                    }
            }
        }
        chdir($dir);
        if (!$format) {
            $format = '';
            foreach ($s as $x) {
                $format .= " %s";
            }
        }
        array_unshift($s, $format);
        // $x, array($i,$j) => sprintf($x, $i, $j)
        $result = call_user_func_array("sprintf", $s);
        if (in_array('link', $modes)) {
            require_once 'lib/InlineParser.php';
            return TransformInline($result, $basepage);
        } else {
            return HTML::raw($result);
        }
    }

    private function mime($file)
    {
        // mime type and mime encoding as defined by RFC 2045
        $f = finfo_open(FILEINFO_MIME);
        $result = finfo_file($f, realpath($file));
        finfo_close($f);
        return $result;
    }

    private function formatsize($n, $factor, $suffix)
    {
        if ($n > $factor) {
            $b = $n / $factor;
            $n -= floor($factor * $b);
            return number_format($b, $n ? 3 : 0) . ' ' . $suffix;
        }
        return '';
    }

    private function phonysize($a)
    {
        $factor = 1024 * 1024 * 1024;
        if ($a > $factor)
            return $this->formatsize($a, $factor, _('GiB'));
        $factor = 1024 * 1024;
        if ($a > $factor)
            return $this->formatsize($a, $factor, _('MiB'));
        $factor = 1024;
        if ($a > $factor)
            return $this->formatsize($a, $factor, _('KiB'));
        if ($a > 1)
            return $this->formatsize($a, 1, _('bytes'));
        else
            return $a;
    }
}
