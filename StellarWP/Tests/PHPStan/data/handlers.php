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

	public function container_handler( int $value ): int {
		return $value;
	}
}

class Fake_Container {

	public function callback( $id, $method ) {
		return static function () {};
	}
}

class Fake_Registrar {

	public function add_action( $tag, $method = '', $priority = 10, $accepted_args = 1 ) {
		add_action( $tag, array( $this, '' === $method ? $tag : $method ), $priority, $accepted_args );
	}

	public function add_filter( $tag, $method = '', $priority = 10, $accepted_args = 1 ) {
		add_filter( $tag, array( $this, '' === $method ? $tag : $method ), $priority, $accepted_args );
	}
}

class Wrapper_Subclass extends Fake_Registrar {

	public function on_save( int $post_id ): void {}

	public function filter_it( string $content ): string {
		return $content;
	}
}

function global_filter( string $length ): string {
	return $length;
}

function typeless_global_filter( $length ) {
	return $length;
}
