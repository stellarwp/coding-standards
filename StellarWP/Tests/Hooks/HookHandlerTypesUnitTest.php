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
			22 => 1, // init closure (non-first-party action): param; void return allowed.
			25 => 3, // ld_valid closure (first-party filter): two params + return.
			30 => 3, // pre_get_posts closure (non-first-party action): nullable, by-ref, variadic.
			49 => 2, // np_filter() (non-first-party filter): param + return.
			53 => 2, // np_action() (non-first-party action): two params; void return allowed.
			55 => 4, // fp_filter() (first-party filter): all three params + return.
			61 => 1, // np_ref_action() (non-first-party action, via [ &$this, ... ]): param.
			63 => 2, // fp_static_filter() (first-party filter, via self::class): param + return.
			68 => 2, // np_global_filter() (non-first-party filter, global function): param + return.
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
