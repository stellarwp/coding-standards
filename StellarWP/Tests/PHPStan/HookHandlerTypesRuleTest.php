<?php
/**
 * Rule test for HookHandlerTypesRule.
 *
 * @package StellarWP\CodingStandards
 */

namespace StellarWP\Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StellarWP\PHPStan\HookHandlerTypesRule;

/**
 * @extends RuleTestCase<HookHandlerTypesRule>
 */
class HookHandlerTypesRuleTest extends RuleTestCase {

	/**
	 * Loads the cross-file handler classes so the rule can reflect them, exactly
	 * as they would be autoloadable in a real analysed project.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/data/handlers.php';
	}

	protected function getRule(): Rule {
		return new HookHandlerTypesRule(
			$this->createReflectionProvider(),
			[ 'learndash', 'ld_', 'sfwd' ],
			true
		);
	}

	public function testRule(): void {
		// This skip is a limitation of the RuleTestCase harness only: PHPStan's
		// bundled php-parser hits a token-emulation bug when parsing fixtures on
		// the PHP 7.4 runtime. The rule itself is PHP 7.4-compatible and runs
		// correctly under a normal `phpstan analyse` on 7.4.
		if ( PHP_VERSION_ID < 80000 ) {
			$this->markTestSkipped( 'Skipped on the PHP 7.4 runtime: PHPStan\'s RuleTestCase php-parser emulation is unreliable here. The rule works on 7.4 under a normal phpstan analyse.' );
		}

		$filter_param  = 'a filter can be dispatched with arguments of unexpected types (including null), so a native type can cause a fatal error.';
		$filter_return = 'filter return values are not type-guaranteed and a native return type can cause a fatal error.';
		$action_param  = 'WordPress does not guarantee hook argument types and a native type can cause a fatal error.';

		$this->analyse(
			[ __DIR__ . '/data/hook-handler-types.php' ],
			[
				// Non-first-party filter, cross-file method.
				[ 'Handler Hook_Test_Handlers::np_filter() for filter "the_content" must not declare the native type "string" on parameter $content; ' . $filter_param, 13 ],
				[ 'Handler Hook_Test_Handlers::np_filter() for filter "the_content" must not declare a native return type ("string"); ' . $filter_return, 13 ],

				// Non-first-party action, cross-file method (void return allowed).
				[ 'Handler Hook_Test_Handlers::np_action() for non-first-party action "save_post" must not declare the native type "int" on parameter $post_id; ' . $action_param, 16 ],
				[ 'Handler Hook_Test_Handlers::np_action() for non-first-party action "save_post" must not declare the native type "WP_Post" on parameter $post; ' . $action_param, 16 ],

				// First-party filter, cross-file method: every param + return.
				[ 'Handler Hook_Test_Handlers::fp_filter() for filter "learndash_has_access" must not declare the native type "bool" on parameter $has_access; ' . $filter_param, 20 ],
				[ 'Handler Hook_Test_Handlers::fp_filter() for filter "learndash_has_access" must not declare the native type "int" on parameter $post_id; ' . $filter_param, 20 ],
				[ 'Handler Hook_Test_Handlers::fp_filter() for filter "learndash_has_access" must not declare the native type "int" on parameter $user_id; ' . $filter_param, 20 ],
				[ 'Handler Hook_Test_Handlers::fp_filter() for filter "learndash_has_access" must not declare a native return type ("bool"); ' . $filter_return, 20 ],

				// First-party filter, string class reference to a static method.
				[ 'Handler Hook_Test_Handlers::fp_static_filter() for filter "sfwd_lms_has_access" must not declare the native type "string" on parameter $title; ' . $filter_param, 26 ],
				[ 'Handler Hook_Test_Handlers::fp_static_filter() for filter "sfwd_lms_has_access" must not declare a native return type ("string"); ' . $filter_return, 26 ],

				// Non-first-party action, closure (void return allowed).
				[ 'Handler for non-first-party action "init" must not declare the native type "int" on parameter $x; ' . $action_param, 29 ],

				// First-party filter, closure: both params + return.
				[ 'Handler for filter "ld_valid" must not declare the native type "bool" on parameter $valid; ' . $filter_param, 32 ],
				[ 'Handler for filter "ld_valid" must not declare the native type "int" on parameter $id; ' . $filter_param, 32 ],
				[ 'Handler for filter "ld_valid" must not declare a native return type ("bool"); ' . $filter_return, 32 ],

				// Non-first-party filter, global function.
				[ 'Handler np_global_filter() for filter "excerpt_length" must not declare the native type "string" on parameter $length; ' . $filter_param, 37 ],
				[ 'Handler np_global_filter() for filter "excerpt_length" must not declare a native return type ("string"); ' . $filter_return, 37 ],

				// Non-literal hook name resolved by inference.
				[ 'Handler Hook_Test_Handlers::np_filter() for filter "the_content" must not declare the native type "string" on parameter $content; ' . $filter_param, 44 ],
				[ 'Handler Hook_Test_Handlers::np_filter() for filter "the_content" must not declare a native return type ("string"); ' . $filter_return, 44 ],
			]
		);
	}
}
