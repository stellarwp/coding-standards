<?php
/**
 * Unit test for the HookHandlerTypes sniff.
 *
 * @package StellarWP\CodingStandards
 */

namespace StellarWP\Tests\Hooks;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * The prefixes property is configured via a `// phpcs:set` annotation at the top
 * of the .inc test case file. Expected error lines are keyed to that file.
 */
class HookHandlerTypesUnitTest extends AbstractSniffUnitTest {

	/**
	 * Returns the lines where errors should occur, keyed by line number.
	 *
	 * @return array<int, int>
	 */
	protected function getErrorList() {
		return [
			12 => 1, // Closure on 'init': native param type (void return is allowed).
			15 => 2, // Closure on 'body_class': native param type + native return type.
			48 => 3, // Closure on 'pre_get_posts': nullable, by-reference, variadic params.
			51 => 1, // Closure on 'the_title': native param type.
			52 => 1, // Closure on 'the_title': native return type (on its own line).
			58 => 2, // filter_content(): param + return (via the 'the_content' binding).
			64 => 2, // on_save(): two native param types (via the 'save_post' binding).
			66 => 2, // static_filter(): param + return (via the 'wp_title' self::class binding).
			71 => 2, // global_excerpt_handler(): param + return (via the same-file 'excerpt_length' binding).
		];
	}

	/**
	 * Returns the lines where warnings should occur, keyed by line number.
	 *
	 * @return array<int, int>
	 */
	protected function getWarningList() {
		return [];
	}
}
