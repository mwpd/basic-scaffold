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
 * Constructors do not hook into WordPress.
 *
 * Creating an object should never change global state. A constructor only
 * stores its dependencies; the plugin calls register() at the right time to
 * add the hooks. A closure the constructor creates runs later, so it is not
 * reported here.
 *
 * @implements Rule<FuncCall>
 */
final class NoHooksInConstructorRule implements Rule {

	/**
	 * Functions that change hooks.
	 */
	private const HOOK_FUNCTIONS = [
		'add_action',
		'add_filter',
		'remove_action',
		'remove_filter',
		'do_action',
		'apply_filters',
		'register_activation_hook',
		'register_deactivation_hook',
	];

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Reflection, to tell which function a call refers to.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a NoHooksInConstructorRule object.
	 *
	 * @param PluginNamespace    $plugin_namespace Code the rule applies to.
	 * @param ReflectionProvider $reflection       Reflection, to tell which
	 *                                             function a call refers to.
	 */
	public function __construct( PluginNamespace $plugin_namespace, ReflectionProvider $reflection ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->reflection       = $reflection;
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
	 * Report a hook function called from a constructor.
	 *
	 * @param FuncCall $node  Function call to inspect.
	 * @param Scope    $scope Scope of the call.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$function = $scope->getFunction();

		if ( $function === null
			|| strtolower( $function->getName() ) !== '__construct'
			|| $scope->isInAnonymousFunction()
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		$called = CalledFunction::name_of( $node, $scope, $this->reflection );

		if ( $called === null || ! in_array( $called, self::HOOK_FUNCTIONS, true ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'%1$s() in %2$s hooks into WordPress while the object is being built. Constructors only store'
					. ' dependencies; add hooks in register() (Registerable), which the plugin calls at the right'
					. ' time.',
					$called,
					CalledFunction::describe_location( $scope )
				)
			)
				->identifier( RuleManifest::NO_HOOKS_IN_CONSTRUCTOR )
				->tip( RuleManifest::get_explanation( RuleManifest::NO_HOOKS_IN_CONSTRUCTOR ) )
				->build(),
		];
	}
}
