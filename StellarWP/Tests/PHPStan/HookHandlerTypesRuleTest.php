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
		return new HookHandlerTypesRule( $this->createReflectionProvider(), true );
	}

	public function testRule(): void {
		// This skip is a limitation of the RuleTestCase harness only: PHPStan's
		// bundled php-parser hits a token-emulation bug when parsing fixtures on
		// the PHP 7.4 runtime. The rule itself is PHP 7.4-compatible and runs
		// correctly under a normal `phpstan analyse` on 7.4.
		if ( PHP_VERSION_ID < 80000 ) {
			$this->markTestSkipped( 'Skipped on the PHP 7.4 runtime: PHPStan\'s RuleTestCase php-parser emulation is unreliable here. The rule works on 7.4 under a normal phpstan analyse.' );
		}

		$param  = 'hook arguments are not type-guaranteed (a hook can be dispatched with unexpected types, including null), so a native type can cause a fatal error.';
		$return = 'filter return values are not type-guaranteed and a native return type can cause a fatal error.';

		$this->analyse(
			[ __DIR__ . '/data/hook-handler-types.php' ],
			[
				// Filter, cross-file method: param + return.
				[ 'Handler Hook_Test_Handlers::filter_method() for filter "the_content" must not declare the native type "string" on parameter $content; ' . $param, 13 ],
				[ 'Handler Hook_Test_Handlers::filter_method() for filter "the_content" must not declare a native return type ("string"); ' . $return, 13 ],

				// Action, cross-file method: two params (void return allowed).
				[ 'Handler Hook_Test_Handlers::action_method() for action "save_post" must not declare the native type "int" on parameter $post_id; ' . $param, 16 ],
				[ 'Handler Hook_Test_Handlers::action_method() for action "save_post" must not declare the native type "WP_Post" on parameter $post; ' . $param, 16 ],

				// Filter with several context arguments: every param + return.
				[ 'Handler Hook_Test_Handlers::filter_context_method() for filter "user_has_cap" must not declare the native type "bool" on parameter $has_access; ' . $param, 19 ],
				[ 'Handler Hook_Test_Handlers::filter_context_method() for filter "user_has_cap" must not declare the native type "int" on parameter $post_id; ' . $param, 19 ],
				[ 'Handler Hook_Test_Handlers::filter_context_method() for filter "user_has_cap" must not declare the native type "int" on parameter $user_id; ' . $param, 19 ],
				[ 'Handler Hook_Test_Handlers::filter_context_method() for filter "user_has_cap" must not declare a native return type ("bool"); ' . $return, 19 ],

				// Action with a typed param (void return allowed).
				[ 'Handler Hook_Test_Handlers::action_typed() for action "transition_post_status" must not declare the native type "int" on parameter $id; ' . $param, 22 ],

				// Filter, string class reference to a static method.
				[ 'Handler Hook_Test_Handlers::static_filter() for filter "wp_title" must not declare the native type "string" on parameter $title; ' . $param, 25 ],
				[ 'Handler Hook_Test_Handlers::static_filter() for filter "wp_title" must not declare a native return type ("string"); ' . $return, 25 ],

				// Action closure (void return allowed).
				[ 'Handler for action "init" must not declare the native type "int" on parameter $x; ' . $param, 28 ],

				// Filter closure: both params + return.
				[ 'Handler for filter "login_redirect" must not declare the native type "bool" on parameter $valid; ' . $param, 31 ],
				[ 'Handler for filter "login_redirect" must not declare the native type "int" on parameter $id; ' . $param, 31 ],
				[ 'Handler for filter "login_redirect" must not declare a native return type ("bool"); ' . $return, 31 ],

				// Filter, global function.
				[ 'Handler global_filter() for filter "excerpt_length" must not declare the native type "string" on parameter $length; ' . $param, 36 ],
				[ 'Handler global_filter() for filter "excerpt_length" must not declare a native return type ("string"); ' . $return, 36 ],

				// Non-literal hook name resolved by inference.
				[ 'Handler Hook_Test_Handlers::filter_method() for filter "the_content" must not declare the native type "string" on parameter $content; ' . $param, 43 ],
				[ 'Handler Hook_Test_Handlers::filter_method() for filter "the_content" must not declare a native return type ("string"); ' . $return, 43 ],

				// Container callback: $container->callback( Class::class, 'method' ).
				[ 'Handler Hook_Test_Handlers::container_handler() for filter "render_block" must not declare the native type "int" on parameter $value; ' . $param, 48 ],
				[ 'Handler Hook_Test_Handlers::container_handler() for filter "render_block" must not declare a native return type ("int"); ' . $return, 48 ],

				// Wrapper method: $receiver->add_action( 'tag', 'method' ).
				[ 'Handler Wrapper_Subclass::on_save() for action "save_post" must not declare the native type "int" on parameter $post_id; ' . $param, 53 ],
				[ 'Handler Wrapper_Subclass::filter_it() for filter "the_content" must not declare the native type "string" on parameter $content; ' . $param, 54 ],
				[ 'Handler Wrapper_Subclass::filter_it() for filter "the_content" must not declare a native return type ("string"); ' . $return, 54 ],
			]
		);
	}
}
