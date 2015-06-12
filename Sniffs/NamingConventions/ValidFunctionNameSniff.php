<?php
/**
 * Enforces WordPress function name format, based upon Squiz code
 *
 * PHP version 5
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @author   John Godley <john@urbangiraffe.com>
 */

/**
 * Enforces WordPress array format
 *
 * Blatant copy from WordPress coding standards repo
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @author   John Godley <john@urbangiraffe.com>
 */
class TribalScents_Sniffs_NamingConventions_ValidFunctionNameSniff extends PHP_CodeSniffer_Standards_AbstractScopeSniff
{

    private $_magicMethods = array(
                              'construct',
                              'destruct',
                              'call',
                              'callStatic',
                              'get',
                              'set',
                              'isset',
                              'unset',
                              'sleep',
                              'wakeup',
                              'toString',
                              'set_state',
                              'clone',
                              'invoke'
                             );

    /**
     * A list of all PHP magic functions.
     *
     * @var array
     */
    protected $magicFunctions = array('autoload');

    /**
     * Constructs a PEAR_Sniffs_NamingConventions_ValidFunctionNameSniff.
     */
    public function __construct() {
        parent::__construct(array(T_CLASS, T_INTERFACE, T_TRAIT), array(T_FUNCTION), true);
    }//end __construct()

    /**
     * Processes the tokens within the scope.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     * @param int                  $currScope The position of the current scope.
     *
     * @return void
     */
    protected function processTokenWithinScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $currScope) {
        $className  = $phpcsFile->getDeclarationName($currScope);
        $methodName = $phpcsFile->getDeclarationName($stackPtr);

        // Is this a magic method. IE. is prefixed with "__".
        if (preg_match('|^__|', $methodName) !== 0) {
            $magicPart = substr($methodName, 2);
            if (in_array($magicPart, $this->_magicMethods) === false) {
                 $error = "Method name \"$className::$methodName\" is invalid; only PHP magic methods should be prefixed with a double underscore";
                 $phpcsFile->addError($error, $stackPtr, 'MethodDoubleUnderscore');
            }

            return;
        }

        // PHP4 constructors are allowed to break our rules.
        if ($methodName === $className) {
            return;
        }

        // PHP4 destructors are allowed to break our rules.
        if ($methodName === '_'.$className) {
            return;
        }
    }//end processTokenWithinScope()

    /**
     * Processes the tokens outside the scope.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     *
     * @return void
     */
    protected function processTokenOutsideScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $functionName = $phpcsFile->getDeclarationName($stackPtr);
        if ($functionName === null) {
            // Ignore closures.
            return;
        }

        if (ltrim($functionName, '_') === '') {
            // Ignore special functions.
            return;
        }

        $errorData = array($functionName);

        // Is this a magic function. i.e., it is prefixed with "__".
        if (preg_match('|^__|', $functionName) !== 0) {
            $magicPart = strtolower(substr($functionName, 2));
            if (in_array($magicPart, $this->magicFunctions) === false) {
                 $error = 'Function name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';
                 $phpcsFile->addError($error, $stackPtr, 'FunctionDoubleUnderscore', $errorData);
            }

            return;
        }
    }//end processTokenOutsideScope()
}//end class

