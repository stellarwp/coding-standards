<?php
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
 * ModernTribe_Sniffs_WhiteSpace_LogicalNotSpacingSniff.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    John Godley <john@urbangiraffe.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 */
class ModernTribe_Sniffs_WhiteSpace_LogicalNotSpacingSniff implements PHP_CodeSniffer_Sniff
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
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token in the
	 *                                        stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( PHP_CodeSniffer_File $phpcsFile, $stackPtr )
	{
		$tokens = $phpcsFile->getTokens();
		$token = $tokens[ $stackPtr ];

		if ( T_WHITESPACE === $tokens[ $stackPtr + 1 ]['code'] )
		{
			return;
		}//end if

		$phpcsFile->addError( '! must be followed by a single space', $stackPtr );
	}//end process
}//end class
