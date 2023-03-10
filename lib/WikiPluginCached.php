<?php
/**
 * Copyright © 2002 Johannes Große
 * Copyright © 2004,2007 Reini Urban
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
 * You should set up the options in config/config.ini at Part seven.
 */

require_once 'lib/WikiPlugin.php';
require_once 'lib/pear/Cache.php';

// types:
define('PLUGIN_CACHED_HTML', 0); // cached html (extensive calculation)
define('PLUGIN_CACHED_IMG_INLINE', 1); // gd images
define('PLUGIN_CACHED_MAP', 2); // area maps
define('PLUGIN_CACHED_SVG', 3); // special SVG/SVGZ object
define('PLUGIN_CACHED_SVG_PNG', 4); // special SVG/SVGZ object with PNG fallback
define('PLUGIN_CACHED_PDF', 6); // special PDF object (inlinable?)
define('PLUGIN_CACHED_PS', 7); // special PS object (inlinable?)
// boolean tests:
define('PLUGIN_CACHED_IMG_ONDEMAND', 64); // don't cache
define('PLUGIN_CACHED_STATIC', 128); // make it available via /uploads/, not via /getimg.php?id=

/**
 * An extension of the WikiPlugin class to allow image output and
 * cacheing.
 * There are several abstract functions to be overloaded.
 * Have a look at the example files
 * <ul><li>plugin/TexToPng.php</li>
 *     <li>plugin/CacheTest.php (extremely simple example)</li>
 *     <li>plugin/VisualWiki.php</li>
 *     <li>plugin/Ploticus.php</li>
 * </ul>
 *
 * @author  Johannes Große, Reini Urban
 */
class WikiPluginCached extends WikiPlugin
{
    public $_static;

    /**
     * Produces URL and id number from plugin arguments which later on,
     * will allow to find a cached image or to reconstruct the complete
     * plugin call to recreate the image.
     *
     * @param object $cache   the cache object used to store the images
     * @param array $argarray all parameters (including those set to
     *                        default values) of the plugin call to be
     *                        prepared
     * @return array(id,url)
     *
     * TODO: check if args is needed at all (on lost cache)
     */
    private function genUrl($cache, $argarray)
    {
        global $request;

        $plugincall = serialize(array(
            'pluginname' => $this->getName(),
            'arguments' => $argarray));
        $id = $cache->generateId($plugincall);
        $plugincall_arg = rawurlencode($plugincall);
        //$plugincall_arg = md5($plugincall);
        // will not work if plugin has to recreate content and cache is lost

        $url = DATA_PATH . '/getimg.php?';
        if (($lastchar = substr($url, -1)) == '/') {
            $url = substr($url, 0, -1);
        }
        if (strlen($plugincall_arg) > PLUGIN_CACHED_MAXARGLEN) {
            // we can't send the data as URL so we just send the id
            if (!$request->getSessionVar('imagecache' . $id)) {
                $request->setSessionVar('imagecache' . $id, $plugincall);
            }
            $plugincall_arg = false; // not needed anymore
        }

        if ($lastchar == '?') {
            // this indicates that a direct call of the image creation
            // script is wished ($url is assumed to link to the script)
            $url .= "id=$id" . ($plugincall_arg ? '&args=' . $plugincall_arg : '');
        } else {
            // Not yet supported.
            // We are supposed to use the indirect 404 ErrorDocument method
            // ($url is assumed to be the url of the image in
            //  cache_dir and the image creation script is referred to in the
            //  ErrorDocument 404 directive.)
            $url .= '/' . PLUGIN_CACHED_FILENAME_PREFIX . $id . '.img'
                . ($plugincall_arg ? '?args=' . $plugincall_arg : '');
        }
        if ($request->getArg("start_debug") and (DEBUG & _DEBUG_REMOTE)) {
            $url .= "&start_debug=1";
        }
        return array($id, $url);
    } // genUrl

    /**
     * Replaces the abstract run method of WikiPlugin to implement
     * a cache check which can avoid redundant runs.
     * <b>Do not override this method in a subclass. Instead you may
     * rename your run method to getHtml, getImage or getMap.
     * Have a close look on the arguments and required return values,
     * however. </b>
     *
     * @param  WikiDB  $dbi      database abstraction class
     * @param  string  $argstr   plugin arguments in the call from PhpWiki
     * @param  WikiRequest $request
     * @param  string  $basepage Pagename to use to interpret links [/relative] page names.
     * @return string            HTML output to be printed to browser
     *
     * @see #getHtml
     * @see #getImage
     * @see #getMap
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        $cache = $this->newCache();

        $sortedargs = $this->getArgs($argstr, $request);
        if (is_array($sortedargs)) {
            ksort($sortedargs);
        }
        $this->_args =& $sortedargs;
        $this->_type = $this->getPluginType();
        $this->_static = false;
        if ($this->_type & PLUGIN_CACHED_STATIC
            or $request->getArg('action') == 'pdf'
        ) { // htmldoc doesn't grok subrequests
            $this->_type = $this->_type & ~PLUGIN_CACHED_STATIC;
            $this->_static = true;
        }

        // ---------- embed static image, no getimg.php? url -----------------
        if (0 and $this->_static) {
            //$content = $cache->get($id, 'imagecache');
            $content = array();
            if ($this->produceImage($content, $this, $dbi, $sortedargs, $request, 'html')) {
                // save the image in uploads
                return $this->embedImg($content['url'], $dbi, $sortedargs, $request);
            } else {
                // copy the cached image into uploads if older
                return HTML();
            }
        }

        list($id, $url) = $this->genUrl($cache, $sortedargs);
        // ---------- don't check cache: html and img gen. -----------------
        // override global PLUGIN_CACHED_USECACHE for a plugin
        if ($this->getPluginType() & PLUGIN_CACHED_IMG_ONDEMAND) {
            if ($this->_static and $this->produceImage($content, $this, $dbi, $sortedargs, $request, 'html')) {
                $url = $content['url'];
            }
            return $this->embedImg($url, $dbi, $sortedargs, $request);
        }

        $do_save = false;
        $content = $cache->get($id, 'imagecache');
        switch ($this->_type) {
            case PLUGIN_CACHED_HTML:
                if (!$content || !$content['html']) {
                    $this->resetError();
                    $content['html'] = $this->getHtml($dbi, $sortedargs, $request, $basepage);
                    if ($errortext = $this->getError()) {
                        $this->printError($errortext, 'html');
                        return HTML();
                    }
                    $do_save = true;
                }
                break;
            case PLUGIN_CACHED_IMG_INLINE:
                if (PLUGIN_CACHED_USECACHE && (!$content || !$content['image'])) { // new
                    $do_save = $this->produceImage($content, $this, $dbi, $sortedargs, $request, 'html');
                    if ($this->_static) {
                        $url = $content['url'];
                    }
                    $content['html'] = $do_save ? $this->embedImg($url, $dbi, $sortedargs, $request) : false;
                } elseif (!empty($content['url']) && $this->_static) { // already in cache
                    $content['html'] = $this->embedImg($content['url'], $dbi, $sortedargs, $request);
                } elseif (!empty($content['image']) && $this->_static) { // copy from cache to upload
                    $do_save = $this->produceImage($content, $this, $dbi, $sortedargs, $request, 'html');
                    $url = $content['url'];
                    $content['html'] = $do_save ? $this->embedImg($url, $dbi, $sortedargs, $request) : false;
                }
                break;
            case PLUGIN_CACHED_MAP:
                if (!$content || !$content['image'] || !$content['html']) {
                    $do_save = $this->produceImage($content, $this, $dbi, $sortedargs, $request, 'html');
                    if ($this->_static) {
                        $url = $content['url'];
                    }
                    $content['html'] = $do_save
                        ? $this->embedMap($id, $url, $content['html'], $dbi, $sortedargs, $request)
                        : false;
                }
                break;
            case PLUGIN_CACHED_SVG:
                if (!$content || !$content['html']) {
                    $do_save = $this->produceImage($content, $this, $dbi, $sortedargs, $request, 'html');
                    if ($this->_static) {
                        $url = $content['url'];
                    }
                    $args = array(); //width+height => object args
                    if (!empty($sortedargs['width'])) {
                        $args['width'] = $sortedargs['width'];
                    }
                    if (!empty($sortedargs['height'])) {
                        $args['height'] = $sortedargs['height'];
                    }
                    $content['html'] = $do_save
                        ? $this->embedObject(
                            $url,
                            'image/svg+xml',
                            $args,
                            HTML::embed(array_merge(
                                array('src' => $url, 'type' => 'image/svg+xml'),
                                $args
                            ))
                        )
                        : false;
                }
                break;
            case PLUGIN_CACHED_SVG_PNG:
                if (!$content || !$content['html']) {
                    $do_save_svg = $this->produceImage($content, $this, $dbi, $sortedargs, $request, 'html');
                    if ($this->_static) {
                        $url = $content['url'];
                    }
                    // hack alert! somehow we should know which argument will produce the secondary image (PNG)
                    $args = $sortedargs;
                    $args[$this->pngArg()] = $content['imagetype']; // default type: PNG or GIF
                    $do_save = $this->produceImage($pngcontent, $this, $dbi, $args, $request, $content['imagetype']);
                    $args = array(); //width+height => object args
                    if (!empty($sortedargs['width'])) {
                        $args['width'] = $sortedargs['width'];
                    }
                    if (!empty($sortedargs['height'])) {
                        $args['height'] = $sortedargs['height'];
                    }
                    $content['html'] = $do_save_svg
                        ? $this->embedObject(
                            $url,
                            'image/svg+xml',
                            $args,
                            $this->embedImg($pngcontent['url'], $dbi, $sortedargs, $request)
                        )
                        : false;
                }
                break;
        }
        if ($do_save) {
            $content['args'] = md5($this->_pi);
            $expire = $this->getExpire($dbi, $content['args'], $request);
            $cache->save($id, $content, $expire, 'imagecache');
        }
        if ($content['html']) {
            return $content['html'];
        }
        return HTML();
    } // run

    /* --------------------- abstract functions ----------- */

    protected function getDescription()
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    /**
     * Sets the type of the plugin to html, image or map
     * production
     *
     * @return int determines the plugin to produce either html,
     *             an image or an image map; uses on of the
     *             following predefined values
     *             <ul>
     *             <li>PLUGIN_CACHED_HTML</li>
     *             <li>PLUGIN_CACHED_IMG_INLINE</li>
     *             <li>PLUGIN_CACHED_IMG_ONDEMAND</li>
     *             <li>PLUGIN_CACHED_MAP</li>
     *             </ul>
     */

    protected function getPluginType()
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    /**
     * Creates an image handle from the given user arguments.
     * This method is only called if the return value of
     * <code>getPluginType</code> is set to
     * PLUGIN_CACHED_IMG_INLINE or PLUGIN_CACHED_IMG_ONDEMAND.
     *
     * @param  WikiDB $dbi             database abstraction class
     * @param  array $argarray         complete (!) arguments to produce
     *                                image. It is not necessary to call
     *                                WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @return mixed imagehandle image handle if successful
     *                                false if an error occured
     */
    protected function getImage($dbi, $argarray, $request)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    /**
     * Sets the life time of a cache entry in seconds.
     * Expired entries are not used anymore.
     * During a garbage collection each expired entry is
     * removed. If removing all expired entries is not
     * sufficient, the expire time is ignored and removing
     * is determined by the last "touch" of the entry.
     *
     * @param  WikiDB $dbi            database abstraction class
     * @param  array $argarray        complete (!) arguments. It is
     *                                not necessary to call
     *                                WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @return string format: '+seconds'
     *                                '0' never expires
     */
    protected function getExpire($dbi, $argarray, $request)
    {
        return '0'; // persist forever
    }

    /**
     * Decides the image type of an image output.
     * Always used unless plugin type is PLUGIN_CACHED_HTML.
     *
     * @param  WikiDB $dbi            database abstraction class
     * @param  array $argarray        complete (!) arguments. It is
     *                                not necessary to call
     *                                WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @return string 'png', 'jpeg' or 'gif'
     */
    protected function getImageType($dbi, $argarray, $request)
    {
        if (in_array($argarray['imgtype'], preg_split('/\s*:\s*/', PLUGIN_CACHED_IMGTYPES))) {
            return $argarray['imgtype'];
        } else {
            return 'png';
        }
    }

    /**
     * Produces the alt text for an image.
     * <code> &lt;img src=... alt="getAlt(...)"&gt; </code>
     *
     * @param  WikiDB $dbi            database abstraction class
     * @param  array $argarray        complete (!) arguments. It is
     *                                not necessary to call
     *                                WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @return string "alt" description of the image
     */
    protected function getAlt($dbi, $argarray, $request)
    {
        return '<?plugin ' . $this->getName() . ' ' . $this->glueArgs($argarray) . '?>';
    }

    /**
     * Creates HTML output to be cached.
     * This method is only called if the plugin_type is set to
     * PLUGIN_CACHED_HTML.
     *
     * @param  WikiDB $dbi            database abstraction class
     * @param  array $argarray        complete (!) arguments to produce
     *                                image. It is not necessary to call
     *                                WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @param  string $basepage Pagename to use to interpret links [/relative] page names.
     * @return string html to be printed in place of the plugin command
     *                                false if an error occured
     */
    protected function getHtml($dbi, $argarray, $request, $basepage)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    /**
     * Creates HTML output to be cached.
     * This method is only called if the plugin_type is set to
     * PLUGIN_CACHED_HTML.
     *
     * @param  WikiDB $dbi            database abstraction class
     * @param  array $argarray        complete (!) arguments to produce
     *                                image. It is not necessary to call
     *                                WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @return array(html,handle) html for the map interior (to be specific,
     *                                only &lt;area;&gt; tags defining hot spots)
     *                                handle is an imagehandle to the corresponding
     *                                image.
     *                                array(false,false) if an error occured
     */
    protected function getMap($dbi, $argarray, $request)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    /* --------------------- produce Html ----------------------------- */

    /**
     * Creates an HTML map hyperlinked to the image specified
     * by url and containing the hotspots given by map.
     *
     * @param  string $id       unique id for the plugin call
     * @param  string $url      url pointing to the image part of the map
     * @param  string $map      &lt;area&gt; tags defining active
     *                          regions in the map
     * @param  WikiDB $dbi      database abstraction class
     * @param  array $argarray  complete (!) arguments to produce
     *                          image. It is not necessary to call
     *                          WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @return string html output
     */
    protected function embedMap($id, $url, $map, &$dbi, $argarray, &$request)
    {
        // id is not unique if the same map is produced twice
        $key = substr($id, 0, 8) . substr(microtime(), 0, 6);
        return HTML(
            HTML::map(array('name' => $key), $map),
            HTML::img(array(
                'src' => $url,
                //  'alt'    => htmlspecialchars($this->getAlt($dbi,$argarray,$request))
                'usemap' => '#' . $key))
        );
    }

    /**
     * Which argument must be set to 'png', for the fallback image when svg
     * will fail on the client.
     * type: SVG_PNG
     *
     * @return string
     */
    protected function pngArg()
    {
        trigger_error('WikiPluginCached::pngArg: pure virtual function in file '
            . __FILE__ . ' line ' . __LINE__, E_USER_ERROR);
    }

    /**
     * Creates an HTML &lt;img&gt; tag hyperlinking to the specified
     * url and produces an alternative text for non-graphical
     * browsers.
     *
     * @param  string $url        url pointing to the image part of the map
     * @param  WikiDB $dbi        database abstraction class
     * @param  array $argarray    complete (!) arguments to produce
     *                            image. It is not necessary to call
     *                            WikiPlugin->getArgs anymore.
     * @param  Request $request
     * @return string html output
     */
    private function embedImg($url, $dbi, $argarray, $request)
    {
        return HTML::img(array(
            'src' => $url,
            'alt' => htmlspecialchars($this->getAlt($dbi, $argarray, $request))));
    }

    /**
     * svg?, ...
    <object type="audio/x-wav" standby="Loading Audio" data="example.wav">
    <param name="src" value="example.wav" valuetype="data"></param>
    <param name="autostart" value="false" valuetype="data"></param>
    <param name="controls" value="ControlPanel" valuetype="data"></param>
    <a href="example.wav">Example Audio File</a>
    </object>
     * See http://www.protocol7.com/svg-wiki/?EmbedingSvgInHTML
    <object data="sample.svgz" type="image/svg+xml"
    width="400" height="300">
    <embed src="sample.svgz" type="image/svg+xml"
    width="400" height="300" />
    <p>Alternate Content like <img src="" /></p>
    </object>
     */
    // how to handle alternate images? always provide alternate static images?
    public function embedObject($url, $type, $args = array(), $params = false)
    {
        if (empty($args)) {
            $args = array();
        }
        $object = HTML::object(array_merge($args, array('src' => $url, 'type' => $type)));
        if ($params) {
            $object->pushContent($params);
        }
        return $object;
    }

    // --------------------------------------------------------------------------
    // ---------------------- static member functions ---------------------------
    // --------------------------------------------------------------------------

    /**
     * Creates one static PEAR Cache object and returns copies afterwards.
     * FIXME: There should be references returned
     *
     * @return Cache copy of the cache object
     */
    public static function newCache()
    {
        static $staticcache;

        if (!is_object($staticcache)) {
            $cacheparams = array();
            foreach (explode(':', 'database:cache_dir:filename_prefix:highwater:lowwater'
                . ':maxlifetime:maxarglen:usecache:force_syncmap') as $key) {
                $cacheparams[$key] = constant('PLUGIN_CACHED_' . strtoupper($key));
            }
            $cacheparams['imgtypes'] = preg_split('/\s*:\s*/', PLUGIN_CACHED_IMGTYPES);
            $staticcache = new Cache(PLUGIN_CACHED_DATABASE, $cacheparams);
            $staticcache->gc_maxlifetime = PLUGIN_CACHED_MAXLIFETIME;

            if (!PLUGIN_CACHED_USECACHE) {
                $staticcache->setCaching(false);
            }
        }
        return $staticcache; // FIXME: use references ?
    }

    /**
     * Determines whether a needed image type may is available
     * from the GD library and gives an alternative otherwise.
     *
     * @param string $wish    one of 'png', 'gif', 'jpeg', 'jpg'
     * @return string the image type to be used ('png', 'gif', 'jpeg')
     *                        'html' in case of an error
     */

    public static function decideImgType($wish)
    {
        if ($wish == 'html') {
            return $wish;
        }
        if ($wish == 'jpg') {
            $wish = 'jpeg';
        }

        $supportedtypes = array();
        // Todo: pdf, ...
        $imagetypes = array(
            'png' => IMG_PNG,
            'gif' => IMG_GIF,
            'jpeg' => IMG_JPEG,
            'wbmp' => IMG_WBMP,
            'xpm' => IMG_XPM,
            /* // these do work but not with the ImageType bitmask
            'gd'    => IMG_GD,
            'gd2'   => IMG_GD,
            'xbm'   => IMG_XBM,
            */
        );
        $presenttypes = imagetypes();
        foreach ($imagetypes as $imgtype => $bitmask) {
            if ($presenttypes && $bitmask) {
                array_push($supportedtypes, $imgtype);
            }
        }
        if (in_array($wish, $supportedtypes)) {
            return $wish;
        } elseif (!empty($supportedtypes)) {
            return reset($supportedtypes);
        } else {
            return 'html';
        }
    } // decideImgType

    /**
     * Writes an image into a file or to the browser.
     * Note that there is no check if the image can
     * be written.
     *
     * @param   string $imgtype    'png', 'gif' or 'jpeg'
     * @param   string $imghandle  image handle containing the image
     * @param   string $imgfile    file name of the image to be produced
     * @return void
     * @see     decideImageType
     */
    public static function writeImage($imgtype, $imghandle, $imgfile = '')
    {
        if ($imgtype != 'html') {
            $func = "Image" . strtoupper($imgtype);
            if ($imgfile) {
                $func($imghandle, $imgfile);
            } else {
                $func($imghandle);
            }
        }
    } // writeImage

    /**
     * Sends HTTP header for some predefined file types.
     * There is no parameter check.
     *
     * @param   string $doctype 'gif', 'png', 'jpeg', 'html'
     * @return void
     */
    public static function writeHeader($doctype)
    {
        static $IMAGEHEADER = array(
            'gif' => 'Content-type: image/gif',
            'png' => 'Content-type: image/png',
            'jpeg' => 'Content-type: image/jpeg',
            'xbm' => 'Content-type: image/xbm',
            'xpm' => 'Content-type: image/xpm',
            'gd' => 'Content-type: image/gd',
            'gd2' => 'Content-type: image/gd2',
            'wbmp' => 'Content-type: image/vnd.wap.wbmp', // wireless bitmaps for PDA's and such.
            'html' => 'Content-type: text/html');
        // Todo: pdf, svg, svgz
        header($IMAGEHEADER[$doctype]);
    }

    /**
     * Converts argument array to a string of format option="value".
     * This should only be used for displaying plugin options for
     * the quoting of arguments is not safe, yet.
     *
     * @param  array $argarray    contains all arguments to be converted
     * @return string concated arguments
     */
    public static function glueArgs($argarray)
    {
        if (!empty($argarray)) {
            $argstr = '';
            foreach ($argarray as $key => $value) {
                $argstr .= $key . '=' . '"' . $value . '" ';
                // FIXME: How are values quoted? Can a value contain '"'?
                // TODO: rawurlencode(value)
            }
            return substr($argstr, 0, strlen($argstr) - 1);
        }
        return '';
    } // glueArgs

    // ---------------------- FetchImageFromCache ------------------------------

    /**
     * Extracts the cache entry id from the url and the plugin call
     * parameters if available.
     *
     * @param  string $id            return value. Image is stored under this id.
     * @param  string $plugincall    return value. Only returned if present in url.
     *                               Contains all parameters to reconstruct
     *                               plugin call.
     * @param  Cache $cache          PEAR Cache object
     * @param  Request $request
     * @param  string $errorformat   format which should be used to
     *                               output errors ('html', 'png', 'gif', 'jpeg')
     * @return bool false if an error occurs, true otherwise.
     *                               Param id and param plugincall are
     *                               also return values.
     */
    private function checkCall1(&$id, &$plugincall, $cache, $request, $errorformat)
    {
        $id = $request->getArg('id');
        $plugincall = rawurldecode($request->getArg('args'));

        if (!$id) {
            if (!$plugincall) {
                // This should never happen, so do not gettextify.
                $errortext = "Neither 'args' nor 'id' given. Cannot proceed without parameters.";
                $this->printError($errorformat, $errortext);
                return false;
            } else {
                $id = $cache->generateId($plugincall);
            }
        }
        return true;
    } // checkCall1

    /**
     * Extracts the parameters necessary to reconstruct the plugin
     * call needed to produce the requested image.
     *
     * @param  string $plugincall  reference to serialized array containing both
     *                             name and parameters of the plugin call
     * @param  Request $request    ???
     * @return bool false if an error occurs, true otherwise.
     *
     */
    private function checkCall2(&$plugincall, $request)
    {
        // if plugincall wasn't sent by URL, it must have been
        // stored in a session var instead and we can retreive it from there
        if (!$plugincall) {
            if (!$plugincall = $request->getSessionVar('imagecache' . $id)) {
                // I think this is the only error which may occur
                // without having written bad code. So gettextify it.
                $errortext = sprintf(
                    gettext("There is no image creation data available to id “%s”. Please reload referring page."),
                    $id
                );
                $this->printError($errorformat, $errortext);
                return false;
            }
        }
        $plugincall = unserialize($plugincall);
        return true;
    } // checkCall2

    /**
     * Creates an image or image map depending on the plugin type.
     * @param  array $content            reference to created array which overwrite the keys
     *                                   'image', 'imagetype' and possibly 'html'
     * @param  WikiPluginCached $plugin  plugin which is called to create image or map
     * @param  WikiDB $dbi     WikiDB    handle to database
     * @param  array $argarray array     Contains all arguments needed by plugin
     * @param  Request $request Request  ????
     * @param  string $errorformat       outputs errors in 'png', 'gif', 'jpg' or 'html'
     * @return bool error status; true=ok; false=error
     */
    private function produceImage(&$content, $plugin, $dbi, $argarray, $request, $errorformat)
    {
        $plugin->resetError();
        $content['html'] = $imagehandle = false;
        if ($plugin->getPluginType() == PLUGIN_CACHED_MAP) {
            list($imagehandle, $content['html']) = $plugin->getMap($dbi, $argarray, $request);
        } else {
            $imagehandle = $plugin->getImage($dbi, $argarray, $request);
        }

        $content['imagetype']
            = $this->decideImgType($plugin->getImageType($dbi, $argarray, $request));
        $errortext = $plugin->getError();

        if (!$imagehandle || $errortext) {
            if (!$errortext) {
                $errortext = "'<<" . $plugin->getName() . ' '
                    . $this->glueArgs($argarray) . " >>' returned no image, "
                    . " although no error was reported.";
            }
            $this->printError($errorformat, $errortext);
            return false;
        }

        // image handle -> image data
        if (!empty($this->_static)) {
            $ext = "." . $content['imagetype'];
            if (is_string($imagehandle) and file_exists($imagehandle)) {
                if (preg_match("/.(\w+)$/", $imagehandle, $m)) {
                    $ext = "." . $m[1];
                }
            }
            $tmpfile = tempnam(getUploadFilePath(), PLUGIN_CACHED_FILENAME_PREFIX . $ext);
            if (!strstr(basename($tmpfile), $ext)) {
                unlink($tmpfile);
                $tmpfile .= $ext;
            }
            $tmpfile = getUploadFilePath() . basename($tmpfile);
            if (is_string($imagehandle) and file_exists($imagehandle)) {
                rename($imagehandle, $tmpfile);
            }
        } else {
            $tmpfile = $this->tempnam();
        }
        if (is_resource($imagehandle)) {
            $this->writeImage($content['imagetype'], $imagehandle, $tmpfile);
            imagedestroy($imagehandle);
            sleep(0.2);
        } elseif (is_string($imagehandle)) {
            $content['file'] = getUploadFilePath() . basename($tmpfile);
            $content['url'] = getUploadDataPath() . basename($tmpfile);
            return true;
        }
        if (file_exists($tmpfile)) {
            $fp = fopen($tmpfile, 'rb');
            if (filesize($tmpfile)) {
                $content['image'] = fread($fp, filesize($tmpfile));
            } else {
                $content['image'] = '';
            }
            fclose($fp);
            if (!empty($this->_static)) {
                // on static it is in "uploads/" but in wikicached also
                $content['file'] = $tmpfile;
                $content['url'] = getUploadDataPath() . basename($tmpfile);
                return true;
            }
            unlink($tmpfile);
            if ($content['image']) {
                return true;
            }
        }
        return false;
    }

    public function staticUrl($tmpfile)
    {
        $content['file'] = $tmpfile;
        $content['url'] = getUploadDataPath() . basename($tmpfile);
        return $content;
    }

    public function tempnam($prefix = "")
    {
        if (preg_match("/^(.+)\.(\w{2,4})$/", $prefix, $m)) {
            $prefix = $m[1];
            $ext = "." . $m[2];
        } else {
            $ext = isWindows() ? ".tmp" : "";
        }
        $temp = tempnam(
            isWindows() ? str_replace('/', "\\", PLUGIN_CACHED_CACHE_DIR)
                : PLUGIN_CACHED_CACHE_DIR,
            $prefix ? $prefix : PLUGIN_CACHED_FILENAME_PREFIX
        );
        if (isWindows()) {
            if ($ext != ".tmp") {
                unlink($temp);
            }
            $temp = preg_replace("/\.tmp$/", $ext, $temp);
        } else {
            $temp .= $ext;
        }
        return $temp;
    }

    /**
     * Main function for obtaining images from cache or generating on-the-fly
     * from parameters sent by url or session vars.
     *
     * @param  WikiDB $dbi                 handle to database
     * @param  Request $request            ???
     * @param  string $errorformat         outputs errors in 'png', 'gif', 'jpeg' or 'html'
     * @return bool
     */
    public function fetchImageFromCache($dbi, $request, $errorformat = 'png')
    {
        $cache = $this->newCache();
        $errorformat = $this->decideImgType($errorformat);
        // get id
        if (!$this->checkCall1($id, $plugincall, $cache, $request, $errorformat)) {
            return false;
        }
        // check cache
        $content = $cache->get($id, 'imagecache');

        if (!empty($content['image'])) {
            $this->writeHeader($content['imagetype']);
            print $content['image'];
            return true;
        }
        if (!empty($content['html'])) {
            print $content['html'];
            return true;
        }
        // static version?
        if (!empty($content['file']) && !empty($content['url']) && file_exists($content['file'])) {
            print $this->embedImg($content['url'], $dbi, array(), $request);
            return true;
        }

        // re-produce image. At first, we need the plugincall parameters.
        // Cached args with matching id override given args to shorten getimg.php?id=md5
        if (!empty($content['args'])) {
            $plugincall['arguments'] = $content['args'];
        }
        if (!$this->checkCall2($plugincall, $request)) {
            return false;
        }

        $pluginname = $plugincall['pluginname'];
        $argarray = $plugincall['arguments'];

        $loader = new WikiPluginLoader();
        $plugin = $loader->getPlugin($pluginname);
        if (!$plugin) {
            return false;
        }

        // cache empty, but image maps have to be created _inline_
        // so ask user to reload wiki page instead
        if (($plugin->getPluginType() & PLUGIN_CACHED_MAP) && PLUGIN_CACHED_FORCE_SYNCMAP) {
            $errortext = _("Image map expired. Reload wiki page to recreate its HTML part.");
            $this->printError($errorformat, $errortext);
        }

        if (!$this->produceImage($content, $plugin, $dbi, $argarray, $request, $errorformat)) {
            return false;
        }

        $expire = $plugin->getExpire($dbi, $argarray, $request);

        if ($content['image']) {
            $cache->save($id, $content, $expire, 'imagecache');
            $this->writeHeader($content['imagetype']);
            print $content['image'];
            return true;
        }

        $errortext = _("Could not create image file from imagehandle.");
        $this->printError($errorformat, $errortext);
        return false;
    } // FetchImageFromCache

    // -------------------- error handling ----------------------------

    /**
     * Resets buffer containing all error messages. This is allways
     * done before invoking any abstract creation routines like
     * <code>getImage</code>.
     *
     * @return void
     */
    protected function resetError()
    {
        $this->_errortext = '';
    }

    /**
     * Returns all accumulated error messages.
     *
     * @return string error messages printed with <code>complain</code>.
     */
    protected function getError()
    {
        return $this->_errortext;
    }

    /**
     * Collects the error messages in a string for later output
     * by WikiPluginCached. This should be used for any errors
     * that occur during data (html,image,map) creation.
     *
     * @param  string $addtext errormessage to be printed (separate
     *                         multiple lines with '\n')
     * @return void
     */
    protected function complain($addtext)
    {
        $this->_errortext .= $addtext;
    }

    /**
     * Outputs the error as image if possible or as html text
     * if wished or html header has already been sent.
     *
     * @param  string $imgtype 'png', 'gif', 'jpeg' or 'html'
     * @param  string $errortext guess what?
     * @return void
     */
    public function printError($imgtype, $errortext)
    {
        $imgtype = $this->decideImgType($imgtype);

        $talkedallready = ob_get_contents() || headers_sent();
        if (($imgtype == 'html') || $talkedallready) {
            if (is_object($errortext)) {
                $errortext = $errortext->asXML();
            }
            trigger_error($errortext, E_USER_WARNING);
        } else {
            $red = array(255, 0, 0);
            $grey = array(221, 221, 221);
            if (is_object($errortext)) {
                $errortext = $errortext->asString();
            }
            $im = $this->text2img($errortext, 2, $red, $grey);
            if (!$im) {
                trigger_error($errortext, E_USER_WARNING);
                return;
            }
            $this->writeHeader($imgtype);
            $this->writeImage($imgtype, $im);
            imagedestroy($im);
        }
    } // printError

    /**
     * Basic text to image converter for error handling which allows
     * multiple line output.
     * It will only output the first 25 lines of 80 characters. Both
     * values may be smaller if the chosen font is to big for there
     * is further restriction to 600 pixel in width and 350 in height.
     *
     * @param  string $txt     multi line text to be converted
     * @param  int $fontnr     number (1-5) telling gd which internal font to use;
     *                         I recommend font 2 for errors and 4 for help texts.
     * @param  array $textcol  text color as a list of the rgb components; array(red,green,blue)
     * @param  array $bgcol    background color; array(red,green,blue)
     * @return string image handle for gd routines
     */
    public static function text2img($txt, $fontnr, $textcol, $bgcol)
    {
        // basic (!) output for error handling

        // check parameters
        if ($fontnr < 1 || $fontnr > 5) {
            $fontnr = 2;
        }
        if (!is_array($textcol) || !is_array($bgcol)) {
            $textcol = array(0, 0, 0);
            $bgcol = array(255, 255, 255);
        }
        foreach (array_merge($textcol, $bgcol) as $component) {
            if ($component < 0 || $component > 255) {
                $textcol = array(0, 0, 0);
                $bgcol = array(255, 255, 255);
                break;
            }
        }

        // prepare Parameters

        // set maximum values
        $IMAGESIZE = array(
            'cols' => 80,
            'rows' => 25,
            'width' => 600,
            'height' => 350);

        $charx = imagefontwidth($fontnr);
        $chary = imagefontheight($fontnr);
        $marginx = $charx;
        $marginy = floor($chary / 2);

        $IMAGESIZE['cols'] = min($IMAGESIZE['cols'], floor(($IMAGESIZE['width'] - 2 * $marginx) / $charx));
        $IMAGESIZE['rows'] = min($IMAGESIZE['rows'], floor(($IMAGESIZE['height'] - 2 * $marginy) / $chary));

        // split lines
        $y = 0;
        $wx = 0;
        do {
            $len = strlen($txt);
            $npos = strpos($txt, "\n");

            if ($npos === false) {
                $breaklen = min($IMAGESIZE['cols'], $len);
            } else {
                $breaklen = min($npos + 1, $IMAGESIZE['cols']);
            }
            $lines[$y] = chop(substr($txt, 0, $breaklen));
            $wx = max($wx, strlen($lines[$y++]));
            $txt = substr($txt, $breaklen);
        } while ($txt && ($y < $IMAGESIZE['rows']));

        // recalculate image size
        $IMAGESIZE['rows'] = $y;
        $IMAGESIZE['cols'] = $wx;

        $IMAGESIZE['width'] = $IMAGESIZE['cols'] * $charx + 2 * $marginx;
        $IMAGESIZE['height'] = $IMAGESIZE['rows'] * $chary + 2 * $marginy;

        // create blank image
        $im = @imagecreate($IMAGESIZE['width'], $IMAGESIZE['height']);

        $col = imagecolorallocate($im, $textcol[0], $textcol[1], $textcol[2]);
        $bg = imagecolorallocate($im, $bgcol[0], $bgcol[1], $bgcol[2]);

        imagefilledrectangle($im, 0, 0, $IMAGESIZE['width'] - 1, $IMAGESIZE['height'] - 1, $bg);

        // write text lines
        foreach ($lines as $nr => $textstr) {
            imagestring(
                $im,
                $fontnr,
                $marginx,
                $marginy + $nr * $chary,
                $textstr,
                $col
            );
        }
        return $im;
    } // text2img

    public function newFilterThroughCmd($input, $commandLine)
    {
        $descriptorspec = array(
            0 => array("pipe", "r"), // stdin is a pipe that the child will read from
            1 => array("pipe", "w"), // stdout is a pipe that the child will write to
            2 => array("pipe", "w"), // stdout is a pipe that the child will write to
        );

        $process = proc_open("$commandLine", $descriptorspec, $pipes);
        if (is_resource($process)) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable  handle connected to child stdout
            // 2 => readable  handle connected to child stderr
            fwrite($pipes[0], $input);
            fclose($pipes[0]);
            $buf = "";
            while (!feof($pipes[1])) {
                $buf .= fgets($pipes[1], 1024);
            }
            fclose($pipes[1]);
            $stderr = '';
            while (!feof($pipes[2])) {
                $stderr .= fgets($pipes[2], 1024);
            }
            fclose($pipes[2]);
            // It is important that you close any pipes before calling
            // proc_close in order to avoid a deadlock
            proc_close($process);
            if (empty($buf)) {
                PrintXML($this->error($stderr));
            }
            return $buf;
        }
        return '';
    }

    // run "echo $source | $commandLine" and return result
    public function filterThroughCmd($source, $commandLine)
    {
        return $this->newFilterThroughCmd($source, $commandLine);
    }

    /**
     * Execute system command and wait until the outfile $until exists.
     *
     * @param  string $cmd      command to be invoked
     * @param  string $until    expected output filename
     * @return bool error status; true=ok; false=error
     */
    public function execute($cmd, $until = '')
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        // cmd must redirect stderr to stdout though!
        $errstr = exec($cmd); //, $outarr, $returnval); // normally 127
        //$errstr = join('',$outarr);
        $ok = empty($errstr);
        if (!$ok) {
            trigger_error("\n" . $cmd . " failed: $errstr", E_USER_WARNING);
        } elseif ($request->getArg('debug')) {
            trigger_error("\n" . $cmd . ": success\n");
        }
        if (!isWindows()) {
            if ($until) {
                $loop = 100000;
                while (!file_exists($until) and $loop > 0) {
                    $loop -= 100;
                    usleep(100);
                }
            } else {
                usleep(5000);
            }
        }
        if ($until) {
            return file_exists($until);
        }
        return $ok;
    }
}
