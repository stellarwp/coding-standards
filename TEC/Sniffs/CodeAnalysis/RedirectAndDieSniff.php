<?php
namespace TEC\Sniffs\CodeAnalysis;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Throw an error if error loggins functions are in use
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @copyright 2014 The Events Calendar
 * @license   https://github.com/the-events-calendar/coding-standards/blob/master/licence.txt BSD Licence
 * @version   Release: 1.4.0
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class RedirectAndDieSniff implements Sniff
{
	/**
	 * A list of tokenizers this sniff supports.
	 *
	 * @var array
	 */
	public $supportedTokenizers = array(
		'PHP',
	);

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(
			T_STRING,
		);
	}//end register

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in
	 *                        the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr )
	{
		$tokens = $phpcsFile->getTokens();
		$token = $tokens[ $stackPtr ];
		$function = strtolower( $token['content'] );

		$ignore = array(
			T_DOUBLE_COLON,
			T_OBJECT_OPERATOR,
			T_FUNCTION,
			T_CONST,
		);

		$prevToken = $phpcsFile->findPrevious( T_WHITESPACE, ( $stackPtr - 1 ), null, true );

		if ( in_array( $tokens[ $prevToken ]['code'], $ignore ) === true )
		{
			// Not a call to a PHP function.
			return;
		}//end if

		if ( ! in_array( $function, array( 'wp_redirect', 'wp_safe_redirect' ) ) )
		{
			return;
		}//end if

		$semicolon = $phpcsFile->findNext( T_SEMICOLON, $stackPtr );
		$next = $tokens[ $phpcsFile->findNext( T_WHITESPACE, $semicolon + 1, NULL, TRUE ) ];

		if ( T_EXIT == $next['code'] || 'tribe_exit' == $next['content'] )
		{
			return;
		}//end if

		$data = array( $function );
		$type  = 'Error';
		$error = "die must follow {$function}";
		$phpcsFile->addError( $error, $stackPtr, $type, $data );
	}//end process
}//end class
