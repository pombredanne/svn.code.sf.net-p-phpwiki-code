<?php rcs_id('$Id: ErrorManager.php,v 1.10 2002-01-20 03:45:47 carstenklapp Exp $');


define ('EM_FATAL_ERRORS',
	E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
define ('EM_WARNING_ERRORS',
	E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING);
define ('EM_NOTICE_ERRORS', E_NOTICE | E_USER_NOTICE);


/**
 * A class which allows custom handling of PHP errors.
 *
 * This is a singleton class. There should only be one instance
 * of it --- you can access the one instance via $GLOBALS['ErrorManager'].
 *
 * FIXME: more docs.
 */ 
class ErrorManager 
{
    /**
     * Constructor.
     *
     * As this is a singleton class, you should never call this.
     * @access private
     */
    function ErrorManager() {
        $this->_handlers = array();
        $this->_fatal_handler = false;
        $this->_postpone_mask = 0;
        $this->_postponed_errors = array();

        set_error_handler('ErrorManager_errorHandler');
    }

    /**
     * Get mask indicating which errors are currently being postponed.
     * @access public
     * @return int The current postponed error mask.
     */
    function getPostponedErrorMask() {
        return $this->_postpone_mask;
    }

    /**
     * Set mask indicating which errors to postpone.
     *
     * The default value of the postpone mask is zero (no errors postponed.)
     *
     * When you set this mask, any queue errors which do not match tne new
     * mask are reported.
     *
     * @access public
     * @param $newmask int The new value for the mask.
     */
    function setPostponedErrorMask($newmask) {
        $this->_postpone_mask = $newmask;
        $this->_flush_errors($newmask);
    }

    /**
     * Report any queued error messages.
     * @access public
     */
    function flushPostponedErrors() {
        $this->_flush_errors();
    }

    /**
     * Get postponed errors, formatted as HTML.
     *
     * This also flushes the postponed error queue.
     *
     * @return string HTML describing any queued errors. 
     */
    function getPostponedErrorsAsHTML() {
        ob_start();
        $this->flushPostponedErrors();
        $html = ob_get_contents();
        ob_end_clean();

        if (!$html)
            return false;
        
        return Element('div', array('class' => 'errors'),
                       QElement('h4', sprintf(_("PHP %s Warnings"),
                                              PHP_VERSION))
                       . $html);
    }
    
    /**
     * Push a custom error handler on the handler stack.
     *
     * Sometimes one is performing an operation where one expects
     * certain errors or warnings. In this case, one might not want
     * these errors reported in the normal manner. Installing a custom
     * error handler via this method allows one to intercept such
     * errors.
     *
     * An error handler installed via this method should be either a
     * function or an object method taking one argument: a PhpError
     * object.
     *
     * The error handler should return either:
     * <dl>
     * <dt> False <dd> If it has not handled the error. In this case,
     *                 error processing will proceed as if the handler
     *                 had never been called: the error will be passed
     *                 to the next handler in the stack, or the
     *                 default handler, if there are no more handlers
     *                 in the stack.
     *
     * <dt> True <dd> If the handler has handled the error. If the
     *                error was a non-fatal one, no further processing
     *                will be done. If it was a fatal error, the
     *                ErrorManager will still terminate the PHP
     *                process (see setFatalHandler.)
     *
     * <dt> A PhpError object <dd> The error is not considered
     *                             handled, and will be passed on to
     *                             the next handler(s) in the stack
     *                             (or the default handler). The
     *                             returned PhpError need not be the
     *                             same as the one passed to the
     *                             handler. This allows the handler to
     *                             "adjust" the error message.
     * </dl>
     * @access public
     * @param $handler WikiCallback  Handler to call.
     */
    function pushErrorHandler($handler) {
        array_unshift($this->_handlers, $handler);
    }

    /**
     * Pop an error handler off the handler stack.
     * @access public
     */
    function popErrorHandler() {
        return array_shift($this->_handlers);
    }

    /**
     * Set a termination handler.
     *
     * This handler will be called upon fatal errors. The handler
     * gets passed one argument: a PhpError object describing the
     * fatal error.
     *
     * @access public
     * @param $handler WikiCallback  Callback to call on fatal errors.
     */
    function setFatalHandler($handler) {
        $this->_fatal_handler = $handler;
    }

    /**
     * Handle an error.
     *
     * The error is passed through any registered error handlers, and
     * then either reported or postponed.
     *
     * @access public
     * @param $error object A PhpError object.
     */
    function handleError($error) {
        static $in_handler;

        if (!empty($in_handler)) {
            echo "<p>ErrorManager: "._("error while handling error:")."</p>\n";
            echo $error->printError();
            return;
        }
        $in_handler = true;

        foreach ($this->_handlers as $handler) {
            $result = $handler->call($error);
            if (!$result) {
                continue;       // Handler did not handle error.
            }
            elseif (is_object($result)) {
                // handler filtered the result. Still should pass to
                // the rest of the chain.
                if ($error->isFatal()) {
                    // Don't let handlers make fatal errors non-fatal.
                    $result->errno = $error->errno;
                }
                $error = $result;
            }
            else {
                // Handler handled error.
                if (!$error->isFatal()) {
                    $in_handler = false;
                    return;
                }
                break;
            }
        }

        // Error was either fatal, or was not handled by a handler.
        // Handle it ourself.
        if ($error->isFatal()) {
            $this->_die($error);
        }
        else if (($error->errno & error_reporting()) != 0) {
            if  (($error->errno & $this->_postpone_mask) != 0) {
                $this->_postponed_errors[] = $error;
            }
            else {
                $error->printError();
            }
        }
        $in_handler = false;
    }

    /**
     * @access private
     */
    function _die($error) {
        $error->printError();
        $this->_flush_errors();
        if ($this->_fatal_handler)
            $this->_fatal_handler->call($error);
        exit -1;
    }

    /**
     * @access private
     */
    function _flush_errors($keep_mask = 0) {
        $errors = &$this->_postponed_errors;
        foreach ($errors as $key => $error) {
            if (($error->errno & $keep_mask) != 0)
                continue;
            unset($errors[$key]);
            $error->printError();
        }
    }
}

/**
 * Global error handler for class ErrorManager.
 *
 * This is necessary since PHP's set_error_handler() does not allow
 * one to set an object method as a handler.
 * 
 * @access private
 */
function ErrorManager_errorHandler($errno, $errstr, $errfile, $errline) 
{
    global $ErrorManager;
    $error = new PhpError($errno, $errstr, $errfile, $errline);
    $ErrorManager->handleError($error);
}


/**
 * A class representing a PHP error report.
 *
 * @see The PHP documentation for set_error_handler at
 *      http://php.net/manual/en/function.set-error-handler.php .
 */
class PhpError {
    /**
     * The PHP errno
     */
    var $errno;

    /**
     * The PHP error message.
     */
    var $errstr;

    /**
     * The source file where the error occurred.
     */
    var $errfile;

    /**
     * The line number (in $this->errfile) where the error occured.
     */
    var $errline;

    /**
     * Construct a new PhpError.
     * @param $errno   int
     * @param $errstr  string
     * @param $errfile string
     * @param $errline int
     */
    function PhpError($errno, $errstr, $errfile, $errline) {
        $this->errno   = $errno;
        $this->errstr  = $errstr;
        $this->errfile = $errfile;
        $this->errline = $errline;
    }

    /**
     * Determine whether this is a fatal error.
     * @return boolean True if this is a fatal error.
     */
    function isFatal() {
        return ($this->errno & (EM_WARNING_ERRORS|EM_NOTICE_ERRORS)) == 0;
    }

    /**
     * Determine whether this is a warning level error.
     * @return boolean
     */
    function isWarning() {
        return ($this->errno & EM_WARNING_ERRORS) != 0;
    }

    /**
     * Determine whether this is a notice level error.
     * @return boolean
     */
    function isNotice() {
        return ($this->errno & EM_NOTICE_ERRORS) != 0;
    }

    /**
     * Get a printable, HTML, message detailing this error.
     * @return string The detailed error message.
     */
    function getDetail() {
        if ($this->isNotice())
            $what = 'Notice';
        else if ($this->isWarning())
            $what = 'Warning';
        else
            $what = 'Fatal';

        $errfile = ereg_replace('^' . getcwd() . '/', '', $this->errfile);

        $lines = explode("\n", $this->errstr);
        $errstr = htmlspecialchars(array_shift($lines));
        foreach ($lines as $key => $line)
            $lines[$key] = "<li>" . htmlspecialchars($line) . "</li>";
        if ($lines)
            $errstr .= "<ul>\n" . join("\n", $lines) . "\n</ul>";
        
        return sprintf("<p class='error'>%s:%d: %s[%d]: %s</p>\n",
                       htmlspecialchars($errfile),
                       $this->errline, $what, $this->errno,
                       $errstr);
    }

    /**
     * Print an HTMLified version of this error.
     * @see getDetail
     */
    function printError() {
        echo $this->getDetail();
    }
}

if (!isset($GLOBALS['ErrorManager'])) {
    $GLOBALS['ErrorManager'] = new ErrorManager;
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
