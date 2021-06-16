<?php
class pw_option_integer extends pw_option_text {

  function __construct($name, $description, $dependency = false,
                       $validator = false, $default = false) {
    if (!$validator) {
      $validator = new pw_validator_ereg("Sorry, '%s' is not an integer.",
                                         '^[-+]?[0-9]+$');
    }
    parent::__construct($name, $description, $dependency,
                        $validator, $default);
  }

  function get_config() {
    if ($this->is_ready() && $this->is_valid() &&
        $this->value != $this->default) {
      return "/* $this->name */\n\$this->properties['$this->name'] = " .
        "$this->value;\n\n";
    } else {
      return '';
    }
  }
}
