<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Rule;

use MWPD\BasicScaffold\Tests\Architecture\RuleManifest;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Feature services add and remove hooks only in their lifecycle methods.
 *
 * A service says once, in register(), what it hooks into; activate() and
 * deactivate() do the same for those events. Hooks added anywhere else, in a
 * helper or in a callback, make the service's behaviour depend on the order
 * things happen in, which is what the capability interfaces exist to avoid.
 * A hook that has to wait for a later action belongs to a Delayed service.
 *
 * The infrastructure, which orchestrates the lifecycle, and the composition
 * root are exempt. A closure, even one created in register(), is a callback
 * and counts as code outside the lifecycle method. Calls made directly in a
 * constructor are left to NoHooksInConstructorRule.
 *
 * @implements Rule<FuncCall>
 */
final class HooksInLifecycleMethodsRule implements Rule {

	/**
	 * Functions that add or remove hooks.
	 */
	private const HOOK_FUNCTIONS = [ 'add_action', 'add_filter', 'remove_action', 'remove_filter' ];

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Namespace of the infrastructure, which orchestrates the lifecycle.
	 */
	private PluginNamespace $infrastructure;

	/**
	 * Namespace of the exceptions.
	 */
	private PluginNamespace $exceptions;

	/**
	 * Classes of the composition root, normalized.
	 *
	 * @var array<string>
	 */
	private array $composition_root;

	/**
	 * Interface each lifecycle method belongs to, keyed by lowercase method.
	 *
	 * @var array<string, string>
	 */
	private array $lifecycle_methods;

	/**
	 * Reflection, to tell which function a call refers to.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a HooksInLifecycleMethodsRule object.
	 *
	 * @param PluginNamespace       $plugin_namespace  Code the rule applies to.
	 * @param string                $infrastructure    Namespace of the
	 *                                                 infrastructure.
	 * @param string                $exceptions        Namespace of the
	 *                                                 exceptions.
	 * @param array<string>         $composition_root  Classes of the
	 *                                                 composition root.
	 * @param array<string, string> $lifecycle_methods Interface each lifecycle
	 *                                                 method belongs to, keyed
	 *                                                 by method.
	 * @param ReflectionProvider    $reflection        Reflection, to tell
	 *                                                 which function a call
	 *                                                 refers to.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		string $infrastructure,
		string $exceptions,
		array $composition_root,
		array $lifecycle_methods,
		ReflectionProvider $reflection
	) {
		$this->plugin_namespace  = $plugin_namespace;
		$this->infrastructure    = new PluginNamespace( $infrastructure );
		$this->exceptions        = new PluginNamespace( $exceptions );
		$this->composition_root  = array_map( [ PluginNamespace::class, 'normalize' ], $composition_root );
		$this->lifecycle_methods = array_change_key_case( $lifecycle_methods, CASE_LOWER );
		$this->reflection        = $reflection;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return FuncCall::class;
	}

	/**
	 * Report a hook added or removed outside a lifecycle method.
	 *
	 * @param FuncCall $node  Function call to inspect.
	 * @param Scope    $scope Scope of the call.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$called = CalledFunction::name_of( $node, $scope, $this->reflection );

		if ( $called === null
			|| ! in_array( $called, self::HOOK_FUNCTIONS, true )
			|| ! $this->is_feature_code( $scope )
			|| $this->is_in_lifecycle_method( $scope )
			|| $this->is_in_constructor( $scope ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'%1$s() in %2$s changes a hook outside a lifecycle method. Add it in register() of a class'
					. ' implementing Registerable, or in activate() or deactivate() for Activateable and'
					. ' Deactivateable; if it must wait for a later action, implement Delayed.',
					$called,
					CalledFunction::describe_location( $scope )
				)
			)
				->identifier( RuleManifest::HOOKS_IN_LIFECYCLE_METHODS )
				->tip( RuleManifest::get_explanation( RuleManifest::HOOKS_IN_LIFECYCLE_METHODS ) )
				->build(),
		];
	}

	/**
	 * Check whether code belongs to a feature subsystem.
	 *
	 * @param Scope $scope Scope of the code.
	 * @return bool Whether the rule applies to it.
	 */
	private function is_feature_code( Scope $scope ): bool {
		$namespace = $scope->getNamespace();
		$class     = $scope->getClassReflection();

		return $this->plugin_namespace->contains_namespace( $namespace )
			&& ! $this->infrastructure->contains_namespace( $namespace )
			&& ! $this->exceptions->contains_namespace( $namespace )
			&& ! ( $class !== null
				&& in_array( PluginNamespace::normalize( $class->getName() ), $this->composition_root, true ) );
	}

	/**
	 * Check whether code runs directly in a constructor.
	 *
	 * NoHooksInConstructorRule reports those calls, with their own fix.
	 *
	 * @param Scope $scope Scope of the code.
	 * @return bool Whether it is directly in a constructor.
	 */
	private function is_in_constructor( Scope $scope ): bool {
		$function = $scope->getFunction();

		return $function !== null
			&& ! $scope->isInAnonymousFunction()
			&& strtolower( $function->getName() ) === '__construct';
	}

	/**
	 * Check whether code runs directly in a lifecycle method of its class.
	 *
	 * @param Scope $scope Scope of the code.
	 * @return bool Whether it is in register(), activate() or deactivate() of
	 *              a class implementing the matching interface.
	 */
	private function is_in_lifecycle_method( Scope $scope ): bool {
		$class    = $scope->getClassReflection();
		$function = $scope->getFunction();

		if ( $class === null || $function === null || $scope->isInAnonymousFunction() ) {
			return false;
		}

		$interface = $this->lifecycle_methods[ strtolower( $function->getName() ) ] ?? null;

		return $interface !== null && $class->implementsInterface( $interface );
	}
}
