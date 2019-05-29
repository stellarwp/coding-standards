<?php
/**
 * Enforces WordPress array format, based upon Squiz code
 *
 * PHP version 5
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @author   John Godley <john@urbangiraffe.com>
 * @author   Greg Sherwood <gsherwood@squiz.net>
 * @author   Marc McIntyre <mmcintyre@squiz.net>
 */

/**
 * Enforces WordPress array format
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @author   John Godley <john@urbangiraffe.com>
 * @author   Greg Sherwood <gsherwood@squiz.net>
 * @author   Marc McIntyre <mmcintyre@squiz.net>
 */
class TribalScents_Sniffs_CodeAnalysis_EmptyIssetSniff implements PHP_CodeSniffer_Sniff {
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			T_ISSET,
		);
	}//end register()

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token
	 *                                        in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( PHP_CodeSniffer_File $phpcsFile, $stackPtr ) {
		$tokens  = $phpcsFile->getTokens();
		$token = $tokens[ $stackPtr ];
		$keyword = $token['content'];

		$next_token = $phpcsFile->findNext( T_WHITESPACE, $stackPtr + 2, null, true );
		$open_token = $phpcsFile->findNext( T_CLOSE_PARENTHESIS, $stackPtr + 1, null, true );
		$last_token = $phpcsFile->findPrevious( T_WHITESPACE, $tokens[ $open_token ]['parenthesis_closer'] - 1, null, true );

		if ( 0 === strpos( $tokens[ $next_token ]['content'], '$' ) && 'T_CLOSE_PARENTHESIS' !== $tokens[ $last_token ]['type'] ) {
			return;
		}

		$phpcsFile->addError( 'isset() must only contain variables (PHP 5.6 compatibility)', $stackPtr, 'Error', 'NonVarEmpty' );
	}//end process()
}//end class
