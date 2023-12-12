<?php
namespace TEC\Sniffs\Classes;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Modified Squiz_Sniffs_Classes_ValidClassNameSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD License
 * @version   CVS: $Id: ValidClassNameSniff.php,v 1.6 2008/05/19 05:59:25 squiz Exp $
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Ensures classes are in Capitalized_Snake_Case format
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    The Events Calendar
 */
/**
 * Custom sniff to enforce Capitalized_Snake_Case class names.
 */
class ValidClassNameSniff implements PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int $stackPtr The position in the stack where this token was found.
     */
    public function process (PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr )
    {
        $tokens = $phpcsFile->getTokens();

        // Get the class name
        $className = $phpcsFile->findNext(T_STRING, $stackPtr);

        // Check if the class name is in Snake_Case format where each word starts with a capital letter.
		// This also allows for the older double-underscore method of namespacing.
        if ( ! preg_match( '/^(?:[A-Z]+[a-z]*){1}(?:_+[A-Z]+[a-z]*]*)+$/', $tokens[$className]['content'] ) ) {
            $error = 'Class name "%s" is not in snake_case format';
            $data = [$tokens[$className]['content']];
            $phpcsFile->addError($error, $stackPtr, 'NotSnakeCase', $data);
        }
    }
}
