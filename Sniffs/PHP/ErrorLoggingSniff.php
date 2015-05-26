<?php
/**
 * ModernTribe_Sniffs_PHP_ErrorLoggingSniff
 *
 * Throw an error if error loggins functions are in use
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @copyright 2014 ModernTribe
 * @license   https://github.com/ModernTribe/ModernTribe-codesniffer/blob/master/licence.txt BSD Licence
 * @version   Release: 1.4.0
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class ModernTribe_Sniffs_PHP_ErrorLoggingSniff implements PHP_CodeSniffer_Sniff
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
	 * A list of forbidden functions with their alternatives.
	 *
	 * The value is NULL if no alternative exists. i.e. the
	 * function should just not be used.
	 *
	 * @var array(string => string|null)
	 */
	protected $forbidden = array(
		'error_log',
		'wlog',
		'golog',
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
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token in
	 *                                        the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( PHP_CodeSniffer_File $phpcsFile, $stackPtr )
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

		if ( 'do_action' == $function )
		{
			$next = $phpcsFile->findNext( T_CONSTANT_ENCAPSED_STRING, $stackPtr );

			if ( ! preg_match( '/("|\')debug_robot("|\')/', $tokens[ $next ]['content'] ) )
			{
				return;
			}//end if

			$data = array( $function );
			$type  = 'Found';
			$error = "Don't leave debug_robot lying around";
			$phpcsFile->addError( $error, $stackPtr, $type, $data );
			return;
		}//end if
		elseif ( FALSE === in_array( $function, $this->forbidden ) )
		{
			return;
		}//end elseif

		$data = array( $function );
		$type  = 'Found';
		$error = "Don't leave {$function} lying around";
		$phpcsFile->addError( $error, $stackPtr, $type, $data );
	}//end process
}//end class
