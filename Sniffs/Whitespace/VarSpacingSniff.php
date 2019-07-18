<?php
namespace Tribe\TribalScents\Sniffs\Whitespace;

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
 * TribalScents_Sniffs_WhiteSpace_VarSpacingSniff.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    John Godley <john@urbangiraffe.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 */
class VarSpacingSniff implements Sniff
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
			T_ARRAY,
			T_IF,
			T_WHILE,
			T_FOREACH,
			T_FOR,
			T_FUNCTION,
			T_SWITCH,
			T_DO,
			T_ELSEIF,
			T_OPEN_PARENTHESIS,
			T_OPEN_SQUARE_BRACKET,
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

		if (
			FALSE === isset( $token[ 'parenthesis_closer' ] )
			&& FALSE === isset( $token[ 'bracket_closer' ] )
		)
		{
			return;
		}//end if

		if ( ! in_array( $token['code'], array( T_ARRAY, T_FUNCTION, T_OPEN_PARENTHESIS, T_OPEN_SQUARE_BRACKET ) ) )
		{
			if ( ' ' != $tokens[ $stackPtr + 1 ]['content'] )
			{
				$error = "There must be a space between {$token['content']} and the first parenthesis '('";
				$phpcsFile->addError( $error, $stackPtr, 'invalidWhitespace' );
			}//end if
		}//end if

		if ( isset( $token['parenthesis_closer'] ) )
		{
			$after_opener = $tokens[ $token[ 'parenthesis_opener' ] + 1 ];
			$before_closer = $tokens[ $token[ 'parenthesis_closer' ] - 1 ];

			if (
				T_CLOSE_PARENTHESIS != $after_opener['code']
				&& T_WHITESPACE != $after_opener['code']
			)
			{
				$error = 'There must be a single space after the first parenthesis';
				$phpcsFile->addError( $error, $token['parenthesis_opener'], 'invalidWhitespace' );
			}//end if

			if (
				T_OPEN_PARENTHESIS != $before_closer['code']
				&& T_WHITESPACE != $before_closer['code']
			)
			{
				$error = 'There must be a single space before the last parenthesis';
				$phpcsFile->addError( $error, $stackPtr, 'invalidWhitespace' );
			}//end if
		}//end if
		else
		{
			$after_opener = $tokens[ $token[ 'bracket_opener' ] + 1 ];
			$before_closer = $tokens[ $token[ 'bracket_closer' ] - 1 ];

			if (
				T_CLOSE_SQUARE_BRACKET != $after_opener['code']
				&& T_LNUMBER != $after_opener['code']
				&& T_WHITESPACE != $after_opener['code']
				&& T_CONSTANT_ENCAPSED_STRING != $after_opener['code']
			)
			{
				$error = 'There must be a single space after the first square bracket';
				$phpcsFile->addError( $error, $stackPtr, 'invalidWhitespace' );
			}//end if

			if (
				T_OPEN_SQUARE_BRACKET != $before_closer['code']
				&& T_LNUMBER != $before_closer['code']
				&& T_WHITESPACE != $before_closer['code']
				&& T_CONSTANT_ENCAPSED_STRING != $before_closer['code']
			)
			{
				$error = 'There must be a single space before the last square bracket';
				$phpcsFile->addError( $error, $stackPtr, 'invalidWhitespace' );
			}//end if
		}//end else
	}//end process
}//end class
