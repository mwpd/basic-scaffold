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
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * Feature subsystems only use the injector for on-demand creation.
 *
 * This is the injector half of architecture.noServiceLocator, next to
 * NoServiceLocatorRule. It receives every node because a method call can be
 * a plain or a nullsafe call, which are separate node types.
 *
 * @implements Rule<Node>
 */
final class NoInjectorConfigurationRule implements Rule {

	private DependencyContext $context;
	private ObjectType $injector;

	/** @var array<string> Allowed injector methods, normalized. */
	private array $allowed;

	/**
	 * @param DependencyContext $context Shared dependency positions and layers.
	 * @param string $injector Injector interface whose methods are restricted.
	 * @param array<string> $allowed_injector_methods Methods allowed in features.
	 */
	public function __construct( DependencyContext $context, string $injector, array $allowed_injector_methods ) {
		$this->context  = $context;
		$this->injector = new ObjectType( ltrim( $injector, '\\' ) );
		$this->allowed  = array_map( 'strtolower', $allowed_injector_methods );
	}

	public function getNodeType(): string {
		return Node::class;
	}

	/**
	 * @param Node $node Node to inspect.
	 * @param Scope $scope Scope of the node.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall ) {
			return [];
		}

		$class  = $scope->getTraitReflection() ?? $scope->getClassReflection();
		$source = $class !== null
			? ( $class->isAnonymous()
				? ltrim( ( $scope->getNamespace() ?? '' ) . '\\class@anonymous', '\\' )
				: $class->getName() )
			: ltrim( ( $scope->getNamespace() ?? '' ) . '\\' . basename( $scope->getFile(), '.php' ), '\\' );

		if ( ! $this->context->is_feature_subsystem( $source, $scope->getFile() ) ) {
			return [];
		}

		// Object class names include implementations, nullable types and unions.
		// An unrelated receiver with a method named bind() is not an injector.
		$is_injector = false;
		foreach ( TypeCombinator::removeNull( $scope->getType( $node->var ) )->getObjectClassNames() as $name ) {
			if ( $this->injector->isSuperTypeOf( new ObjectType( $name ) )->yes() ) {
				$is_injector = true;
				break;
			}
		}
		if ( ! $is_injector ) {
			return [];
		}

		$methods = $node->name instanceof Identifier
			? [ $node->name->toString() ]
			: array_map(
				static fn( $type ): string => $type->getValue(),
				$scope->getType( $node->name )->getConstantStrings()
			);

		// A runtime name with no finite set of values cannot prove it only creates.
		$methods = $methods === [] ? [ '{dynamic}' ] : array_unique( $methods );
		$errors  = [];
		foreach ( $methods as $method ) {
			if ( ! in_array( strtolower( $method ), $this->allowed, true ) ) {
				$errors[] = RuleErrorBuilder::message(
					'Calls ' . $this->injector->getClassName() . '::' . $method . '() in a feature subsystem.'
					. ' Configure bindings, sharing and delegations in the composition root;'
					. ' receive collaborators through the constructor or use the allowed injector methods'
					. ' for on-demand creation.'
				)
					->identifier( RuleManifest::NO_SERVICE_LOCATOR )
					->tip( RuleManifest::get_explanation( RuleManifest::NO_SERVICE_LOCATOR ) )
					->line( $node->getStartLine() )
					->build();
			}
		}

		return $errors;
	}
}
