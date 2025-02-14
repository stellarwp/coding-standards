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
	public function register() {
		return [ T_DOC_COMMENT_TAG ];
	}

	public function process(File $phpcsFile, $stackPtr) {
		$tokens   = $phpcsFile->getTokens();
		$full_tag = $tokens[ $stackPtr ]['content'];

		// Check if @since or @version is missing space.
		if ( preg_match( '/@(?:since|version)(\S+)/', $full_tag, $matches, PREG_OFFSET_CAPTURE ) ) {
			$error = 'There should be exactly one space after the %s tag.';
			$version = $matches[1][0];
			$tag     = strstr( $full_tag, $version, true );
			$data    = [ $tag ];
			$fix     = $phpcsFile->addFixableError( $error, $stackPtr, 'MissingSpace', $data );

			if ( $fix ) {
				$this->fixSpacing( $phpcsFile, $stackPtr, $tag, $version );
			}
		}
	}

	private function fixSpacing(File $phpcsFile, $stackPtr, $tag, $version) {
		$correctedComment = sprintf('%s %s', $tag, $version);

		$phpcsFile->fixer->beginChangeset();
		$phpcsFile->fixer->replaceToken($stackPtr, $correctedComment);
		$phpcsFile->fixer->endChangeset();
	}
}