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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_ as ThrowExpression;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Throw_ as ThrowStatement;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Translating a failure preserves the exception that explains its cause.
 *
 * This checks direct arguments, rather than tracking aliases or proving what
 * a named constructor does with them. Nested catches own their caught variable;
 * function and class bodies have separate scopes.
 *
 * @implements Rule<Catch_>
 */
final class ExceptionsKeepCauseRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Reviewed recoveries allowed to have an empty catch, normalized as PHP names.
	 *
	 * @var array<string>
	 */
	private array $empty_catches;

	/**
	 * Instantiate an ExceptionsKeepCauseRule object.
	 *
	 * @param PluginNamespace $plugin_namespace Code the rule applies to.
	 * @param array<string>   $empty_catches    Methods (`Class::method`) allowed
	 *                                          to recover with an empty catch.
	 */
	public function __construct( PluginNamespace $plugin_namespace, array $empty_catches ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->empty_catches    = array_map(
			static fn( string $method ): string => strtolower( ltrim( $method, '\\' ) ),
			$empty_catches
		);
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return Catch_::class;
	}

	/**
	 * Report discarded causes and translations that omit the caught variable.
	 *
	 * @param Catch_ $node  Catch block to inspect.
	 * @param Scope  $scope Scope of the catch.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		$location = CalledFunction::describe_location( $scope );
		$body     = array_filter( $node->stmts, static fn( Node $statement ): bool => ! $statement instanceof Nop );

		if ( $body === [] && ! $this->allows_empty_catch( $scope ) ) {
			return [
				$this->build_error(
					'Empty catch in ' . $location . ' discards the caught exception.'
						. ' Handle the failure or throw an exception with the caught exception as its previous cause;'
						. ' list reviewed recoveries in parameters.architecture.exceptionsKeepCause.emptyCatches.',
					$node->getStartLine()
				),
			];
		}

		$caught = $node->var !== null && is_string( $node->var->name ) ? $node->var->name : 'caught';
		$errors = [];
		foreach ( $body as $statement ) {
			foreach ( $this->find_throws( $statement ) as $throw ) {
				$created = $throw->expr;
				if ( ! ( $created instanceof New_ || $created instanceof StaticCall ) ) {
					continue;
				}

				foreach ( $created->args as $argument ) {
					if ( $node->var !== null && $argument instanceof Arg && $argument->value instanceof Variable
						&& $argument->value->name === $caught ) {
						continue 2;
					}
				}

				$errors[] = $this->build_error(
					'Throw in ' . $location . ' loses the caught exception $' . $caught . '. Pass $' . $caught
						. ' to the exception constructor or named constructor as the previous cause, or rethrow $'
						. $caught . '.',
					$throw->getStartLine()
				);
			}
		}

		return $errors;
	}

	/**
	 * Find throws in this catch, without entering a different catch or function.
	 *
	 * Both throw node forms are needed for PHPStan 1 and 2's PHP-Parser versions.
	 *
	 * @param Node $node Node within the catch body.
	 * @return list<ThrowStatement|ThrowExpression> Throws within this catch.
	 */
	private function find_throws( Node $node ): array {
		if ( $node instanceof Catch_ || $node instanceof FunctionLike || $node instanceof ClassLike ) {
			return [];
		}

		$throws = $node instanceof ThrowStatement || $node instanceof ThrowExpression ? [ $node ] : [];
		foreach ( $node->getSubNodeNames() as $name ) {
			$children = $node->$name;
			foreach ( is_array( $children ) ? $children : [ $children ] as $child ) {
				if ( $child instanceof Node ) {
					$throws = array_merge( $throws, $this->find_throws( $child ) );
				}
			}
		}

		return $throws;
	}

	/**
	 * Check a method's allowance without extending it to closures inside it.
	 *
	 * @param Scope $scope Scope of the catch.
	 * @return bool Whether this method may have an empty catch.
	 */
	private function allows_empty_catch( Scope $scope ): bool {
		$class    = $scope->getClassReflection();
		$function = $scope->getFunction();

		return $class !== null && $function !== null && ! $scope->isInAnonymousFunction()
			&& in_array( strtolower( $class->getName() . '::' . $function->getName() ), $this->empty_catches, true );
	}

	/**
	 * Build a diagnostic that names the fix and links to the principle.
	 *
	 * @param string $message What is wrong and what to do instead.
	 * @param int    $line    Line of the catch or throw.
	 * @return IdentifierRuleError Error to report.
	 */
	private function build_error( string $message, int $line ): IdentifierRuleError {
		return RuleErrorBuilder::message( $message )
			->line( $line )
			->identifier( RuleManifest::EXCEPTIONS_KEEP_CAUSE )
			->tip( RuleManifest::get_explanation( RuleManifest::EXCEPTIONS_KEEP_CAUSE ) )
			->build();
	}
}
