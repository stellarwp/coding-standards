<?php
namespace TEC\Sniffs\Methods;

use PHP_CodeSniffer\Sniffs;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Checks that the method declaration is correct.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: 1.4.0
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class MethodDeclarationSniff extends Sniffs\AbstractScopeSniff
{
    /**
     * Constructs a Squiz_Sniffs_Scope_MethodScopeSniff.
     */
    public function __construct()
    {
        parent::__construct(array(T_CLASS, T_INTERFACE), array(T_FUNCTION));

    }//end __construct()


    /**
     * Processes the function tokens within the class.
     *
     * @param File $phpcsFile The file where this token was found.
     * @param int  $stackPtr  The position where the token was found.
     * @param int  $currScope The current scope opener token.
     *
     * @return void
     */
    protected function processTokenWithinScope(File $phpcsFile, $stackPtr, $currScope)
    {
        $tokens = $phpcsFile->getTokens();

        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null) {
            // Ignore closures.
            return;
        }

        $visibility = 0;
        $static     = 0;
        $abstract   = 0;
        $final      = 0;

        $find   = Tokens::$methodPrefixes;
        $find[] = T_WHITESPACE;
        $prev   = $phpcsFile->findPrevious($find, ($stackPtr - 1), null, true);

        $prefix = $stackPtr;
        while (($prefix = $phpcsFile->findPrevious(Tokens::$methodPrefixes, ($prefix - 1), $prev)) !== false) {
            switch ($tokens[$prefix]['code']) {
            case T_STATIC:
                $static = $prefix;
                break;
            case T_ABSTRACT:
                $abstract = $prefix;
                break;
            case T_FINAL:
                $final = $prefix;
                break;
            default:
                $visibility = $prefix;
                break;
            }
        }

        if ($static !== 0 && $static < $visibility) {
            $error = 'The static declaration must come after the visibility declaration';
            $phpcsFile->addError($error, $static, 'StaticBeforeVisibility');
        }

        if ($visibility !== 0 && $final > $visibility) {
            $error = 'The final declaration must precede the visibility declaration';
            $phpcsFile->addError($error, $final, 'FinalAfterVisibility');
        }

        if ($visibility !== 0 && $abstract > $visibility) {
            $error = 'The abstract declaration must precede the visibility declaration';
            $phpcsFile->addError($error, $abstract, 'AbstractAfterVisibility');
        }

    }//end processTokenWithinScope()

    /**
     * Processes a token that is found outside the scope that this test is
     * listening to.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position in the stack where this
     *                                               token was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return (count($tokens) + 1) to skip
     *                  the rest of the file.
     */
    protected function processTokenOutsideScope(File $phpcsFile, $stackPtr) {}


}//end class
