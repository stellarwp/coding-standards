<?php
/**
 * Analysed fixture for the HookHandlerTypesRule.
 *
 * @package StellarWP\CodingStandards
 */

// phpcs:disable

$obj = new Hook_Test_Handlers();

// Cross-file method handler (declared in handlers.php): param + return.
add_filter( 'the_content', [ $obj, 'filter_content' ] );

// Cross-file action handler: two param types; void return is allowed.
add_action( 'save_post', [ $obj, 'on_save' ] );

// String class reference to a cross-file static method: param + return.
add_filter( 'wp_title', [ 'Hook_Test_Handlers', 'static_filter' ] );

// First-party filter (matches prefix) - no error.
add_filter( 'learndash_something', [ $obj, 'filter_content' ] );

// First-party action - no error even with types.
add_action( 'ld_after_save', [ $obj, 'typed_ld_handler' ] );

// Inline closure on a non-first-party action: param type; void return allowed.
add_action( 'init', function ( int $x ): void {} );

// Inline closure on a non-first-party filter: param + return type.
add_filter( 'body_class', function ( array $classes ): array {
	return $classes;
} );

// Non-literal hook name that type inference narrows to 'the_content' - the case
// the sniff cannot see. Resolves to a non-first-party hook: param + return.
$hook = 'the_content';
add_filter( $hook, [ $obj, 'filter_content' ] );

// Global-function-name callback with native types - resolved via PHPStan's
// static function reflection: param + return.
add_filter( 'excerpt_length', 'typed_global_handler' );

// Global-function-name callback that is already type-less - no error.
add_filter( 'get_the_excerpt', 'typeless_global_handler' );

// First-party hook with a typed global handler - no error.
add_filter( 'learndash_excerpt', 'typed_global_handler' );

// Unknown/undefined global function - skipped.
add_filter( 'wp_footer', 'some_undefined_handler' );
