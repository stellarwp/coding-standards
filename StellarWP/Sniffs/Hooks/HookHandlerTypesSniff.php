<?php
/**
 * Forbids native parameter and return types on WordPress hook handlers where the
 * argument or return types are not guaranteed.
 *
 * A hook's argument and return types are never guaranteed. Relying on
 * documentation that turns out to be inaccurate can lead to the wrong native
 * type, and any third party can dispatch one of our hooks via apply_filters() or
 * do_action() with arguments of a different type. Either way, a value that does
 * not match a native type declared on the handler causes a runtime fatal.
 * Enforcement follows the hook type:
 *
 * - Filter handlers: no native type on ANY parameter and no native return type.
 * - Action handlers: no native type on ANY parameter; a native `void` return
 *   type is allowed (actions always return void).
 *
 * This sniff analyses at the call site and can only resolve handlers that live in
 * the same file: inline closures and arrow functions, same-file [ $this, 'method' ]
 * / [ self::class, 'method' ] references, same-file 'function_name' global-function
 * handlers, and $this->add_action( 'tag', 'method' ) wrapper calls whose handler
 * method is on the enclosing class (e.g. memberdash's MS_Hooker). It also only
 * understands literal hook names. Cross-file callbacks and non-literal hook names
 * are left to the companion PHPStan rule, which resolves callbacks across files
 * via reflection and resolves hook names via type inference (constant-string
 * types).
 *
 * @package StellarWP\CodingStandards
 */

namespace StellarWP\Sniffs\Hooks;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * HookHandlerTypesSniff class.
 */
class HookHandlerTypesSniff implements Sniff {

	/**
	 * Hook-registration functions mapped to whether they register a filter.
	 *
	 * @var array<string, bool>
	 */
	private const HOOK_FUNCTIONS = [
		'add_filter' => true,
		'add_action' => false,
	];

	/**
	 * Whether a native `void` return type is acceptable on action handlers.
	 *
	 * Actions always return void, so `: void` is harmless on an action handler.
	 * Filters can never carry a native return type regardless of this setting.
	 *
	 * @var bool
	 */
	public $allow_void_return_on_actions = true;

	/**
	 * Whether to warn when the hook name cannot be resolved to a literal string.
	 *
	 * @var bool
	 */
	public $warn_on_dynamic_hook_names = false;

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array<int>
	 */
	public function register(): array {
		return [ T_STRING ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * $stack_ptr carries no native type hint on purpose: the PHP_CodeSniffer
	 * Sniff interface declares process() with an untyped second parameter, and
	 * PHP would fatal on an incompatible declaration if a type were added here.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ): void { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- The Sniff interface forbids a native type on this parameter; see the note above.
		$tokens  = $phpcs_file->getTokens();
		$content = strtolower( $tokens[ $stack_ptr ]['content'] );

		if ( ! isset( self::HOOK_FUNCTIONS[ $content ] ) ) {
			return;
		}

		// Determine the call form. A global add_action()/add_filter() call takes a
		// callback as its second argument. A $this->add_action( 'tag', 'method' )
		// wrapper (e.g. memberdash's MS_Hooker) takes a method name that resolves
		// to a method on the enclosing class. Other forms (another object, ::, or a
		// definition) are not handled here.
		$prev       = $phpcs_file->findPrevious( Tokens::$emptyTokens, $stack_ptr - 1, null, true );
		$is_wrapper = false;

		if ( $prev !== false ) {
			if ( in_array( $tokens[ $prev ]['code'], [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR ], true ) ) {
				// Only $this-> wrappers can be resolved (and fixed) within this file.
				$receiver = $phpcs_file->findPrevious( Tokens::$emptyTokens, $prev - 1, null, true );
				if (
					$receiver === false
					|| $tokens[ $receiver ]['code'] !== T_VARIABLE
					|| strtolower( $tokens[ $receiver ]['content'] ) !== '$this'
				) {
					return;
				}

				$is_wrapper = true;
			} elseif ( in_array( $tokens[ $prev ]['code'], [ T_DOUBLE_COLON, T_FUNCTION, T_NEW ], true ) ) {
				return;
			}
		}

		$open_paren = $phpcs_file->findNext( Tokens::$emptyTokens, $stack_ptr + 1, null, true );
		if (
			$open_paren === false
			|| $tokens[ $open_paren ]['code'] !== T_OPEN_PARENTHESIS
			|| ! isset( $tokens[ $open_paren ]['parenthesis_closer'] )
		) {
			return;
		}

		$close_paren = $tokens[ $open_paren ]['parenthesis_closer'];
		$args        = $this->split_arguments( $phpcs_file, $open_paren, $close_paren );

		if ( $args === [] ) {
			return;
		}

		// Argument 1: the hook name. Used to name the hook in the message, and on
		// the wrapper path it also serves as the fallback handler method name.
		$hook_name = $this->get_string_argument( $phpcs_file, $args[0] );
		if ( $hook_name === null ) {
			if ( $this->warn_on_dynamic_hook_names ) {
				$phpcs_file->addWarning(
					'Unable to determine the hook name statically; the handler type restriction could not be verified.',
					$args[0]['start'],
					'DynamicHookName'
				);
			}

			return;
		}

		$is_filter = self::HOOK_FUNCTIONS[ $content ];

		if ( $is_wrapper ) {
			$func_ptr = $this->resolve_wrapper_handler( $phpcs_file, $args, $hook_name, $stack_ptr );
		} elseif ( isset( $args[1] ) ) {
			// Argument 2: the callback. Resolve to a function/closure/method token.
			$func_ptr = $this->resolve_handler( $phpcs_file, $args[1], $stack_ptr );
		} else {
			return;
		}

		if ( $func_ptr === null ) {
			// Cross-file, global-function, or dynamic handler: left to the PHPStan rule.
			return;
		}

		// Wrapper handlers ($this->add_action( 'tag', 'method' )) are resolved via
		// a name-match heuristic, so they are reported but never auto-fixed -
		// stripping types off a coincidental match would be destructive. Direct
		// callbacks are unambiguous and stay auto-fixable.
		$this->check_handler_types( $phpcs_file, $func_ptr, $hook_name, $is_filter, ! $is_wrapper );
	}

	/**
	 * Resolves the handler method for a $this->add_action( 'tag', 'method' )
	 * wrapper call. The method name is the second argument, falling back to the
	 * hook name when it is absent or an empty string (per MS_Hooker). Returns the
	 * method token in the enclosing class, or null when it cannot be resolved.
	 *
	 * @param File                                     $phpcs_file The file being scanned.
	 * @param array<int, array{start: int, end: int}>  $args       The call arguments.
	 * @param string                                   $hook_name  The resolved hook name.
	 * @param int                                      $stack_ptr  The call token position.
	 *
	 * @return int|null
	 */
	private function resolve_wrapper_handler( File $phpcs_file, array $args, string $hook_name, int $stack_ptr ): ?int {
		$method = $hook_name;

		if ( isset( $args[1] ) ) {
			$argument = $this->get_string_argument( $phpcs_file, $args[1] );
			if ( $argument === null ) {
				// Dynamic method name - cannot be resolved within this file.
				return null;
			}

			if ( $argument !== '' ) {
				$method = $argument;
			}
		}

		return $this->find_class_method( $phpcs_file, $stack_ptr, $method );
	}

	/**
	 * Splits the contents of a parenthesis or bracket pair into top-level,
	 * comma-separated argument token ranges.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $opener     The opening parenthesis/bracket position.
	 * @param int  $closer     The matching closing position.
	 *
	 * @return array<int, array{start: int, end: int}>
	 */
	private function split_arguments( File $phpcs_file, int $opener, int $closer ): array {
		$tokens = $phpcs_file->getTokens();
		$args   = [];

		$open_codes = [
			T_OPEN_PARENTHESIS,
			T_OPEN_SHORT_ARRAY,
			T_OPEN_SQUARE_BRACKET,
			T_OPEN_CURLY_BRACKET,
		];

		$close_codes = [
			T_CLOSE_PARENTHESIS,
			T_CLOSE_SHORT_ARRAY,
			T_CLOSE_SQUARE_BRACKET,
			T_CLOSE_CURLY_BRACKET,
		];

		$start = $phpcs_file->findNext( Tokens::$emptyTokens, $opener + 1, $closer, true );
		if ( $start === false ) {
			return $args;
		}

		$depth         = 0;
		$current_start = $start;

		for ( $i = $start; $i < $closer; $i++ ) {
			$code = $tokens[ $i ]['code'];

			if ( in_array( $code, $open_codes, true ) ) {
				$depth++;
				continue;
			}

			if ( in_array( $code, $close_codes, true ) ) {
				$depth--;
				continue;
			}

			if ( $depth === 0 && $code === T_COMMA ) {
				$end = $phpcs_file->findPrevious( Tokens::$emptyTokens, $i - 1, $current_start, true );
				if ( $end !== false ) {
					$args[] = [
						'start' => $current_start,
						'end'   => $end,
					];
				}

				$next = $phpcs_file->findNext( Tokens::$emptyTokens, $i + 1, $closer, true );
				if ( $next === false ) {
					return $args;
				}

				$current_start = $next;
			}
		}

		$end = $phpcs_file->findPrevious( Tokens::$emptyTokens, $closer - 1, $current_start, true );
		if ( $end !== false ) {
			$args[] = [
				'start' => $current_start,
				'end'   => $end,
			];
		}

		return $args;
	}

	/**
	 * Returns the literal string value of an argument, or null when the argument
	 * is not a single constant string (e.g. a variable or concatenation).
	 *
	 * @param File                          $phpcs_file The file being scanned.
	 * @param array{start: int, end: int}   $arg        The argument token range.
	 *
	 * @return string|null
	 */
	private function get_string_argument( File $phpcs_file, array $arg ): ?string {
		$tokens = $phpcs_file->getTokens();

		if ( $arg['start'] !== $arg['end'] ) {
			return null;
		}

		if ( $tokens[ $arg['start'] ]['code'] !== T_CONSTANT_ENCAPSED_STRING ) {
			return null;
		}

		return $this->strip_quotes( $tokens[ $arg['start'] ]['content'] );
	}

	/**
	 * Removes matching surrounding single or double quotes from a raw string token.
	 *
	 * @param string $raw The raw token content.
	 *
	 * @return string
	 */
	private function strip_quotes( string $raw ): string {
		if ( strlen( $raw ) >= 2 ) {
			$first = $raw[0];
			$last  = $raw[ strlen( $raw ) - 1 ];

			if ( ( $first === "'" || $first === '"' ) && $first === $last ) {
				return substr( $raw, 1, -1 );
			}
		}

		return $raw;
	}

	/**
	 * Resolves a callback argument to the token of the function, closure, or
	 * arrow function whose declared types should be checked. Returns null when
	 * the callback cannot be resolved within this file.
	 *
	 * @param File                        $phpcs_file The file being scanned.
	 * @param array{start: int, end: int} $arg        The callback argument range.
	 * @param int                         $stack_ptr  The hook-call token position.
	 *
	 * @return int|null
	 */
	private function resolve_handler( File $phpcs_file, array $arg, int $stack_ptr ): ?int {
		$tokens = $phpcs_file->getTokens();
		$ptr    = $arg['start'];

		// Allow a leading `static` for static closures/arrow functions.
		if ( $tokens[ $ptr ]['code'] === T_STATIC ) {
			$ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $ptr + 1, $arg['end'] + 1, true );
			if ( $ptr === false ) {
				return null;
			}
		}

		$code = $tokens[ $ptr ]['code'];

		if ( $code === T_CLOSURE || $code === T_FN ) {
			return $ptr;
		}

		if ( $code === T_OPEN_SHORT_ARRAY || $code === T_ARRAY ) {
			return $this->resolve_array_callback( $phpcs_file, $ptr, $stack_ptr );
		}

		// A plain 'function_name' string: resolve a same-file global function.
		// Namespaced names and 'Class::method' strings are left to the PHPStan rule.
		if ( $code === T_CONSTANT_ENCAPSED_STRING && $ptr === $arg['end'] ) {
			$value = $this->strip_quotes( $tokens[ $ptr ]['content'] );

			if ( $value === '' || strpos( $value, '::' ) !== false || strpos( $value, '\\' ) !== false ) {
				return null;
			}

			return $this->find_global_function( $phpcs_file, $value );
		}

		return null;
	}

	/**
	 * Finds a global (file-scope) function declaration by name. Returns the
	 * T_FUNCTION token position, or null when not found in this file.
	 *
	 * @param File   $phpcs_file The file being scanned.
	 * @param string $name       The function name to find.
	 *
	 * @return int|null
	 */
	private function find_global_function( File $phpcs_file, string $name ): ?int {
		$tokens  = $phpcs_file->getTokens();
		$name_lc = strtolower( $name );
		$ptr     = 0;

		while ( ( $ptr = $phpcs_file->findNext( T_FUNCTION, $ptr + 1 ) ) !== false ) {
			// Only file-scope functions - skip methods and nested functions.
			if ( ! empty( $tokens[ $ptr ]['conditions'] ) ) {
				continue;
			}

			$name_ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $ptr + 1, null, true );
			if ( $name_ptr !== false && $tokens[ $name_ptr ]['code'] === T_BITWISE_AND ) {
				$name_ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $name_ptr + 1, null, true );
			}

			if (
				$name_ptr !== false
				&& $tokens[ $name_ptr ]['code'] === T_STRING
				&& strtolower( $tokens[ $name_ptr ]['content'] ) === $name_lc
			) {
				return $ptr;
			}
		}

		return null;
	}

	/**
	 * Resolves an array callback ([ $this, 'method' ] or array( self::class,
	 * 'method' )) to a same-file method declaration token, or null.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $array_ptr  The array opener token position.
	 * @param int  $stack_ptr  The hook-call token position.
	 *
	 * @return int|null
	 */
	private function resolve_array_callback( File $phpcs_file, int $array_ptr, int $stack_ptr ): ?int {
		$tokens = $phpcs_file->getTokens();

		if ( $tokens[ $array_ptr ]['code'] === T_OPEN_SHORT_ARRAY ) {
			$opener = $array_ptr;
			$closer = $tokens[ $array_ptr ]['bracket_closer'];
		} else {
			$opener = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $array_ptr + 1 );
			if ( $opener === false || ! isset( $tokens[ $opener ]['parenthesis_closer'] ) ) {
				return null;
			}

			$closer = $tokens[ $opener ]['parenthesis_closer'];
		}

		$elements = $this->split_arguments( $phpcs_file, $opener, $closer );
		if ( count( $elements ) < 2 ) {
			return null;
		}

		if ( ! $this->is_current_class_reference( $phpcs_file, $elements[0] ) ) {
			return null;
		}

		$method = $this->get_string_argument( $phpcs_file, $elements[1] );
		if ( $method === null ) {
			return null;
		}

		return $this->find_class_method( $phpcs_file, $stack_ptr, $method );
	}

	/**
	 * Determines whether a callback array's first element refers to the current
	 * class instance or name ($this, self::class, static::class, __CLASS__).
	 *
	 * @param File                        $phpcs_file The file being scanned.
	 * @param array{start: int, end: int} $element    The element token range.
	 *
	 * @return bool
	 */
	private function is_current_class_reference( File $phpcs_file, array $element ): bool {
		$tokens = $phpcs_file->getTokens();
		$first  = $element['start'];

		// Skip a leading reference operator, e.g. the old `[ &$this, 'method' ]` idiom.
		if ( $tokens[ $first ]['code'] === T_BITWISE_AND ) {
			$first = $phpcs_file->findNext( Tokens::$emptyTokens, $first + 1, $element['end'] + 1, true );
			if ( $first === false ) {
				return false;
			}
		}

		$code = $tokens[ $first ]['code'];

		if ( $code === T_VARIABLE ) {
			return $first === $element['end']
				&& strtolower( $tokens[ $first ]['content'] ) === '$this';
		}

		if ( $code === T_CLASS_C ) {
			return $first === $element['end'];
		}

		// self::class / static::class. PHPCS tokenizes these as T_SELF / T_STATIC;
		// the T_STRING fallback covers tokenizer/version differences.
		$name = strtolower( $tokens[ $first ]['content'] );
		if (
			$code === T_SELF
			|| $code === T_STATIC
			|| ( $code === T_STRING && ( $name === 'self' || $name === 'static' ) )
		) {
			$next = $phpcs_file->findNext( Tokens::$emptyTokens, $first + 1, $element['end'] + 1, true );

			return $next !== false && $tokens[ $next ]['code'] === T_DOUBLE_COLON;
		}

		return false;
	}

	/**
	 * Finds a method declaration by name within the class/trait enclosing the
	 * hook call. Returns the T_FUNCTION token position, or null when not found.
	 *
	 * @param File   $phpcs_file The file being scanned.
	 * @param int    $stack_ptr  The hook-call token position.
	 * @param string $method     The method name to find.
	 *
	 * @return int|null
	 */
	private function find_class_method( File $phpcs_file, int $stack_ptr, string $method ): ?int {
		$tokens = $phpcs_file->getTokens();

		if ( empty( $tokens[ $stack_ptr ]['conditions'] ) ) {
			return null;
		}

		$class_scopes = [ T_CLASS, T_TRAIT, T_ANON_CLASS ];
		$class_ptr    = null;

		foreach ( array_reverse( $tokens[ $stack_ptr ]['conditions'], true ) as $ptr => $code ) {
			if ( in_array( $code, $class_scopes, true ) ) {
				$class_ptr = $ptr;
				break;
			}
		}

		if (
			$class_ptr === null
			|| ! isset( $tokens[ $class_ptr ]['scope_opener'], $tokens[ $class_ptr ]['scope_closer'] )
		) {
			return null;
		}

		$start     = $tokens[ $class_ptr ]['scope_opener'];
		$end       = $tokens[ $class_ptr ]['scope_closer'];
		$method_lc = strtolower( $method );

		for ( $i = $start + 1; $i < $end; $i++ ) {
			if ( $tokens[ $i ]['code'] !== T_FUNCTION ) {
				continue;
			}

			// Only direct methods of this class - skip functions nested in methods.
			$conditions = $tokens[ $i ]['conditions'];
			if ( empty( $conditions ) ) {
				continue;
			}

			end( $conditions );
			if ( key( $conditions ) !== $class_ptr ) {
				continue;
			}

			$name_ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $i + 1, null, true );
			if ( $name_ptr !== false && $tokens[ $name_ptr ]['code'] === T_BITWISE_AND ) {
				$name_ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $name_ptr + 1, null, true );
			}

			if (
				$name_ptr !== false
				&& $tokens[ $name_ptr ]['code'] === T_STRING
				&& strtolower( $tokens[ $name_ptr ]['content'] ) === $method_lc
			) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Checks a resolved handler for disallowed native parameter and return types.
	 * When $fixable is true the offending type declarations are stripped in place;
	 * otherwise the violation is reported without an auto-fix.
	 *
	 * @param File   $phpcs_file The file being scanned.
	 * @param int    $func_ptr   The function/closure/arrow token position.
	 * @param string $hook_name  The resolved hook name (for messaging).
	 * @param bool   $is_filter  Whether the hook is a filter.
	 * @param bool   $fixable    Whether the violation may be auto-fixed.
	 *
	 * @return void
	 */
	private function check_handler_types( File $phpcs_file, int $func_ptr, string $hook_name, bool $is_filter, bool $fixable = true ): void {
		$hook_type = $is_filter ? 'filter' : 'action';
		$params    = $phpcs_file->getMethodParameters( $func_ptr );

		foreach ( $params as $param ) {
			if ( empty( $param['type_hint'] ) ) {
				continue;
			}

			$message = 'Handler for %s "%s" must not declare the native type "%s" on parameter %s; hook arguments are not type-guaranteed (a hook can be dispatched with unexpected types, including null), so a native type can cause a fatal error.';
			$data    = [ $hook_type, $hook_name, $param['type_hint'], $param['name'] ];

			if ( ! $fixable ) {
				$phpcs_file->addError( $message, $param['type_hint_token'], 'NativeParameterType', $data );
				continue;
			}

			$fix = $phpcs_file->addFixableError( $message, $param['type_hint_token'], 'NativeParameterType', $data );

			if ( $fix === true ) {
				$this->remove_parameter_type( $phpcs_file, $param );
			}
		}

		$props = $phpcs_file->getMethodProperties( $func_ptr );
		if ( empty( $props['return_type'] ) ) {
			return;
		}

		$return_type    = $props['return_type'];
		$return_type_lc = strtolower( ltrim( $return_type, '?\\' ) );

		// Actions always return void, so a void return type is acceptable.
		if ( ! $is_filter && $this->allow_void_return_on_actions && $return_type_lc === 'void' ) {
			return;
		}

		if ( $is_filter ) {
			$message = 'Handler for filter "%s" must not declare a native return type ("%s"); filter return values are not type-guaranteed and a native return type can cause a fatal error.';
		} elseif ( $this->allow_void_return_on_actions ) {
			$message = 'Handler for action "%s" must not declare a native return type ("%s") other than void.';
		} else {
			$message = 'Handler for action "%s" must not declare a native return type ("%s").';
		}

		$data = [ $hook_name, $return_type ];

		if ( ! $fixable ) {
			$phpcs_file->addError( $message, $props['return_type_token'], 'NativeReturnType', $data );

			return;
		}

		$fix = $phpcs_file->addFixableError( $message, $props['return_type_token'], 'NativeReturnType', $data );

		if ( $fix === true ) {
			$this->remove_return_type( $phpcs_file, $func_ptr, $props );
		}
	}

	/**
	 * Strips a parameter's native type declaration, leaving the variable (and
	 * any reference/variadic markers, attributes, default, and line breaks)
	 * untouched.
	 *
	 * @param File  $phpcs_file The file being scanned.
	 * @param array $param      A single entry from File::getMethodParameters().
	 *
	 * @return void
	 */
	private function remove_parameter_type( File $phpcs_file, array $param ): void {
		$tokens = $phpcs_file->getTokens();
		$fixer  = $phpcs_file->fixer;

		$start = $param['type_hint_token'];
		$end   = $param['type_hint_end_token'];

		// Include a leading nullable `?` when it sits just before the type.
		if ( ! empty( $param['nullable_type'] ) ) {
			$before = $phpcs_file->findPrevious( Tokens::$emptyTokens, $start - 1, null, true );
			if ( $before !== false && $tokens[ $before ]['code'] === T_NULLABLE ) {
				$start = $before;
			}
		}

		$fixer->beginChangeset();

		for ( $i = $start; $i <= $end; $i++ ) {
			$fixer->replaceToken( $i, '' );
		}

		// Remove exactly one following whitespace token (the space before the
		// variable/&/...), but only when it does not span a line break.
		$after = $end + 1;
		if (
			isset( $tokens[ $after ] )
			&& $tokens[ $after ]['code'] === T_WHITESPACE
			&& strpos( $tokens[ $after ]['content'], "\n" ) === false
		) {
			$fixer->replaceToken( $after, '' );
		}

		$fixer->endChangeset();
	}

	/**
	 * Strips a native return type declaration (the colon through the type),
	 * leaving whatever whitespace joined the type to the body untouched so the
	 * brace/arrow position is preserved.
	 *
	 * @param File  $phpcs_file The file being scanned.
	 * @param int   $func_ptr   The function/closure/arrow token position.
	 * @param array $props      The result of File::getMethodProperties().
	 *
	 * @return void
	 */
	private function remove_return_type( File $phpcs_file, int $func_ptr, array $props ): void {
		$tokens = $phpcs_file->getTokens();
		$fixer  = $phpcs_file->fixer;

		$end = $props['return_type_end_token'];

		// The return-type colon lives between the parameter list close-paren and
		// the type. Bound the search at the close-paren to avoid stray colons.
		$boundary = $func_ptr;
		if (
			isset( $tokens[ $func_ptr ]['parenthesis_opener'] )
			&& isset( $tokens[ $tokens[ $func_ptr ]['parenthesis_opener'] ]['parenthesis_closer'] )
		) {
			$boundary = $tokens[ $tokens[ $func_ptr ]['parenthesis_opener'] ]['parenthesis_closer'];
		}

		$colon = $phpcs_file->findPrevious( T_COLON, $props['return_type_token'] - 1, $boundary );
		$start = ( $colon !== false ) ? $colon : $props['return_type_token'];

		// When the return type begins on a line of its own (a line break sits
		// between the parameter list close-paren and the colon), also remove that
		// leading break and indentation so no blank or indent-only line is left
		// behind. Inline return types have no such whitespace and are untouched.
		$ws_start    = $start;
		$saw_newline = false;
		for ( $p = $start - 1; isset( $tokens[ $p ] ) && $tokens[ $p ]['code'] === T_WHITESPACE; $p-- ) {
			if ( strpos( $tokens[ $p ]['content'], "\n" ) !== false ) {
				$saw_newline = true;
			}

			$ws_start = $p;
		}

		if ( $saw_newline ) {
			$start = $ws_start;
		}

		$fixer->beginChangeset();

		for ( $i = $start; $i <= $end; $i++ ) {
			$fixer->replaceToken( $i, '' );
		}

		$fixer->endChangeset();
	}
}
