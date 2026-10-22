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
 * register() does not decide whether a service applies to the request.
 *
 * An `is_admin()` check wrapped around the hooks in register() hides a
 * service's condition inside its code. Conditional::is_needed() states it
 * once, and the plugin never even builds a service that is not needed.
 *
 * Request-context functions stay allowed everywhere else, including in
 * callbacks registered by register(): they run later, when the answer may
 * have changed.
 *
 * @implements Rule<FuncCall>
 */
final class NoContextChecksInRegisterRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Request-context functions, in lowercase.
	 *
	 * @var array<string>
	 */
	private array $context_functions;

	/**
	 * Reflection, to tell which function a call refers to.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a NoContextChecksInRegisterRule object.
	 *
	 * @param PluginNamespace    $plugin_namespace  Code the rule applies to.
	 * @param array<string>      $context_functions Request-context functions.
	 * @param ReflectionProvider $reflection        Reflection, to tell which
	 *                                              function a call refers to.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		array $context_functions,
		ReflectionProvider $reflection
	) {
		$this->plugin_namespace  = $plugin_namespace;
		$this->context_functions = array_map( 'strtolower', $context_functions );
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
	 * Report a request-context function called directly in register().
	 *
	 * @param FuncCall $node  Function call to inspect.
	 * @param Scope    $scope Scope of the call.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$function = $scope->getFunction();

		if ( $function === null
			|| $scope->getClassReflection() === null
			|| strtolower( $function->getName() ) !== 'register'
			|| $scope->isInAnonymousFunction()
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		$called = CalledFunction::name_of( $node, $scope, $this->reflection );

		if ( $called === null || ! in_array( $called, $this->context_functions, true ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'%1$s() in %2$s decides at registration whether the service applies to this request. Implement'
					. ' Conditional and return the answer from is_needed(), so the plugin does not even build the'
					. ' service when it is not needed.',
					$called,
					CalledFunction::describe_location( $scope )
				)
			)
				->identifier( RuleManifest::NO_CONTEXT_CHECKS_IN_REGISTER )
				->tip( RuleManifest::get_explanation( RuleManifest::NO_CONTEXT_CHECKS_IN_REGISTER ) )
				->build(),
		];
	}
}
