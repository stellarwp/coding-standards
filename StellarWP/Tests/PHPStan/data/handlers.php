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

	public function filter_content( string $content ): string {
		return $content;
	}

	public function on_save( int $post_id, WP_Post $post ): void {}

	public static function static_filter( string $title ): string {
		return $title;
	}

	public function typed_ld_handler( int $id ): void {}
}

function typed_global_handler( string $value ): string {
	return $value;
}

function typeless_global_handler( $value ) {
	return $value;
}
