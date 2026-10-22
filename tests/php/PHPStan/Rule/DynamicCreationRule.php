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
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * `new` of a class chosen at runtime only creates what principle 5 allows.
 *
 * Part of architecture.newOnlyWhereAllowed. When the types at the expression
 * prove that every class it can create is a value object or throwable, it is
 * allowed; otherwise only factories may create objects of a class they are
 * given.
 *
 * @implements Rule<New_>
 */
final class DynamicCreationRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Where `new` is allowed.
	 */
	private CreationAllowances $allowances;

	/**
	 * Instantiate a DynamicCreationRule object.
	 *
	 * @param PluginNamespace    $plugin_namespace Code the rule applies to.
	 * @param CreationAllowances $allowances       Where `new` is allowed.
	 */
	public function __construct( PluginNamespace $plugin_namespace, CreationAllowances $allowances ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->allowances       = $allowances;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return New_::class;
	}

	/**
	 * Report a `new` of a class chosen at runtime outside factories.
	 *
	 * @param New_  $node  Object creation to inspect.
	 * @param Scope $scope Scope of the object creation.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$class = $scope->getClassReflection();

		if ( $node->class instanceof Name
			|| $node->class instanceof Class_
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() )
			|| ( $class !== null && $this->allowances->is_factory( $class ) ) ) {
			return [];
		}

		$created = $scope->getType( $node )->getObjectClassNames();
		$allowed = $created !== [] && array_filter( $created, [ $this->allowances, 'is_value_object' ] ) === $created;

		if ( $allowed ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'new with a class chosen at runtime in %s. Only factories create objects of a class they are'
					. ' given; ask a factory or the injector for it.',
					$scope->getFunction() === null
						? (string) $scope->getNamespace()
						: ( $class === null || $class->isAnonymous() ? 'class@anonymous' : $class->getName() )
							. '::' . $scope->getFunction()->getName() . '()'
				)
			)
				->identifier( RuleManifest::NEW_ONLY_WHERE_ALLOWED )
				->tip( RuleManifest::get_explanation( RuleManifest::NEW_ONLY_WHERE_ALLOWED ) )
				->build(),
		];
	}
}
