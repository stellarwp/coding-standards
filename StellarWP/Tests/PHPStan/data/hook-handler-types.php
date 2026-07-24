<?php
/**
 * Analysed fixture for the HookHandlerTypesRule.
 *
 * @package StellarWP\CodingStandards
 */

// phpcs:disable

$obj = new Hook_Test_Handlers();

// Filter, cross-file method: param + return.
add_filter( 'the_content', [ $obj, 'filter_method' ] );

// Action, cross-file method: two params; void return allowed.
add_action( 'save_post', [ $obj, 'action_method' ] );

// Filter with several context arguments, cross-file method: all params + return.
add_filter( 'user_has_cap', [ $obj, 'filter_context_method' ] );

// Action with a typed param, cross-file method: the param is flagged (void ok).
add_action( 'transition_post_status', [ $obj, 'action_typed' ] );

// Filter, string class reference to a static method: param + return.
add_filter( 'wp_title', [ 'Hook_Test_Handlers', 'static_filter' ] );

// Action closure: param; void return allowed.
add_action( 'init', function ( int $x ): void {} );

// Filter closure: two params + return.
add_filter( 'login_redirect', function ( bool $valid, int $id ): bool {
	return $valid;
} );

// Filter, global function: param + return.
add_filter( 'excerpt_length', 'global_filter' );

// Already type-less global function - no error.
add_filter( 'get_the_excerpt', 'typeless_global_filter' );

// Non-literal hook name resolved by inference to a filter.
$hook = 'the_content';
add_filter( $hook, [ $obj, 'filter_method' ] );

// Unknown/undefined global function - skipped.
add_filter( 'wp_footer', 'some_undefined_handler' );
