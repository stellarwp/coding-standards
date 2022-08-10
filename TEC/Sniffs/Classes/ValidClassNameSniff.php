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
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   CVS: $Id: ValidClassNameSniff.php,v 1.6 2008/05/19 05:59:25 squiz Exp $
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Ensures classes are in camel caps, and the first letter is capitalised
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   Release: 1.2.0RC1
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class ValidClassNameSniff implements Sniff
{
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(
			T_CLASS,
			T_INTERFACE,
		);
	}//end register()

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The current file being processed.
	 * @param int  $stackPtr  The position of the current token in the
	 *                        stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr )
	{
		$tokens = $phpcsFile->getTokens();

		$className = $phpcsFile->findNext( T_STRING, $stackPtr );
		$name      = trim( $tokens[ $className ]['content'] );
		$errorData = array( ucfirst( $tokens[ $stackPtr ]['content'] ) );

		// Make sure the first letter is a capital.
		if ( 0 === preg_match( '|^[A-Z]|', $name ) )
		{
			$error = '%s name must begin with a capital letter';
			$phpcsFile->addError( $error, $stackPtr, 'StartWithCaptial', $errorData );
		}//end if

		// Check that each new word starts with a capital as well, but don't
		// check the first word, as it is checked above.
		$validName = TRUE;
		$nameBits  = explode( '_', $name );
		$firstBit  = array_shift( $nameBits );
		foreach ( $nameBits as $bit )
		{
			if ( '' === $bit ) {
				continue;
			}

			if ( $bit[0] !== strtoupper( $bit[0] ) )
			{
				$validName = false;
				break;
			}//end if
		}//end foreach

		if ( preg_match( '/^GO_Local_.*/', $name ) )
		{
			$validName = TRUE;
		}//end if

		if ( FALSE === $validName )
		{
			// Strip underscores because they cause the suggested name
			// to be incorrect.
			$nameBits = explode( '_', trim( $name, '_' ) );
			$firstBit = array_shift( $nameBits );
			if ( '' === $firstBit )
			{
				$error = '%s name is not valid';
				$phpcsFile->addError( $error, $stackPtr, 'Invalid', $errorData );
			}//end if
			else
			{
				$newName = strtoupper( $firstBit[0] ) . substr( $firstBit, 1 ) . '_';
				foreach ( $nameBits as $bit )
				{
					if ( '' !== $bit )
					{
						$newName .= strtoupper( $bit[0] ) . substr( $bit, 1 ) . '_';
					}//end if
				}//end foreach

				$newName = rtrim( $newName, '_' );
				$error   = '%s name is not valid; consider %s instead';
				$data    = $errorData;
				$data[]  = $newName;

				$phpcsFile->addError( $error, $stackPtr, 'Invalid', $data );
			}//end else
		}//end if

		if ( FALSE === isset( $tokens[ $stackPtr ]['scope_opener'] ) )
		{
			$error  = 'Possible parse error: ';
			$error .= $tokens[ $stackPtr ]['content'];
			$error .= ' missing opening or closing brace';
			$phpcsFile->addWarning( $error, $stackPtr );
			return;
		}//end if
	}//end process
}//end class
