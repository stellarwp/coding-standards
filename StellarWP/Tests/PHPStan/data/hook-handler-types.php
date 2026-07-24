<?php
/**
 * Analysed fixture for the HookHandlerTypesRule.
 *
 * @package StellarWP\CodingStandards
 */

// phpcs:disable

$obj = new Hook_Test_Handlers();

// Non-first-party filter, cross-file method: param + return.
add_filter( 'the_content', [ $obj, 'np_filter' ] );

// Non-first-party action, cross-file method: two params; void return allowed.
add_action( 'save_post', [ $obj, 'np_action' ] );

// First-party filter, cross-file method: all three params + return (a context
// argument such as the user id can be dispatched as null by core).
add_filter( 'learndash_has_access', [ $obj, 'fp_filter' ] );

// First-party action, cross-file method: unrestricted - no error.
add_action( 'learndash_after_save', [ $obj, 'fp_action' ] );

// First-party filter, string class reference to a static method: param + return.
add_filter( 'sfwd_lms_has_access', [ 'Hook_Test_Handlers', 'fp_static_filter' ] );

// Non-first-party action, closure: param; void return allowed.
add_action( 'init', function ( int $x ): void {} );

// First-party filter, closure: two params + return.
add_filter( 'ld_valid', function ( bool $valid, int $id ): bool {
	return $valid;
} );

// Non-first-party filter, global function: param + return.
add_filter( 'excerpt_length', 'np_global_filter' );

// Already type-less global function - no error.
add_filter( 'get_the_excerpt', 'typeless_global_filter' );

// Non-literal hook name resolved by inference to a non-first-party filter.
$hook = 'the_content';
add_filter( $hook, [ $obj, 'np_filter' ] );

// Unknown/undefined global function - skipped.
add_filter( 'wp_footer', 'some_undefined_handler' );
