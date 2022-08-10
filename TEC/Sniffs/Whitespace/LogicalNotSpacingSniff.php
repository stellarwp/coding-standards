<?php
namespace TEC\Sniffs\Whitespace;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Enforces spacing around logical operators and assignments, based upon Squiz code
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    John Godley <john@urbangiraffe.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 */

/**
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    John Godley <john@urbangiraffe.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 */
class LogicalNotSpacingSniff implements Sniff
{
	/**
	 * A list of tokenizers this sniff supports.
	 *
	 * @var array
	 */
	public $supportedTokenizers = array(
		'PHP',
		'JS',
	);

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(
			T_BOOLEAN_NOT,
		);
	}//end register

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the
	 *                        stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr )
	{
		$tokens = $phpcsFile->getTokens();
		$token = $tokens[ $stackPtr ];

		if ( T_WHITESPACE === $tokens[ $stackPtr + 1 ]['code'] )
		{
			return;
		}//end if

		$phpcsFile->addError( '! must be followed by a single space', $stackPtr, 'invalidWhitespace' );
	}//end process
}//end class
