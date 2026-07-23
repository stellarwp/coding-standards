<?php
/**
 * PHPStan rule: forbids native parameter and return types on handlers attached
 * to hooks that this project does not own.
 *
 * This is the whole-codebase companion to the StellarWP.Hooks.HookHandlerTypes
 * PHPCS sniff. Because PHPStan analyses the entire codebase (never diff-limited)
 * and resolves callbacks and hook names across files, it catches the cases the
 * sniff cannot: a handler method whose declaration lives in a different file
 * from the add_action()/add_filter() call, and hook names that are not literal
 * strings but which type inference can narrow to constant string(s).
 *
 * @package StellarWP\CodingStandards
 */

namespace StellarWP\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParameterReflectionWithPhpDocs;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ParametersAcceptorWithPhpDocs;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<FuncCall>
 */
class HookHandlerTypesRule implements Rule {

	/**
	 * @var ReflectionProvider
	 */
	private $reflection_provider;

	/**
	 * Prefixes that identify first-party hooks. Mirror the project's
	 * WordPress.NamingConventions.PrefixAllGlobals "prefixes" value.
	 *
	 * @var string[]
	 */
	private $prefixes;

	/**
	 * Whether a native `void` return type is acceptable on action handlers.
	 *
	 * @var bool
	 */
	private $allow_void_return_on_actions;

	/**
	 * @param ReflectionProvider $reflection_provider          Provided by PHPStan.
	 * @param string[]           $prefixes                     First-party hook prefixes.
	 * @param bool               $allow_void_return_on_actions Allow `: void` on action handlers.
	 */
	public function __construct( ReflectionProvider $reflection_provider, array $prefixes, bool $allow_void_return_on_actions = true ) {
		$this->reflection_provider          = $reflection_provider;
		$this->prefixes                     = $prefixes;
		$this->allow_void_return_on_actions = $allow_void_return_on_actions;
	}

	public function getNodeType(): string {
		return FuncCall::class;
	}

	/**
	 * @param FuncCall $node
	 *
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( $this->prefixes === [] || ! $node->name instanceof Name ) {
			return [];
		}

		$function = strtolower( $node->name->toString() );
		if ( $function !== 'add_filter' && $function !== 'add_action' ) {
			return [];
		}

		$args = $node->getArgs();
		if ( count( $args ) < 2 ) {
			return [];
		}

		// Resolve the hook name(s) via type inference. This covers literals as
		// well as any expression narrowing to constant string(s).
		$constant_strings = $scope->getType( $args[0]->value )->getConstantStrings();
		if ( $constant_strings === [] ) {
			return [];
		}

		$foreign_hook = null;
		foreach ( $constant_strings as $constant_string ) {
			if ( ! $this->is_first_party( $constant_string->getValue() ) ) {
				$foreign_hook = $constant_string->getValue();
				break;
			}
		}

		// Every possible hook name is first-party: nothing to enforce.
		if ( $foreign_hook === null ) {
			return [];
		}

		return $this->check_callback( $args[1]->value, $scope, $foreign_hook, $function === 'add_filter' );
	}

	/**
	 * Dispatches to the appropriate resolver for the callback expression.
	 *
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	private function check_callback( Node $callback, Scope $scope, string $hook, bool $is_filter ): array {
		if ( $callback instanceof Closure || $callback instanceof ArrowFunction ) {
			return $this->check_closure_node( $callback, $hook, $is_filter );
		}

		if ( $callback instanceof Array_ ) {
			return $this->check_array_callback( $callback, $scope, $hook, $is_filter );
		}

		if ( $callback instanceof String_ ) {
			if ( strpos( $callback->value, '::' ) !== false ) {
				list( $class, $method ) = explode( '::', $callback->value, 2 );

				return $this->check_class_method( $class, $method, $hook, $is_filter, $callback->getStartLine() );
			}

			return $this->check_global_function( $callback->value, $scope, $hook, $is_filter, $callback->getStartLine() );
		}

		// Unresolvable callbacks (variables, first-class callables) are skipped.
		return [];
	}

	/**
	 * Checks an inline closure or arrow function using its declared native types.
	 *
	 * @param Closure|ArrowFunction $node
	 *
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	private function check_closure_node( Node $node, string $hook, bool $is_filter ): array {
		$errors = [];

		foreach ( $node->params as $param ) {
			if ( $param->type === null ) {
				continue;
			}

			$subject = $param->var instanceof Node\Expr\Variable && is_string( $param->var->name )
				? 'parameter $' . $param->var->name
				: 'a parameter';

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'Handler for non-first-party hook "%s" must not declare the native type "%s" on %s; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					$hook,
					$this->type_node_to_string( $param->type ),
					$subject
				)
			)->identifier( 'stellarwp.hookHandlerParamType' )->line( $param->getStartLine() )->build();
		}

		if ( $node->returnType !== null ) {
			$return_type = $this->type_node_to_string( $node->returnType );

			if ( ! $this->is_void_allowed( $is_filter, $return_type ) ) {
				$errors[] = $this->return_type_error( $hook, $is_filter, $return_type, $node->returnType->getStartLine() );
			}
		}

		return $errors;
	}

	/**
	 * Resolves an array callback ([ $this, 'method' ], [ Foo::class, 'method' ],
	 * [ 'Foo', 'method' ]) to the handler class(es) and checks the method.
	 *
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	private function check_array_callback( Array_ $callback, Scope $scope, string $hook, bool $is_filter ): array {
		if ( count( $callback->items ) < 2 || $callback->items[0] === null || $callback->items[1] === null ) {
			return [];
		}

		$method_value = $callback->items[1]->value;
		if ( ! $method_value instanceof String_ ) {
			return [];
		}

		$method       = $method_value->value;
		$subject_type = $scope->getType( $callback->items[0]->value );

		$class_names = [];
		foreach ( $subject_type->getObjectClassReflections() as $class_reflection ) {
			$class_names[ $class_reflection->getName() ] = true;
		}
		foreach ( $subject_type->getConstantStrings() as $constant_string ) {
			$class_names[ $constant_string->getValue() ] = true;
		}

		$errors = [];
		foreach ( array_keys( $class_names ) as $class_name ) {
			$errors = array_merge(
				$errors,
				$this->check_class_method( $class_name, $method, $hook, $is_filter, $callback->getStartLine() )
			);
		}

		return $errors;
	}

	/**
	 * Checks a resolved class method's native parameter and return types.
	 *
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	private function check_class_method( string $class_name, string $method, string $hook, bool $is_filter, int $line ): array {
		$class_name = ltrim( $class_name, '\\' );

		if ( ! $this->reflection_provider->hasClass( $class_name ) ) {
			return [];
		}

		$native = $this->reflection_provider->getClass( $class_name )->getNativeReflection();
		if ( ! $native->hasMethod( $method ) ) {
			return [];
		}

		$reflection_method = $native->getMethod( $method );
		$declaring         = $reflection_method->getDeclaringClass()->getName();
		$errors            = [];

		foreach ( $reflection_method->getParameters() as $parameter ) {
			if ( ! $parameter->hasType() ) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'Handler %s::%s() for non-first-party hook "%s" must not declare the native type "%s" on parameter $%s; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					$declaring,
					$method,
					$hook,
					$this->reflection_type_to_string( $parameter->getType() ),
					$parameter->getName()
				)
			)->identifier( 'stellarwp.hookHandlerParamType' )->line( $line )->build();
		}

		if ( $reflection_method->hasReturnType() ) {
			$return_type = $this->reflection_type_to_string( $reflection_method->getReturnType() );

			if ( ! $this->is_void_allowed( $is_filter, $return_type ) ) {
				$errors[] = $this->return_type_error( $hook, $is_filter, $return_type, $line, $declaring . '::' . $method . '()' );
			}
		}

		return $errors;
	}

	/**
	 * Checks a global-function-name callback ('my_function') using PHPStan's
	 * static reflection, which knows project functions without loading them.
	 *
	 * @return array<int, \PHPStan\Rules\RuleError>
	 */
	private function check_global_function( string $name, Scope $scope, string $hook, bool $is_filter, int $line ): array {
		$function_name = new Name( ltrim( $name, '\\' ) );

		if ( ! $this->reflection_provider->hasFunction( $function_name, $scope ) ) {
			return [];
		}

		$variant = ParametersAcceptorSelector::selectSingle(
			$this->reflection_provider->getFunction( $function_name, $scope )->getVariants()
		);

		if ( ! $variant instanceof ParametersAcceptorWithPhpDocs ) {
			return [];
		}

		$errors = [];

		foreach ( $variant->getParameters() as $parameter ) {
			if ( ! $parameter instanceof ParameterReflectionWithPhpDocs || ! $this->has_native_type( $parameter->getNativeType() ) ) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'Handler %s() for non-first-party hook "%s" must not declare the native type "%s" on parameter $%s; WordPress does not guarantee hook argument types and a native type can cause a fatal error.',
					$name,
					$hook,
					$parameter->getNativeType()->describe( VerbosityLevel::typeOnly() ),
					$parameter->getName()
				)
			)->identifier( 'stellarwp.hookHandlerParamType' )->line( $line )->build();
		}

		$native_return = $variant->getNativeReturnType();

		if ( $this->has_native_type( $native_return ) ) {
			$return_type = $native_return->describe( VerbosityLevel::typeOnly() );

			if ( ! $this->is_void_allowed( $is_filter, $return_type ) ) {
				$errors[] = $this->return_type_error( $hook, $is_filter, $return_type, $line, $name . '()' );
			}
		}

		return $errors;
	}

	/**
	 * Whether a native type is actually declared. An undeclared type is an
	 * implicit `mixed`; an explicit `mixed` counts as a declared native type,
	 * matching \ReflectionParameter::hasType() semantics used for methods.
	 */
	private function has_native_type( Type $type ): bool {
		return ! ( $type instanceof MixedType && ! $type->isExplicitMixed() );
	}

	/**
	 * Builds a return-type violation error.
	 *
	 * @return \PHPStan\Rules\RuleError
	 */
	private function return_type_error( string $hook, bool $is_filter, string $return_type, int $line, string $handler = '' ) {
		$where = $handler === '' ? '' : $handler . ' ';

		if ( $is_filter ) {
			$message = sprintf(
				'Handler %sfor non-first-party filter "%s" must not declare a native return type ("%s"); filter return values are not type-guaranteed and a native return type can cause a fatal error.',
				$where,
				$hook,
				$return_type
			);
		} else {
			$message = sprintf(
				'Handler %sfor non-first-party action "%s" must not declare a native return type ("%s") other than void.',
				$where,
				$hook,
				$return_type
			);
		}

		return RuleErrorBuilder::message( $message )->identifier( 'stellarwp.hookHandlerReturnType' )->line( $line )->build();
	}

	/**
	 * Whether a native `void` return type is acceptable in this context.
	 */
	private function is_void_allowed( bool $is_filter, string $return_type ): bool {
		return ! $is_filter
			&& $this->allow_void_return_on_actions
			&& strtolower( ltrim( $return_type, '?\\' ) ) === 'void';
	}

	/**
	 * Whether a hook name belongs to this project by prefix.
	 */
	private function is_first_party( string $hook_name ): bool {
		foreach ( $this->prefixes as $prefix ) {
			if ( $prefix !== '' && stripos( $hook_name, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renders a PhpParser type node to a readable string (for messages and void
	 * detection).
	 *
	 * @param Node $type A parameter or return type node.
	 */
	private function type_node_to_string( Node $type ): string {
		if ( $type instanceof Node\Identifier || $type instanceof Node\Name ) {
			return $type->toString();
		}

		if ( $type instanceof Node\NullableType ) {
			return '?' . $this->type_node_to_string( $type->type );
		}

		if ( $type instanceof Node\UnionType || $type instanceof Node\IntersectionType ) {
			$separator = $type instanceof Node\UnionType ? '|' : '&';
			$parts     = [];

			foreach ( $type->types as $inner ) {
				$parts[] = $this->type_node_to_string( $inner );
			}

			return implode( $separator, $parts );
		}

		return 'mixed';
	}

	/**
	 * Renders a native ReflectionType to a readable string.
	 *
	 * @param \ReflectionType|null $type The reflection type.
	 */
	private function reflection_type_to_string( ?\ReflectionType $type ): string {
		if ( $type instanceof \ReflectionNamedType ) {
			return ( $type->allowsNull() && strtolower( $type->getName() ) !== 'null' ? '?' : '' ) . $type->getName();
		}

		return $type === null ? 'mixed' : (string) $type;
	}
}
