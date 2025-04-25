<?php
/**
 * StellarWP Coding Standards.
 *
 * @package StellarWP\Sniffs\Security
 * @since TBD
 */

namespace TEC\Sniffs\Security;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Checks that functions which require exit or die after are not left without them.
 *
 * @since TBD
 */
class ExitAfterRedirectSniff implements Sniff {
	/**
	 * Functions that need to be followed by an exit.
	 *
	 * @since TBD
	 *
	 * @var array<string>
	 */
	public $functions = [
		'wp_redirect',
		'wp_safe_redirect',
		'wp_doing_ajax',
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since TBD
	 *
	 * @return array<int|string>
	 */
	public function register() {
		return [ T_STRING ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since TBD
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Find the function call.
		$name   = $tokens[ $stackPtr ]['content'];
		$function_name = strtolower( $name );

		if ( ! in_array( $function_name, $this->functions, true ) ) {
			return;
		}

		// Find the opening and closing parenthesis of the function call.
		$open_paren  = $phpcsFile->findNext( Tokens::$emptyTokens, $stackPtr + 1, null, true );
		if ( ! isset( $tokens[ $open_paren ] ) || $tokens[ $open_paren ]['code'] !== T_OPEN_PARENTHESIS ) {
			return;
		}

		// Check if the function call is followed by a semicolon (end of statement).
		$close_paren = $tokens[ $open_paren ]['parenthesis_closer'];
		$next_token  = $phpcsFile->findNext( Tokens::$emptyTokens, $close_paren + 1, null, true );

		// If the next non-empty token is a semicolon, we need to check if an exit follows.
		if ( isset( $tokens[ $next_token ] ) && $tokens[ $next_token ]['code'] === T_SEMICOLON ) {
			// Check if exit follows in the current scope.
			$exit_found = false;
			$start      = $next_token + 1;
			$end        = $phpcsFile->numTokens;

			// If we're in a function or method, only search until the end of the function.
			if ( isset( $tokens[ $stackPtr ]['conditions'] ) ) {
				foreach ( $tokens[ $stackPtr ]['conditions'] as $scope => $type ) {
					if ( in_array( $type, [ T_FUNCTION, T_CLOSURE, T_ANON_CLASS ], true ) ) {
						if ( isset( $tokens[ $scope ]['scope_closer'] ) ) {
							$end = $tokens[ $scope ]['scope_closer'];
						}
						break;
					}
				}
			}

			// Search for exit or die statements.
			for ( $i = $start; $i < $end; $i++ ) {
				// Check for exit or die calls
				if ( isset( $tokens[ $i ] ) ) {
					$token_code = $tokens[ $i ]['code'];
					$token_content = isset( $tokens[ $i ]['content'] ) ? strtolower( $tokens[ $i ]['content'] ) : '';
					
					// Check for exit, die, or return statements
					if (
						$token_code === T_EXIT  
						|| ( $token_code === T_STRING && in_array( $token_content, [ 'die', 'tribe_exit', 'tec_exit' ], true ) )
						|| $token_code === T_RETURN
					) {
						$exit_found = true;
						break;
					}
				}
			}

			if ( ! $exit_found ) {
				$phpcsFile->addError(
					'%s() should be followed by a call to exit; for proper redirection.',
					$stackPtr,
					'NoExit',
					[ $name ]
				);
			}
		}
	}
} 