<?php
rcs_id('$Id: loadsave.php,v 1.25 2002-01-10 23:55:52 carstenklapp Exp $');
require_once("lib/ziplib.php");
require_once("lib/Template.php");

function StartLoadDump($title, $html = '')
{
    // FIXME: This is a hack
    echo ereg_replace('</body>.*', '',
                      GeneratePage('MESSAGE', $html, $title, 0));
}

function EndLoadDump()
{
    // FIXME: This is a hack
    
    echo Element('p', QElement('strong', _("Complete.")));
    echo Element('p', sprintf( _("Return to %s"), 
                               LinkExistingWikiWord($GLOBALS['pagename']) ) );
    echo "</body></html>\n";
}


////////////////////////////////////////////////////////////////
//
//  Functions for dumping.
//
////////////////////////////////////////////////////////////////

function MailifyPage ($page, $nversions = 1)
{
    global $SERVER_ADMIN, $pagedump_format;
    
    $current = $page->getCurrentRevision();
    $from = isset($SERVER_ADMIN) ? $SERVER_ADMIN : 'foo@bar';
    $head = "";
    if ($pagedump_format == 'quoted-printable') {
        $head = "From $from  " . CTime(time()) . "\r\n";
        $head .= "Subject: " . rawurlencode($page->getName()) . "\r\n";
        $head .= "From: $from (PhpWiki)\r\n";
        $head .= "Date: " . Rfc2822DateTime($current->get('mtime')) . "\r\n";
        $head .= sprintf("Mime-Version: 1.0 (Produced by PhpWiki %s)\r\n",
                         PHPWIKI_VERSION);
    } else {
        $head .= sprintf("Mime-Version: 1.0 (Produced by PhpWiki %s)\r\n",
                         PHPWIKI_VERSION."+carsten's-binary-hack");
    }
    $head .= "X-RCS_ID: $" ."Id" ."$" ."\r\n";
    
    $iter = $page->getAllRevisions();
    $parts = array();
    while ($revision = $iter->next()) {
        $parts[] = MimeifyPageRevision($revision);
        if ($nversions > 0 && count($parts) >= $nversions)
            break;
    }
    if (count($parts) > 1)
        return $head . MimeMultipart($parts);
    assert($parts);
    return $head . $parts[0];
}

/***
 * Compute filename to used for storing contents of a wiki page.
 *
 * Basically we do a rawurlencode() which encodes everything except
 * ASCII alphanumerics and '.', '-', and '_'.
 *
 * But we also want to encode leading dots to avoid filenames like
 * '.', and '..'. (Also, there's no point in generating "hidden" file
 * names, like '.foo'.)
 *
 * @param $pagename string Pagename.
 * @return string Filename for page.
 */
function FilenameForPage ($pagename)
{
    $enc = rawurlencode($pagename);
    return preg_replace('/^\./', '%2e', $enc);
}

/**
 * The main() function which generates a zip archive of a PhpWiki.
 *
 * If $include_archive is false, only the current version of each page
 * is included in the zip file; otherwise all archived versions are
 * included as well.
 */
function MakeWikiZip ($dbi, $request)
{
    if ($request->getArg('include') == 'all') {
        $zipname         = "wikidb.zip";
        $include_archive = true;
    }
    else {
        $zipname         = "wiki.zip";
        $include_archive = false;
    }
    
    

    $zip = new ZipWriter("Created by PhpWiki", $zipname);

    $pages = $dbi->getAllPages();
    while ($page = $pages->next()) {
        set_time_limit(30);	// Reset watchdog.
        
        $current = $page->getCurrentRevision();
        if ($current->getVersion() == 0)
            continue;
        
        
        $attrib = array('mtime'    => $current->get('mtime'),
                        'is_ascii' => 1);
        if ($page->get('locked'))
            $attrib['write_protected'] = 1;
        
        if ($include_archive)
            $content = MailifyPage($page, 0);
        else
            $content = MailifyPage($page);
        
        $zip->addRegularFile( FilenameForPage($page->getName()),
                              $content, $attrib);
    }
    $zip->finish();
}

function DumpToDir ($dbi, $request) 
{
    $directory = $request->getArg('directory');
    if (empty($directory))
        ExitWiki(_("You must specify a directory to dump to"));
    
    // see if we can access the directory the user wants us to use
    if (! file_exists($directory)) {
        if (! mkdir($directory, 0755))
            ExitWiki( sprintf(_("Cannot create directory '%s'"), 
                              $directory) . "<br />\n");
        else
            $html = sprintf(_("Created directory '%s' for the page dump..."),
                            $directory) . "<br />\n";
    } else {
        $html = sprintf(_("Using directory '%s'"),$directory) . "<br />\n";
    }
    
    StartLoadDump( _("Dumping Pages"), $html);
    
    $pages = $dbi->getAllPages();
    
    while ($page = $pages->next()) {
        
        $enc_name = htmlspecialchars($page->getName());
        $filename = FilenameForPage($page->getName());
        
        echo "<br />$enc_name ... ";
        if($page->getName() != $filename)
            echo "<small>" . sprintf(_("saved as %s"),$filename)
                . "</small> ... ";
        
        $data = MailifyPage($page);
        
        if ( !($fd = fopen("$directory/$filename", "w")) )
            ExitWiki("<strong>" . sprintf(_("couldn't open file '%s' for writing"),
                                     "$directory/$filename") . "</strong>\n");
        
        $num = fwrite($fd, $data, strlen($data));
        echo "<small>" . sprintf(_("%s bytes written"),$num) . "</small>\n";
        flush();
        
        assert($num == strlen($data));
        fclose($fd);
    }
    
    EndLoadDump();
}

////////////////////////////////////////////////////////////////
//
//  Functions for restoring.
//
////////////////////////////////////////////////////////////////

function SavePage ($dbi, $pageinfo, $source, $filename)
{
    $pagedata    = $pageinfo['pagedata'];    // Page level meta-data.
    $versiondata = $pageinfo['versiondata']; // Revision level meta-data.
    
    if (empty($pageinfo['pagename'])) {
        echo Element('dd'). Element('dt', QElement('strong',
                                                   _("Empty pagename!") ));
        return;
    }
    
    if (empty($versiondata['author_id']))
        $versiondata['author_id'] = $versiondata['author'];
    
    $pagename = $pageinfo['pagename'];
    $content  = $pageinfo['content'];
    
    $page = $dbi->getPage($pagename);
    
    foreach ($pagedata as $key => $value) {
        if (!empty($value))
            $page->set($key, $value);
    }
    
    $mesg = array();
    $skip = false;
    if ($source)
        $mesg[] = sprintf(_("from %s"), $source);

    $current = $page->getCurrentRevision();
    if ($current->getVersion() == 0) {
        $mesg[] = _("new page");
        $isnew = true;
    }
    else {
        if ($current->getPackedContent() == $content
            && $current->get('author') == $versiondata['author']) {
            $mesg[] = sprintf(_("is identical to current version %d"),
                              $current->getVersion());
            $mesg[] = _("- skipped");
            $skip = true;
        }
        $isnew = false;
    }
    
    if (! $skip) {
        $new = $page->createRevision(WIKIDB_FORCE_CREATE, $content,
                                     $versiondata,
                                     ExtractWikiPageLinks($content));
        
        $mesg[] = sprintf(_("- saved to database as version %d"),
                          $new->getVersion());
    }
    
    print( Element('dt', LinkExistingWikiWord($pagename))
           . QElement('dd', join(" ", $mesg))
           . "\n" );
    flush();
}

function ParseSerializedPage($text, $default_pagename)
{
    if (!preg_match('/^a:\d+:{[si]:\d+/', $text))
        return false;
    
    $pagehash = unserialize($text);
    
    // Split up pagehash into four parts:
    //   pagename
    //   content
    //   page-level meta-data
    //   revision-level meta-data
    
    if (!defined('FLAG_PAGE_LOCKED'))
        define('FLAG_PAGE_LOCKED', 1);
    $pageinfo = array('pagedata'    => array(),
                      'versiondata' => array());
    
    $pagedata = &$pageinfo['pagedata'];
    $versiondata = &$pageinfo['versiondata'];
    
    // Fill in defaults.
    if (empty($pagehash['pagename']))
        $pagehash['pagename'] = $default_pagename;
    if (empty($pagehash['author']))
        $pagehash['author'] = $GLOBALS['user']->id();
    
    
    foreach ($pagehash as $key => $value) {
        switch($key) {
        case 'pagename':
        case 'version':
            $pageinfo[$key] = $value;
            break;
        case 'content':
            $pageinfo[$key] = join("\n", $value);
        case 'flags':
            if (($value & FLAG_PAGE_LOCKED) != 0)
                $pagedata['locked'] = 'yes';
            break;
        case 'created':
            $pagedata[$key] = $value;
            break;
        case 'lastmodified':
            $versiondata['mtime'] = $value;
            break;
        case 'author':
            $versiondata[$key] = $value;
            break;
        }
    }
    return $pageinfo;
}
 
function SortByPageVersion ($a, $b) {
    return $a['version'] - $b['version'];
}

function LoadFile ($dbi, $filename, $text = false, $mtime = false)
{
    if (!is_string($text)) {
        // Read the file.
        $stat  = stat($filename);
        $mtime = $stat[9];
        $text  = implode("", file($filename));
    }
   
    set_time_limit(30);	// Reset watchdog.
    
    // FIXME: basename("filewithnoslashes") seems to return garbage sometimes.
    $basename = basename("/dummy/" . $filename);
   
    if (!$mtime)
        $mtime = time();	// Last resort.
    
    $defaults = array('author'   => $GLOBALS['user']->id(),
                      'pagename' => rawurldecode($basename));
    
    $default_pagename = rawurldecode($basename);
    
    if ( ($parts = ParseMimeifiedPages($text)) ) {
        usort($parts, 'SortByPageVersion');
        foreach ($parts as $pageinfo)
            SavePage($dbi, $pageinfo, sprintf(_("MIME file %s"),$filename),
                     $basename);
    }
    else if ( ($pageinfo = ParseSerializedPage($text, $default_pagename)) ) {
        SavePage($dbi, $pageinfo, sprintf(_("Serialized file %s"),$filename),
                 $basename);
    }
    else {
        // Assume plain text file.
        $pageinfo = array('pagename' => $default_pagename,
                          'pagedata' => array(),
                          'versiondata'
                          => array('author' => $GLOBALS['user']->id()),
                          'content'  => preg_replace('/[ \t\r]*\n/', "\n",
                                                     chop($text))
                          );
        SavePage($dbi, $pageinfo, sprintf(_("plain file %s"),$filename),
                 $basename);
    }
}

function LoadZip ($dbi, $zipfile, $files = false, $exclude = false)
{
   $zip = new ZipReader($zipfile);
   while (list ($fn, $data, $attrib) = $zip->readFile())
       {
           // FIXME: basename("filewithnoslashes") seems to return garbage sometimes.
           $fn = basename("/dummy/" . $fn);
           if ( ($files && !in_array($fn, $files))
                || ($exclude && in_array($fn, $exclude)) )
               {
                   print Element('dt', LinkExistingWikiWord($fn))
                       . QElement('dd', _("Skipping"));
                   continue;
               }
           
           LoadFile($dbi, $fn, $data, $attrib['mtime']);
   }
}

function LoadDir ($dbi, $dirname, $files = false, $exclude = false)
{
    $handle = opendir($dir = $dirname);
    while ($fn = readdir($handle))
        {
            if ($fn[0] == '.' || filetype("$dir/$fn") != 'file')
                continue;
            
            if ( ($files && !in_array($fn, $files))
                 || ($exclude && in_array($fn, $exclude)) )
                {
                    print Element('dt', LinkExistingWikiWord($fn))
                        . QElement('dd', _("Skipping"));
                    continue;
                }
            
            LoadFile($dbi, "$dir/$fn");
        }
    closedir($handle);
}

function IsZipFile ($filename_or_fd)
{
    // See if it looks like zip file
    if (is_string($filename_or_fd))
        {
            $fd    = fopen($filename_or_fd, "rb");
            $magic = fread($fd, 4);
            fclose($fd);
        }
    else
        {
            $fpos  = ftell($filename_or_fd);
            $magic = fread($filename_or_fd, 4);
            fseek($filename_or_fd, $fpos);
        }
    
    return $magic == ZIP_LOCHEAD_MAGIC || $magic == ZIP_CENTHEAD_MAGIC;
}


function LoadAny ($dbi, $file_or_dir, $files = false, $exclude = false)
{
    // FIXME: This is a partial workaround for sf bug #501145
    if (substr_count($file_or_dir,"/") < 1) {
        $type = filetype(rawurlencode($file_or_dir));
    } else {
        $type = filetype($file_or_dir);
    }
    
    if ($type == 'dir') {
        LoadDir($dbi, $file_or_dir, $files, $exclude);
    }
    else if ($type != 'file' && !preg_match('/^(http|ftp):/', $file_or_dir))
    {
        ExitWiki( sprintf(_("Bad file type: %s"),$type) );
    }
    else if (IsZipFile($file_or_dir)) {
        LoadZip($dbi, $file_or_dir, $files, $exclude);
    }
    else /* if (!$files || in_array(basename($file_or_dir), $files)) */
    {
        LoadFile($dbi, $file_or_dir);
    }
}

function LoadFileOrDir ($dbi, $request)
{
    $source = $request->getArg('source');
    StartLoadDump( sprintf(_("Loading '%s'"),$source) );
    echo "<dl>\n";
    LoadAny($dbi, $source/*, false, array(gettext("RecentChanges"))*/);
    echo "</dl>\n";
    EndLoadDump();
}

function SetupWiki ($dbi)
{
    global $GenericPages, $LANG, $user;
    
    //FIXME: This is a hack
    $user->userid = _("The PhpWiki programming team");
    
    StartLoadDump(_("Loading up virgin wiki"));
    echo "<dl>\n";
    
    LoadAny($dbi, FindLocalizedFile(WIKI_PGSRC)/*, false, $ignore*/);
    if ($LANG != "C")
        LoadAny($dbi, FindFile(DEFAULT_WIKI_PGSRC),
                $GenericPages/*, $ignore*/);
    
    echo "</dl>\n";
    EndLoadDump();
}

function LoadPostFile ($dbi, $request)
{
    $upload = $request->getUploadedFile('file');
    
    if (!$upload)
        // FIXME: better message?
        ExitWiki(_("No uploaded file to upload?"));
    
    // Dump http headers.
    StartLoadDump( sprintf(_("Uploading %s"),$upload->getName()) );
    echo "<dl>\n";
    
    $fd = $upload->open();
    if (IsZipFile($fd))
        LoadZip($dbi, $fd, false, array(_("RecentChanges")));
    else
        Loadfile($dbi, $upload->getName(), $upload->getContents());
    
    echo "</dl>\n";
    EndLoadDump();
}

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
