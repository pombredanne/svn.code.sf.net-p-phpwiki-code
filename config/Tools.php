<?php
rcs_id('$Id: Tools.php,v 1.2 2003-01-28 06:31:00 zorloc Exp $')
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
 * Base class for Configuration properties
 * 
 * Class provides the base functions for subclasses to get and set 
 * valid values for configuration properties.
 * @author Joby Walker<zorloc@imperium.org>
 */
class ConfigValue {
    
    /** 
    * Name of the Value.
    * @var string
    * @access protected
    */
    var $name;
    /** 
    * The current value.
    * @var mixed
    * @access protected
    */
    var $currentValue;
    /** 
    * The default value.
    * @var mixed
    * @access protected
    */
    var $defaultValue;
    /** 
    * Array with a short and full description.
    * @var array
    * @access protected
    */
    var $description;
    /** 
    * Validator object to validate a new value.
    * @var object
    * @access protected
    */
    var $validator;
    
    /**
    * Constructor
    * 
    * Initializes instance variables from parameter array.
    * @param array $params Array with properties of the config value.
    */
    function ConfigValue($params){
        $this->name = $params['name'];
        $this->defaultValue = $params['defaultValue'];
        $this->description = $params['description'];
        $this->validator = &$params['validator'];
        $this->currentValue = $this->getStarting();
    }
    
    /**
    * Static method to get the proper subclass.
    * 
    * @param array $params Config Values properties.
    * @return object A subclass of ConfigValue.
    * @static
    */
    function getConfig($params){
        $class = 'Config' . $params['type'];
        if (isset($params['validator'])) {
            $params['validator'] = &Validator::getValidator($params['validator']);
        }
        return &new $class ($params);
    }

    /**
    * Determines if the value is valid.
    * 
    * If the parameter is a valid value for this config value returns
    * true, false else.
    * @param mixed $value Value to be checked for validity.
    * @return boolean True if valid, false else.
    */
    function valid($value){
        if ($this->validator->validate($value)) {
            return true;
        }
        trigger_error("Value for \'" . $this->name . "\' is invalid.",
                      E_USER_WARNING);
        return false;
    }

    /**
    * Determines the value currently being used.
    * 
    * Just returns the default value.
    * @return mixed The currently used value (the default).
    */
    function getStarting(){
        return $this->defaultValue;
    }
    
    /**
    * Get the currently selected value.
    * 
    * @return mixed The currently selected value.
    */
    function getCurrent(){
        return $this->currentValue;
    }

    /**
    * Set the current value to this.
    * 
    * Checks to see if the parameter is a valid value, if so it
    * sets the parameter to currentValue.
    * @param mixed $value The value to set.
    */    
    function setCurrent($value){
        if ($this->valid($value)) {
            $this->currentValue = $value;
        }
    }
    
    /**
    * Get the Name of the Value
    * @return mixed Name of the value.
    */
    function getName(){
        return $this->name;
    }
    
    /**
    * Get the default value of the Value
    * @return mixed Default value of the value.
    */
    function getDefaultValue(){
        return $this->defaultValue;
    }
    
    /**
    * Get the Short Description of the Value
    * @return mixed Short Description of the value.
    */
    function getShortDescription(){
        return $this->description['short'];
    }

    /**
    * Get the Full Description of the Value
    * @return mixed Full Description of the value.
    */
    function getFullDescription(){
        return $this->description['full'];
    }
}

/**
* Configuration class for Constants
* 
* Subclass of ConfigValue which overrides the getStarting method 
* to provide the true value currently used.
* @author Joby Walker<zorloc@imperium.org>
*/
class ConfigConstant extends ConfigValue {

    /**
    * Determines the currently used value of this constant
    * @return mixed The value currently used.
    */
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
* Configuration class for Variables
* 
* Subclass of ConfigValue which overrides the getStarting method 
* to provide the true value currently used
* @author Joby Walker<zorloc@imperium.org>
*/
class ConfigVariable extends ConfigValue {

    /**
    * Determines the currently used value of this variable
    * @return mixed The value currently used.
    */
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


class ConfigArray extends ConfigVariable {


    function ConfigArray($params){
        $this->name = $params['name'];
        $this->description = $params['description'];
        $number = 0;
        foreach ($params['defaultValue'] as $config){
            $this->value = &ConfigValue::getConfig($config);
            $number++;
        }
        return;
    }
}



/**
* Abstract base Validator Class
* @author Joby Walker<zorloc@imperium.org>
*/
class Validator {

    /**
    * Constructor
    * 
    * Dummy constructor that does nothing.
    */
    function Validator(){
        return;
    }

    /**
    * Dummy valitate method -- always returns true.
    * @param mixed $value Value to check.
    * @return boolean Always returns true.
    */
    function validate($value){
        return true;
    }
    
    /**
    * Get the proper Valitator subclass for the provided parameters
    * @param array $params Initialization values for Validator.
    * @return object Validator subclass for use with the parameters.
    * @static
    */
    function getValidator($params){
        extract($params, EXTR_OVERWRITE);
        $class = 'Validator' . $type;
        if (isset($list)){
            $class .= 'List';
            return &new $class ($list);
        } elseif (isset($range)) {
            $class .= 'Range';
            return &new $class ($range);
        } elseif (isset($pcre)){
            $class .= 'Pcre';
            return &new $class ($pcre);
        }
        return &new $class ();
    
    }

}

/**
* Validator subclass for use with boolean values
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorBoolean extends Validator {

    /**
    * Checks the parameter to see if it is a boolean, returns true if
    * it is, else false.
    * @param boolean $boolean Value to check to ensure it is a boolean.
    * @return boolean True if parameter is boolean.
    */
    function validate ($boolean){
        if (is_bool($boolean)) {
            return true;
        }
        return false;
    }
}

/**
* Validator subclass for use with integer values with no bounds.
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorInteger extends Validator {

    /**
    * Checks the parameter to ensure that it is an integer.
    * @param integer $integer Value to check.
    * @return boolean True if parameter is an integer, false else.
    */
    function validate ($integer){
        if (is_int($integer)) {
            return true;
        }
        return false;
    }
}

/**
* Validator subclass for use with integer values to be bound within a range.
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorIntegerRange extends ValidatorInteger {

    /** 
    * Minimum valid value
    * @var integer
    * @access protected
    */
    var $minimum;
    
    /** 
    * Maximum valid value
    * @var integer
    * @access protected
    */
    var $maximum;

    /**
    * Constructor
    * 
    * Sets the minimum and maximum values from the parameter array.
    * @param array $range Minimum and maximum valid values.
    */
    function ValidatorIntegerRange($range){
        $this->minimum = $range['minimum'];
        $this->maximum = $range['maximum'];
        return;
    }
    
    /**
    * Checks to ensure that the parameter is an integer and within the desired 
    * range.
    * @param integer $integer Value to check. 
    * @return boolean True if the parameter is an integer and within the 
    * desired range, false else.
    */
    function validate ($integer){
        if (is_int($integer)) {
            if (($integer >= $this->minimum) && ($integer <= $this->maximum)) {
                return true;
            }
        }
        return false;
    }

}

/**
* Validator subclass for use with integer values to be selected from a list.
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorIntegerList extends ValidatorInteger {

    /** 
    * Array of potential valid values
    * @var array
    * @access protected
    */
    var $intList;
    
    /**
    * Constructor
    * 
    * Saves parameter as the instance variable $intList.
    * @param array List of valid values.
    */
    function ValidatorIntegerList($intList){
        $this->intList = $intList;
        return;
    }

    /**
    * Checks the parameter to ensure that it is an integer, and 
    * within the defined list.
    * @param integer $integer Value to check.
    * @return boolean True if parameter is an integer and in list, false else.
    */
    function validate ($integer){
        if (is_int($integer)) {
            if (in_array($integer, $this->intList, true)) {
                return true;
            }
        }
        return false;
    }

}

/**
* Validator subclass for string values with no bounds
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorString extends Validator {

    /**
    * Checks the parameter to ensure that is is a string.
    * @param string $string Value to check.
    * @return boolean True if parameter is a string, false else.
    */
    function validate ($string){
        if (is_string($string)) {
            return true;
        }
        return false;
    }

}

/**
* Validator subclass for string values to be selected from a list.
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorStringList extends ValidatorString {

    /** 
    * Array of potential valid values
    * @var array
    * @access protected
    */
    var stringList;
    
    /**
    * Constructor
    * 
    * Saves parameter as the instance variable $stringList.
    * @param array List of valid values.
    */
    function ValidatorStringList($stringList){
        $this->stringList = $stringList;
        return;
    }

    /**
    * Checks the parameter to ensure that is is a string, and within 
    * the defined list.
    * @param string $string Value to check.
    * @return boolean True if parameter is a string and in the list, false else.
    */
    function validate($string){
        if (is_string($string)) {
            if (in_array($string, $this->stringList, true)) {
                return true;
            }
        }
        return false;
    }

}

/**
* Validator subclass for string values that must meet a PCRE.
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorStringPcre extends ValidatorString {

    /** 
    * PCRE to validate value
    * @var array
    * @access protected
    */
    var pattern;

    /**
    * Constructor
    * 
    * Saves parameter as the instance variable $pattern.
    * @param array PCRE pattern to determin validity.
    */
    function ValidatorStringPcre($pattern){
        $this->pattern = $pattern;
        return;
    }

    /**
    * Checks the parameter to ensure that is is a string, and matches the 
    * defined pattern.
    * @param string $string Value to check.
    * @return boolean True if parameter is a string and matches the pattern,
    * false else.
    */
    function validate ($string){
        if (is_string($string)) {
            if (preg_match($this->pattern, $string)) {
                return true;
            }
        }
        return false;
    }
}

/**
* Validator subclass for constant values.
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorConstant extends Validator {

    /**
    * Checks the parameter to ensure that is is a constant.
    * @param string $constant Value to check.
    * @return boolean True if parameter is a constant, false else.
    */
    function validate ($constant){
        if (defined($constant)) {
            return true;
        }
        return false;
    }
}

/**
* Validator subclass for constant values to be selected from a list.
* @author Joby Walker<zorloc@imperium.org>
*/
class ValidatorConstantList extends Validator {

    /** 
    * Array of potential valid values
    * @var array
    * @access protected
    */
    var constantList;

    /**
    * Constructor
    * 
    * Saves parameter as the instance variable $constantList.
    * @param array List of valid values.
    */
    function ValidatorConstantList($constantList){
        $this->constantList = $constantList;
        return;
    }

    /**
    * Checks the parameter to ensure that is is a constant, and within 
    * the defined list.
    * @param string $constant Value to check.
    * @return boolean True if parameter is a constant and in the list, false else.
    */
    function validate ($constant){
        if (defined($constant)) {
            if (in_array($constant, $this->constantList, true)) {
                return true;
            }
        }
        return false;
    }
}


//$Log: not supported by cvs2svn $
//Revision 1.1  2003/01/23 00:32:04  zorloc
//Initial work for classes to hold configuration constants/variables. Base
//ConfigValue class and subclasses for constants and variables.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>