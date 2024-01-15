<?php
namespace TEC\Sniffs\Arrays;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * This is a shameless copy of the work done by Squizlabs, specifically
 * Greg Sherwood <gsherwood@squiz.net> and Marc McIntyre <mmcintyre@squiz.net>,
 * but modified to match The Events Calendar standards.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @author    Zachary Tirrell <zbtirrell@gmail.com>
 * @copyright 2012 The Events Calendar
 * @license   https://github.com/the-events-calendar/coding-standards/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Squiz_Sniffs_Arrays_ArrayBracketSpacingSniff.
 *
 * Ensure that there are no spaces around square brackets.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @author    Zachary Tirrell <zbtirrell@gmail.com>
 * @copyright 2012 The Events Calendar
 * @license   https://github.com/the-events-calendar/coding-standards/blob/master/licence.txt BSD Licence
 * @version   Release: 1.4.0
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class ArrayBracketSpacingSniff implements Sniff
{
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(
			T_OPEN_SQUARE_BRACKET,
			T_CLOSE_SQUARE_BRACKET,
		);

	}//end register()


	/**
	 * Processes this sniff, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The current file being checked.
	 * @param int  $stackPtr  The position of the current token in the
	 *                        stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process(File $phpcsFile, $stackPtr)
	{
		$tokens = $phpcsFile->getTokens();

		// PHP 5.4 introduced a shorthand array declaration syntax, so we need
		// to ignore the these type of array declarations because this sniff is
		// only dealing with array usage.
		if ($tokens[$stackPtr]['code'] === T_OPEN_SQUARE_BRACKET) {
			$openBracket = $stackPtr;
		} else {
			$openBracket = $tokens[$stackPtr]['bracket_opener'];
		}

		$prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openBracket - 1), null, true);
		if ($tokens[$prev]['code'] === T_EQUAL) {
			return;
		}

		// Square brackets can not have a space before them.
		$prevType = $tokens[($stackPtr - 1)]['code'];
		if ('[' == $tokens[$stackPtr]['content'] && in_array($prevType, Tokens::$emptyTokens) === true) {
			$nonSpace = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 2), null, true);
			$expected = $tokens[$nonSpace]['content'].$tokens[$stackPtr]['content'];
			$found    = $phpcsFile->getTokensAsString($nonSpace, ($stackPtr - $nonSpace)).$tokens[$stackPtr]['content'];
			$error    = 'Space found before square bracket; expected "%s" but found "%s"';
			$data     = array(
				$expected,
				$found,
			);
			$phpcsFile->addError($error, $stackPtr, 'SpaceBeforeBracket', $data);
		}
	}//end process()
}//end class
