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
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * Feature subsystems receive collaborators and only use injectors for creation.
 *
 * @implements Rule<Node>
 */
final class NoServiceLocatorRule implements Rule {

	private DependencyContext $context;
	private string $container;
	private ObjectType $injector;

	/** @var array<string> Allowed injector methods, normalized. */
	private array $allowed;

	/**
	 * @param DependencyContext $context Shared dependency positions and layers.
	 * @param string $container Container interface forbidden in feature code.
	 * @param string $injector Injector interface whose methods are restricted.
	 * @param array<string> $allowed_injector_methods Methods allowed in features.
	 */
	public function __construct(
		DependencyContext $context,
		string $container,
		string $injector,
		array $allowed_injector_methods
	) {
		$this->context   = $context;
		$this->container = PluginNamespace::normalize( $container );
		$this->injector  = new ObjectType( ltrim( $injector, '\\' ) );
		$this->allowed   = array_map( 'strtolower', $allowed_injector_methods );
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
		if ( $node instanceof FileNode ) {
			return $this->check_container( $node, $scope );
		}

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
				$errors[] = $this->error(
					'Calls ' . $this->injector->getClassName() . '::' . $method . '() in a feature subsystem.'
					. ' Configure bindings, sharing and delegations in the composition root;'
					. ' receive collaborators through the constructor or use the allowed injector methods'
					. ' for on-demand creation.',
					$node->getStartLine()
				);
			}
		}

		return $errors;
	}

	/**
	 * @param FileNode $node File to inspect.
	 * @param Scope $scope Scope of the file.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	private function check_container( FileNode $node, Scope $scope ): array {
		$errors = [];
		foreach ( $this->context->get_mentions( $node, $scope ) as [ $source, $dependency, $line ] ) {
			if ( PluginNamespace::normalize( $dependency ) === $this->container
				&& $this->context->is_feature_subsystem( $source, $scope->getFile() ) ) {
				$errors[] = $this->error(
					$source . ' depends on the service container ' . $dependency . '.'
					. ' Receive the collaborator through the constructor instead of looking it up in the container.',
					$line
				);
			}
		}
		return $errors;
	}

	/**
	 * @param string $message What is wrong and what to do instead.
	 * @param int $line Line of the dependency or call.
	 * @return IdentifierRuleError Error with the rule's guidance.
	 */
	private function error( string $message, int $line ): IdentifierRuleError {
		return RuleErrorBuilder::message( $message )
			->identifier( RuleManifest::NO_SERVICE_LOCATOR )
			->tip( RuleManifest::get_explanation( RuleManifest::NO_SERVICE_LOCATOR ) )
			->line( $line )
			->build();
	}
}
