<?php
rcs_id('$Id: Tools.php,v 1.1 2003-01-23 00:32:04 zorloc Exp $')
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */


/**
 * ConfigValue
 * 
 *
 */
class ConfigValue {
    var $name;
    var $currentValue;
    var $defaultValue;
    var $description;
    var $validator;
    
    function ConfigValue($params){
        $this->name = $params['name'];
        $this->defaultValue = $params['defaultValue'];
        $this->description = $params['description'];
        $this->validator = &$params['validator'];
        $this->currentValue = $this->getStarting();
    }

    function valid($value){
        if ($this->validator->validate($value)) {
            return true;
        }
        trigger_error("Value for " . $this->name . "is invalid", E_USER_WARNING);
        return false;
    }
    
    function getStarting(){
        return $this->defaultValue;
    }
    
    function getCurrent(){
        return $this->currentValue;
    }
    
    function setCurrent($value){
        if ($this->valid($value)) {
            $this->currentValue = $value;
        }
    }
    
    function getName(){
        return $this->name;
    }
    
    function getDefaultValue(){
        return $this->defaultValue;
    }
    
    function getShortDescription(){
        return $this->description['short'];
    }

    function getFullDescription(){
        return $this->description['full'];
    }
}

/**
* 
*/
class ConfigConstant extends ConfigValue {

    function getStarting(){
        if (defined($this->name)) {
            $starting = constant($this->name);
            if ($this->valid($starting)) {
                return $starting;
            }
        }
        return $this->defaultValue;
    }
}

/**
* 
*/
class ConfigVariable extends ConfigValue {

    function getStarting(){
        if (isset(${$this->name})) {
            $starting = ${$this->name};
            if ($this->valid($starting)) {
                return $starting;
            }
        }
        return $this->defaultValue;
    }
}

//$Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>