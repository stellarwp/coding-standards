<?php
namespace TEC\Sniffs\Classes;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

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
class ValidClassNameSniff implements Sniff {
	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			T_CLASS,
			T_INTERFACE,
		);
	}

	/**
	 * Processes the tokens that this sniff is interested in.
	 *
	 * @param File $phpcsFile The current file being processed.
	 * @param int  $stackPtr  The position of the current token in the stack from getTokens().
	 *
	 */
	public function process ( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Get the class name.
		$className = $phpcsFile->findNext( T_STRING, $stackPtr );

		// Check if the class name is in Capitalized_Snake_Case format where each word starts with a capital letter.
		// This also allows for the older double-underscore method of namespacing.
		if ( ! preg_match( '/^(?:[A-Z]+[a-z\d]*)(?:_[A-Z]+[a-z\d]*)*$/', $tokens[ $className ]['content'] ) ) {
			$phpcsFile->addError(
				'Class name "%s" is not in Capitalized_Snake_Case format',
				$stackPtr,
				'NotSnakeCase',
				[ $tokens[ $className ]['content'] ]
			);
		}
	}
}
