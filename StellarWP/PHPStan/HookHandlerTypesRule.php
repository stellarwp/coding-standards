<?php
/**
 * PHPStan rule: forbids native parameter and return types on hook handlers where
 * the argument or return types are not guaranteed. A hook's types are never
 * guaranteed - inaccurate documentation can lead to the wrong native type, and
 * any third party can dispatch one of our hooks via apply_filters()/do_action()
 * with a different type - so a value that does not match a native type on the
 * handler causes a runtime fatal. Enforcement mirrors the
 * StellarWP.Hooks.HookHandlerTypes sniff:
 *
 * - Filter handlers: no native type on any parameter and no native return type.
 * - Action handlers: no native type on any parameter; a native `void` return
 *   type is allowed (actions always return void).
 *
 * This is the whole-codebase companion to the sniff. Because PHPStan analyses the
 * entire codebase (never diff-limited) and resolves callbacks and hook names
 * across files, it catches the cases the sniff cannot: a handler whose
 * declaration lives in a different file from the add_action()/add_filter() call,
 * hook names that are not literal strings but which type inference can narrow to
 * constant string(s), container callbacks
 * (`$container->callback( Class::class, 'method' )`), and hook-registration
 * wrapper methods (`$receiver->add_action( 'tag', 'method' )`, where the second
 * argument is a method on the receiver).
 *
 * @package StellarWP\CodingStandards
 */

namespace StellarWP\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParameterReflectionWithPhpDocs;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ParametersAcceptorWithPhpDocs;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use ReflectionNamedType;
use ReflectionType;

/**
 * @implements Rule<CallLike>
 */
class HookHandlerTypesRule implements Rule {

	/**
	 * @var ReflectionProvider
	 */
	private $reflection_provider;

	/**
	 * Whether a native `void` return type is acceptable on action handlers.
	 *
	 * @var bool
	 */
	private $allow_void_return_on_actions;

	/**
	 * @param ReflectionProvider $reflection_provider          Provided by PHPStan.
	 * @param bool               $allow_void_return_on_actions Allow `: void` on action handlers.
	 */
	public function __construct( ReflectionProvider $reflection_provider, bool $allow_void_return_on_actions = true ) {
		$this->reflection_provider          = $reflection_provider;
		$this->allow_void_return_on_actions = $allow_void_return_on_actions;
	}

	/**
	 * The node type this rule listens for.
	 *
	 * @return string
	 */
	public function getNodeType(): string {
		return CallLike::class;
	}

	/**
	 * Dispatches a call node to the WordPress-call or wrapper-call handler.
	 *
	 * @param Node  $node  The call node being analysed.
	 * @param Scope $scope The current analysis scope.
	 *
	 * @return array<int, RuleError>
	 */
	public function processNode( Node $node, Scope $scope ): array {
		// WordPress add_action()/add_filter() calls.
		if ( $node instanceof FuncCall ) {
			return $this->process_wp_call( $node, $scope );
		}

		// Wrapper methods that forward to add_action()/add_filter(), where the
		// second argument is the name of a method on the receiver, resolved to
		// [ $receiver, 'method' ] (e.g. memberdash's MS_Hooker:
		// $this->add_action( 'tag', 'method' )).
		if ( $node instanceof MethodCall ) {
			return $this->process_wrapper_call( $node, $scope );
		}

		return [];
	}

	/**
	 * Handles a WordPress add_action()/add_filter() function call.
	 *
	 * @param FuncCall $node  The function-call node.
	 * @param Scope    $scope The current analysis scope.
	 *
	 * @return array<int, RuleError>
	 */
	private function process_wp_call( FuncCall $node, Scope $scope ): array {
		if ( ! $node->name instanceof Name ) {
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

		// Resolve the hook name via type inference (used only to name the hook in
		// the message). This covers literals as well as any expression narrowing
		// to constant string(s).
		$constant_strings = $scope->getType( $args[0]->value )->getConstantStrings();
		if ( $constant_strings === [] ) {
			return [];
		}

		return $this->check_callback( $args[1]->value, $scope, $constant_strings[0]->getValue(), $function === 'add_filter' );
	}

	/**
	 * Handles a hook-registration wrapper method: `$receiver->add_action( 'tag',
	 * 'method' )` / `->add_filter( ... )` whose second argument is the name of a
	 * method on the receiver (the handler is [ $receiver, 'method' ]; when the
	 * second argument is absent or empty the hook name is used as the method
	 * name). Only acts when that method actually exists on the receiver, so an
	 * unrelated method named add_action()/add_filter() is ignored.
	 *
	 * @param MethodCall $node  The method-call node.
	 * @param Scope      $scope The current analysis scope.
	 *
	 * @return array<int, RuleError>
	 */
	private function process_wrapper_call( MethodCall $node, Scope $scope ): array {
		if ( ! $node->name instanceof Node\Identifier ) {
			return [];
		}

		$method = strtolower( $node->name->name );
		if ( $method !== 'add_action' && $method !== 'add_filter' ) {
			return [];
		}

		$args = $node->getArgs();
		if ( $args === [] ) {
			return [];
		}

		$hook_strings = $scope->getType( $args[0]->value )->getConstantStrings();
		if ( $hook_strings === [] ) {
			return [];
		}
		$hook = $hook_strings[0]->getValue();

		// The handler method name is the second argument, falling back to the hook
		// name when it is absent or an empty string. A dynamic second argument
		// cannot be resolved.
		$method_names = [];
		if ( isset( $args[1] ) ) {
			$method_strings = $scope->getType( $args[1]->value )->getConstantStrings();
			if ( $method_strings === [] ) {
				return [];
			}

			foreach ( $method_strings as $method_string ) {
				$method_names[ $method_string->getValue() === '' ? $hook : $method_string->getValue() ] = true;
			}
		} else {
			$method_names[ $hook ] = true;
		}

		$class_names = [];
		foreach ( $scope->getType( $node->var )->getObjectClassReflections() as $class_reflection ) {
			$class_names[ $class_reflection->getName() ] = true;
		}

		$is_filter = $method === 'add_filter';
		$errors    = [];
		foreach ( array_keys( $method_names ) as $method_name ) {
			foreach ( array_keys( $class_names ) as $class_name ) {
				$errors = array_merge(
					$errors,
					$this->check_class_method( $class_name, $method_name, $hook, $is_filter, $node->getStartLine() )
				);
			}
		}

		return $errors;
	}

	/**
	 * Dispatches to the appropriate resolver for the callback expression.
	 *
	 * @param Node   $callback  The callback argument node.
	 * @param Scope  $scope     The current analysis scope.
	 * @param string $hook      The resolved hook name.
	 * @param bool   $is_filter Whether the hook is a filter.
	 *
	 * @return array<int, RuleError>
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

		// Container callback: `$container->callback( Some_Class::class, 'method' )`
		// (lucatume/di52 and StellarWP containers), which returns a callable that
		// invokes Some_Class::method.
		if (
			( $callback instanceof MethodCall || $callback instanceof StaticCall )
			&& $callback->name instanceof Node\Identifier
			&& strtolower( $callback->name->name ) === 'callback'
		) {
			return $this->check_container_callback( $callback, $scope, $hook, $is_filter );
		}

		// Unresolvable callbacks (variables, first-class callables) are skipped.
		return [];
	}

	/**
	 * Checks a container callback - `$container->callback( Class::class, 'method' )`
	 * - by resolving the class/method arguments and inspecting that method. Only
	 * acts when the arguments actually resolve to a real class method, so an
	 * unrelated `->callback()` is harmlessly ignored.
	 *
	 * @param MethodCall|StaticCall $callback  The container `callback()` call node.
	 * @param Scope                 $scope     The current analysis scope.
	 * @param string                $hook      The resolved hook name.
	 * @param bool                  $is_filter Whether the hook is a filter.
	 *
	 * @return array<int, RuleError>
	 */
	private function check_container_callback( Node $callback, Scope $scope, string $hook, bool $is_filter ): array {
		$args = $callback->getArgs();
		if ( count( $args ) < 2 ) {
			return [];
		}

		$class_type = $scope->getType( $args[0]->value );

		$class_names = [];
		foreach ( $class_type->getObjectClassReflections() as $class_reflection ) {
			$class_names[ $class_reflection->getName() ] = true;
		}
		foreach ( $class_type->getConstantStrings() as $constant_string ) {
			$class_names[ $constant_string->getValue() ] = true;
		}

		$errors = [];
		foreach ( $scope->getType( $args[1]->value )->getConstantStrings() as $method_string ) {
			foreach ( array_keys( $class_names ) as $class_name ) {
				$errors = array_merge(
					$errors,
					$this->check_class_method( $class_name, $method_string->getValue(), $hook, $is_filter, $callback->getStartLine() )
				);
			}
		}

		return $errors;
	}

	/**
	 * Checks an inline closure or arrow function using its declared native types.
	 *
	 * @param Closure|ArrowFunction $node      The closure or arrow-function node.
	 * @param string                $hook      The resolved hook name.
	 * @param bool                  $is_filter Whether the hook is a filter.
	 *
	 * @return array<int, RuleError>
	 */
	private function check_closure_node( Node $node, string $hook, bool $is_filter ): array {
		$errors = [];

		foreach ( $node->params as $param ) {
			if ( $param->type === null ) {
				continue;
			}

			$name = $param->var instanceof Node\Expr\Variable && is_string( $param->var->name ) ? $param->var->name : '';

			$errors[] = $this->param_error( $is_filter, '', $hook, $this->type_node_to_string( $param->type ), $name, $param->getStartLine() );
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
	 * @param Array_ $callback  The array-callback node.
	 * @param Scope  $scope     The current analysis scope.
	 * @param string $hook      The resolved hook name.
	 * @param bool   $is_filter Whether the hook is a filter.
	 *
	 * @return array<int, RuleError>
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
	 * @param string $class_name The handler class name.
	 * @param string $method     The handler method name.
	 * @param string $hook       The resolved hook name.
	 * @param bool   $is_filter  Whether the hook is a filter.
	 * @param int    $line       The line to report the violation on.
	 *
	 * @return array<int, RuleError>
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

			$errors[] = $this->param_error(
				$is_filter,
				$declaring . '::' . $method . '()',
				$hook,
				$this->reflection_type_to_string( $parameter->getType() ),
				$parameter->getName(),
				$line
			);
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
	 * @param string $name      The global function name.
	 * @param Scope  $scope     The current analysis scope.
	 * @param string $hook      The resolved hook name.
	 * @param bool   $is_filter Whether the hook is a filter.
	 * @param int    $line      The line to report the violation on.
	 *
	 * @return array<int, RuleError>
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

			$errors[] = $this->param_error(
				$is_filter,
				$name . '()',
				$hook,
				$parameter->getNativeType()->describe( VerbosityLevel::typeOnly() ),
				$parameter->getName(),
				$line
			);
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
	 * matching ReflectionParameter::hasType() semantics used for methods.
	 *
	 * @param Type $type The native type to inspect.
	 *
	 * @return bool
	 */
	private function has_native_type( Type $type ): bool {
		return ! ( $type instanceof MixedType && ! $type->isExplicitMixed() );
	}

	/**
	 * Builds a parameter-type violation error.
	 *
	 * @param bool   $is_filter  Whether the hook is a filter.
	 * @param string $handler    The handler label (e.g. `Foo::bar()`), or '' for closures.
	 * @param string $hook       The resolved hook name.
	 * @param string $type       The offending native type.
	 * @param string $param_name The parameter name, or '' when unknown.
	 * @param int    $line       The line to report the violation on.
	 *
	 * @return RuleError
	 */
	private function param_error( bool $is_filter, string $handler, string $hook, string $type, string $param_name, int $line ): RuleError {
		$where     = $handler === '' ? '' : $handler . ' ';
		$subject   = $param_name === '' ? 'a parameter' : 'parameter $' . $param_name;
		$hook_type = $is_filter ? 'filter' : 'action';

		$message = sprintf(
			'Handler %sfor %s "%s" must not declare the native type "%s" on %s; hook arguments are not type-guaranteed (a hook can be dispatched with unexpected types, including null), so a native type can cause a fatal error.',
			$where,
			$hook_type,
			$hook,
			$type,
			$subject
		);

		return RuleErrorBuilder::message( $message )->identifier( 'stellarwp.hookHandlerParamType' )->line( $line )->build();
	}

	/**
	 * Builds a return-type violation error.
	 *
	 * @param string $hook        The resolved hook name.
	 * @param bool   $is_filter   Whether the hook is a filter.
	 * @param string $return_type The offending native return type.
	 * @param int    $line        The line to report the violation on.
	 * @param string $handler     The handler label (e.g. `Foo::bar()`), or '' for closures.
	 *
	 * @return RuleError
	 */
	private function return_type_error( string $hook, bool $is_filter, string $return_type, int $line, string $handler = '' ): RuleError {
		$where = $handler === '' ? '' : $handler . ' ';

		if ( $is_filter ) {
			$message = sprintf(
				'Handler %sfor filter "%s" must not declare a native return type ("%s"); filter return values are not type-guaranteed and a native return type can cause a fatal error.',
				$where,
				$hook,
				$return_type
			);
		} elseif ( $this->allow_void_return_on_actions ) {
			$message = sprintf(
				'Handler %sfor action "%s" must not declare a native return type ("%s") other than void.',
				$where,
				$hook,
				$return_type
			);
		} else {
			$message = sprintf(
				'Handler %sfor action "%s" must not declare a native return type ("%s").',
				$where,
				$hook,
				$return_type
			);
		}

		return RuleErrorBuilder::message( $message )->identifier( 'stellarwp.hookHandlerReturnType' )->line( $line )->build();
	}

	/**
	 * Whether a native `void` return type is acceptable in this context.
	 *
	 * @param bool   $is_filter   Whether the hook is a filter.
	 * @param string $return_type The declared native return type.
	 *
	 * @return bool
	 */
	private function is_void_allowed( bool $is_filter, string $return_type ): bool {
		return ! $is_filter
			&& $this->allow_void_return_on_actions
			&& strtolower( ltrim( $return_type, '?\\' ) ) === 'void';
	}

	/**
	 * Renders a PhpParser type node to a readable string (for messages and void
	 * detection).
	 *
	 * @param Node $type A parameter or return type node.
	 *
	 * @return string
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
	 * @param ReflectionType|null $type The reflection type.
	 *
	 * @return string
	 */
	private function reflection_type_to_string( ?ReflectionType $type ): string {
		if ( $type instanceof ReflectionNamedType ) {
			return ( $type->allowsNull() && strtolower( $type->getName() ) !== 'null' ? '?' : '' ) . $type->getName();
		}

		return $type === null ? 'mixed' : (string) $type;
	}
}
