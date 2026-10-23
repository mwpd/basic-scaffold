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
 * Query-dependent conditions wait until WordPress has run the query.
 *
 * Conditional::is_needed() runs before the service is built. Delayed lets
 * the plugin wait before asking it, so query-dependent answers are meaningful.
 * Only direct calls count: helpers and deferred callbacks are outside this
 * check, just as they are for NoContextChecksInRegisterRule.
 *
 * @implements Rule<FuncCall>
 */
final class QueryContextNeedsDelayRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Namespace of the capability interfaces.
	 */
	private string $infrastructure;

	/**
	 * Query-dependent functions, in lowercase.
	 *
	 * @var array<string>
	 */
	private array $query_functions;

	/**
	 * Reflection, to tell which function a call refers to.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a QueryContextNeedsDelayRule object.
	 *
	 * @param PluginNamespace    $plugin_namespace Code the rule applies to.
	 * @param string             $infrastructure   Namespace of the capability
	 *                                             interfaces.
	 * @param array<string>      $query_functions  Query-dependent functions.
	 * @param ReflectionProvider $reflection       Reflection, to tell which
	 *                                             function a call refers to.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		string $infrastructure,
		array $query_functions,
		ReflectionProvider $reflection
	) {
		$this->plugin_namespace = $plugin_namespace;
		$this->infrastructure   = trim( $infrastructure, '\\' );
		$this->query_functions  = array_map( 'strtolower', $query_functions );
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
	 * Report a query-dependent function called directly in is_needed().
	 *
	 * @param FuncCall $node  Function call to inspect.
	 * @param Scope    $scope Scope of the call.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$function = $scope->getFunction();
		$class    = $scope->getClassReflection();

		if ( $function === null
			|| $class === null
			|| strtolower( $function->getName() ) !== 'is_needed'
			|| $scope->isInAnonymousFunction()
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() )
			|| ! $class->implementsInterface( $this->infrastructure . '\Conditional' )
			|| $class->implementsInterface( $this->infrastructure . '\Delayed' ) ) {
			return [];
		}

		$called = CalledFunction::name_of( $node, $scope, $this->reflection );

		if ( $called === null || ! in_array( $called, $this->query_functions, true ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'%1$s() in %2$s asks about the query before it is available. Implement Delayed and return'
					. ' "wp" or a later action from get_registration_action(), so the plugin waits for the query'
					. ' before checking is_needed().',
					$called,
					CalledFunction::describe_location( $scope )
				)
			)
				->identifier( RuleManifest::QUERY_CONTEXT_NEEDS_DELAY )
				->tip( RuleManifest::get_explanation( RuleManifest::QUERY_CONTEXT_NEEDS_DELAY ) )
				->build(),
		];
	}
}
