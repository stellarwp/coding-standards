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

		$this->analyse(
			[ __DIR__ . '/data/hook-handler-types.php' ],
			[
				[
					'Handler Hook_Test_Handlers::filter_content() for non-first-party hook "the_content" must not declare the native type "string" on parameter $content; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					13,
				],
				[
					'Handler Hook_Test_Handlers::filter_content() for non-first-party filter "the_content" must not declare a native return type ("string"); filter return values are not type-guaranteed and a native return type can cause a fatal error.',
					13,
				],
				[
					'Handler Hook_Test_Handlers::on_save() for non-first-party hook "save_post" must not declare the native type "int" on parameter $post_id; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					16,
				],
				[
					'Handler Hook_Test_Handlers::on_save() for non-first-party hook "save_post" must not declare the native type "WP_Post" on parameter $post; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					16,
				],
				[
					'Handler Hook_Test_Handlers::static_filter() for non-first-party hook "wp_title" must not declare the native type "string" on parameter $title; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					19,
				],
				[
					'Handler Hook_Test_Handlers::static_filter() for non-first-party filter "wp_title" must not declare a native return type ("string"); filter return values are not type-guaranteed and a native return type can cause a fatal error.',
					19,
				],
				[
					'Handler for non-first-party hook "init" must not declare the native type "int" on parameter $x; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					28,
				],
				[
					'Handler for non-first-party hook "body_class" must not declare the native type "array" on parameter $classes; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					31,
				],
				[
					'Handler for non-first-party filter "body_class" must not declare a native return type ("array"); filter return values are not type-guaranteed and a native return type can cause a fatal error.',
					31,
				],
				[
					'Handler Hook_Test_Handlers::filter_content() for non-first-party hook "the_content" must not declare the native type "string" on parameter $content; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					38,
				],
				[
					'Handler Hook_Test_Handlers::filter_content() for non-first-party filter "the_content" must not declare a native return type ("string"); filter return values are not type-guaranteed and a native return type can cause a fatal error.',
					38,
				],
				[
					'Handler typed_global_handler() for non-first-party hook "excerpt_length" must not declare the native type "string" on parameter $value; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					42,
				],
				[
					'Handler typed_global_handler() for non-first-party filter "excerpt_length" must not declare a native return type ("string"); filter return values are not type-guaranteed and a native return type can cause a fatal error.',
					42,
				],
			]
		);
	}
}
