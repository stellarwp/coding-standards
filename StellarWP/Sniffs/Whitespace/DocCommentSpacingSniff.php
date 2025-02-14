<?php

namespace StellarWP\Sniffs\Whitespace;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Class DocCommentSpacingSniff
 *
 * @package StellarWP\Sniffs\Whitespace
 */
final class DocCommentSpacingSniff implements Sniff {
	/**
	 * Register the sniff.
	 *
	 * @return array<string>
	 */
	public function register() {
		return [ T_DOC_COMMENT_TAG ];
	}

	/**
	 * Process the doc comment.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens   = $phpcsFile->getTokens();
		$full_tag = $tokens[ $stackPtr ]['content'];

		// Check if @since or @version is missing space.
		if ( preg_match( '/@(?:since|version)(\S+)/', $full_tag, $matches, PREG_OFFSET_CAPTURE ) ) {
			$error   = 'There should be exactly one space after the %s tag.';
			$version = $matches[1][0];
			$tag     = strstr( $full_tag, $version, true );
			$data    = [ $tag ];
			$fix     = $phpcsFile->addFixableError( $error, $stackPtr, 'MissingSpace', $data );

			if ( $fix ) {
				$this->fixSpacing( $phpcsFile, $stackPtr, $tag, $version );
			}
		}

		// Check for the extra whitespace after @since or @version.
		$next_token = $tokens[ $stackPtr + 1 ];

		if ( $next_token["type"] !== "T_DOC_COMMENT_WHITESPACE" ) {
			return;
		}

		$whitespace = $next_token['content'];

		// Check if @since or @version is followed by more than one space
		if ( strlen( $whitespace ) > 1 ) {
			$error = 'There should be exactly one space after the %s tag, not multiple.';
			$fix   = $phpcsFile->addFixableError( $error, $stackPtr, 'ExtraSpaces', $full_tag );

			if ( $fix ) {
				$this->fixMultipleSpaces( $phpcsFile, $stackPtr + 1 );
			}
		}
	}

	/**
	 * Fix the spacing issue in the doc comment.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 * @param string $tag     The tag name.
	 * @param string $version The version number.
	 */
	private function fixSpacing( File $phpcsFile, $stackPtr, $tag, $version ) {
		$correctedComment = sprintf( '%s %s', $tag, $version );

		$phpcsFile->fixer->beginChangeset();
		$phpcsFile->fixer->replaceToken( $stackPtr, $correctedComment );
		$phpcsFile->fixer->endChangeset();
	}

	/**
	 * Fix the multiple space trails in the doc comment.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 */
	private function fixMultipleSpaces( File $phpcsFile, $stackPtr ) {
		$phpcsFile->fixer->beginChangeset();
		$phpcsFile->fixer->replaceToken( $stackPtr, " " );
		$phpcsFile->fixer->endChangeset();
	}
}
