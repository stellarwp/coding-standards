<?php

// phpcs:set StellarWP.Hooks.HookHandlerTypes prefixes[] learndash,ld_,sfwd

class Example {

	public function register() {
		// Non-first-party filter: all params + return must be type-less.
		add_filter( 'the_content', [ $this, 'np_filter' ] );

		// Non-first-party action: params must be type-less; void return is allowed.
		add_action( 'save_post', [ $this, 'np_action' ] );

		// First-party filter: all params + return must be type-less, including the
		// context arguments (core can dispatch e.g. a null user id).
		add_filter( 'learndash_has_access', [ $this, 'fp_filter' ] );

		// First-party action: unrestricted - no errors.
		add_action( 'learndash_after_save', [ $this, 'fp_action' ] );

		// Non-first-party action, closure: param flagged, void return allowed.
		add_action( 'init', function ( int $x ): void {} );

		// First-party filter, closure: all params + return flagged.
		add_filter( 'ld_valid', function ( bool $valid, int $id ): bool {
			return $valid;
		} );

		// Non-first-party action closure with nullable / by-reference / variadic.
		add_action( 'pre_get_posts', function ( ?WP_Query $q, int &$ref, string ...$rest ) {} );

		// self::class static handler on a first-party filter.
		add_filter( 'sfwd_lms_has_access', [ self::class, 'fp_static_filter' ] );

		// Old-style [ &$this, 'method' ] reference on a non-first-party action.
		add_action( 'wp_mail_failed', [ &$this, 'np_ref_action' ] );

		// Same-file global-function handler on a non-first-party filter.
		add_filter( 'excerpt_length', 'np_global_filter' );

		// Dynamic hook name - skipped by the sniff (the PHPStan rule catches it).
		$hook = 'the_content';
		add_filter( $hook, [ $this, 'np_filter' ] );

		// Cross-file callback - left to the PHPStan rule.
		add_filter( 'wp_title', 'some_cross_file_handler' );
	}

	public function np_filter( string $content ): string {
		return $content;
	}

	public function np_action( int $post_id, WP_Post $post ): void {}

	public function fp_filter( bool $has_access, int $post_id, int $user_id ): bool {
		return $has_access;
	}

	public function fp_action( int $id ): void {}

	public function np_ref_action( \WP_Error $error ) {}

	public static function fp_static_filter( string $title ): string {
		return $title;
	}
}

function np_global_filter( string $length ): string {
	return $length;
}
