<?php
/**
 * Unit test for the HookHandlerTypes sniff.
 *
 * @package StellarWP\CodingStandards
 */

namespace StellarWP\Tests\Hooks;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Expected error lines are keyed to the .inc test case file.
 */
class HookHandlerTypesUnitTest extends AbstractSniffUnitTest {

	/**
	 * Returns the lines where errors should occur, keyed by line number.
	 *
	 * @return array<int, int>
	 */
	protected function getErrorList() {
		return [
			19 => 1, // init action closure: param; void return allowed.
			22 => 3, // login_redirect filter closure: two params + return.
			27 => 3, // pre_get_posts action closure: nullable, by-ref, variadic params.
			50 => 2, // filter_method(): param + return.
			54 => 2, // action_method(): two params; void return allowed.
			56 => 4, // filter_context_method(): all three params + return.
			60 => 1, // action_typed(): the native param (an action still forbids param types).
			62 => 1, // ref_action() (via [ &$this, ... ]): param.
			64 => 2, // static_filter() (via self::class): param + return.
			68 => 1, // wrapper_action() (via $this->add_action( 'save_post', 'wrapper_action' )): param.
			70 => 2, // wrapper_filter() (via $this->add_filter( 'the_title', 'wrapper_filter' )): param + return.
			75 => 2, // global_filter() (global function): param + return.
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
