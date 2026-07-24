<?php
/**
 * Handler classes and WP stubs for the HookHandlerTypesRule test. These live in
 * a separate file from the analysed calls so the rule must resolve a handler
 * whose declaration is in a different file - the cross-file case.
 *
 * @package StellarWP\CodingStandards
 */

// phpcs:disable
class WP_Post {}
class WP_Query {}

class Hook_Test_Handlers {

	public function filter_method( string $content ): string {
		return $content;
	}

	public function action_method( int $post_id, WP_Post $post ): void {}

	public function filter_context_method( bool $has_access, int $post_id, int $user_id ): bool {
		return $has_access;
	}

	public function action_typed( int $id ): void {}

	public static function static_filter( string $title ): string {
		return $title;
	}
}

function global_filter( string $length ): string {
	return $length;
}

function typeless_global_filter( $length ) {
	return $length;
}
