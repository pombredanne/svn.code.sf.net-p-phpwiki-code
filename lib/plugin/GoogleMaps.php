<?php
/**
 * Copyright © 2005 $ThePhpWikiProgrammingTeam
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
 * Uses Google Maps as a Map Server
 *
 * This plugin displays a marker with further infos (when clicking) on given coordinates.
 * Hint: You need to sign up for a Google Maps API key!
 *         https://www.google.com/apis/maps/signup.html
 *       Then enter the key in config/config.ini under GOOGLE_LICENSE_KEY=
 *
 * Usage:
 *  <<GoogleMaps
 *           Latitude=53.053
 *             Longitude=7.803
 *           ZoomFactor=10
 *           Marker=true
 *           InfoText=
 *           InfoLink=
 *           MapType=Map|Satellite|Hybrid
 *           width=500px
 *           height=400px
 *  >>
 *
 * @author Reini Urban
 *
 * @see plugin/GooglePlugin
 *      https://www.google.com/apis/maps/, https://maps.google.com/
 *      http://libgmail.sourceforge.net/googlemaps.html
 *
 * NOT YET SUPPORTED:
 *   Search for keywords (search=)
 *   mult. markers would need a new syntax
 *   directions (from - to)
 *   drawing polygons
 *   Automatic route following
 */

class WikiPlugin_GoogleMaps extends WikiPlugin
{
    public function getDescription()
    {
        return _("Display a marker with further infos (when clicking) on given coordinates.");
    }

    public function getDefaultArguments()
    {
        return array(
            'Longitude' => '',
            'Latitude' => '',
            'ZoomFactor' => 5,
            'Marker' => true,
            'InfoText' => '',
            'MapType' => 'Hybrid', // Map|Satellite|Hybrid,
            'SmallMapControl' => false, // large or small
            'width' => '500px',
            'height' => '400px',
        );
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        $args = $this->getArgs($argstr, $request);
        extract($args);

        if ($Longitude === '') {
            return $this->error(sprintf(_("A required argument “%s” is missing."), 'Longitude'));
        }
        if (!is_numeric($Longitude)) {
            return $this->error(_('Longitude must be a number.'));
        }
        if ($Latitude === '') {
            return $this->error(sprintf(_("A required argument “%s” is missing."), 'Latitude'));
        }
        if (!is_numeric($Latitude)) {
            return $this->error(_('Latitude must be a number.'));
        }

        $maps = JavaScript('', array('src' => "https://maps.google.com/maps?file=api&v=1&key=" . GOOGLE_LICENSE_KEY));
        $id = GenerateId("googlemap");
        switch ($MapType) {
            case "Satellite":
                $type = "_SATELLITE_TYPE";
                break;
            case "Map":
                $type = "_MAP_TYPE";
                break;
            case "Hybrid":
                $type = "_HYBRID_TYPE";
                break;
            default:
                return $this->error(sprintf(_("Invalid argument %s"), $MapType));
        }
        $div = HTML::div(array('id' => $id, 'style' => 'width: ' . $width . '; height: ' . $height));

        // TODO: Check for multiple markers or polygons
        if (!$InfoText) {
            $Marker = false;
        }
        // Create a marker whose info window displays the given text
        if ($Marker) {
            if ($InfoText) {
                include_once 'lib/BlockParser.php';
                $page = $dbi->getPage($request->getArg('pagename'));
                $rev = $page->getCurrentRevision(false);
                $markertext = TransformText($InfoText, $basepage);
            }
            $markerjs = JavaScript("
function createMarker(point, text) {
  var marker = new GMarker(point);
  var html = text + \"<br /><br />[" .
                _("new&nbsp;window") .
                "]\";
  GEvent.addListener(marker, \"click\", function() {marker.openInfoWindowHtml(html);});
  return marker;
}");
        }

        $run = JavaScript(
            "
var map = new GMap(document.getElementById('" . $id . "'));\n" .
                ($SmallMapControl
                    ? "map.addControl(new GSmallMapControl());\n"
                    : "map.addControl(new GLargeMapControl());\n") . "
map.addControl(new GMapTypeControl());
map.centerAndZoom(new GPoint(" . $Longitude . ", " . $Latitude . "), " . $ZoomFactor . ");
map.setMapType(" . $type . ");" .
                ($Marker
                    ? "
var point = new GPoint(" . $Longitude . "," . $Latitude . ");
var marker = createMarker(point, '" . $markertext->asXML() . "'); map.addOverlay(marker);"
                    : "")
        );
        if ($Marker) {
            return HTML($markerjs, $maps, $div, $run);
        } else {
            return HTML($maps, $div, $run);
        }
    }
}
