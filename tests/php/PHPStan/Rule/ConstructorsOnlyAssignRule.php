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
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Building a service only stores its dependencies; work belongs to its lifecycle.
 *
 * Inspect statements rather than individual calls, so control flow and computed
 * assignments cannot hide work. Poka-yoke defaults follow newOnlyWhereAllowed:
 * a nullable interface parameter falls back to an implementation of that interface.
 *
 * @implements Rule<InClassMethodNode>
 */
final class ConstructorsOnlyAssignRule implements Rule {

	private PluginNamespace $plugin_namespace;

	private string $service;

	private string $injector;

	/** @var array<string> Reviewed Class::__construct allowances, normalized. */
	private array $allowed;

	private ReflectionProvider $reflection;

	/**
	 * @param PluginNamespace    $plugin_namespace Code the rule applies to.
	 * @param string             $infrastructure   Namespace containing Service.
	 * @param array<string>      $allowed          Reviewed constructor allowances.
	 * @param ReflectionProvider $reflection       Reflection, to check default interfaces.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		string $infrastructure,
		array $allowed,
		ReflectionProvider $reflection
	) {
		$this->plugin_namespace = $plugin_namespace;
		$this->service          = trim( $infrastructure, '\\' ) . '\Service';
		$this->injector         = trim( $infrastructure, '\\' ) . '\Injector';
		$this->allowed          = array_map( [ PluginNamespace::class, 'normalize' ], $allowed );
		$this->reflection       = $reflection;
	}

	public function getNodeType(): string {
		return InClassMethodNode::class;
	}

	/**
	 * @param InClassMethodNode $node  Method to inspect.
	 * @param Scope             $scope Scope of the method.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$method = $node->getOriginalNode();
		$class  = $node->getClassReflection();

		if ( $method->name->toLowerString() !== '__construct'
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() )
			|| ! $class->implementsInterface( $this->service ) ) {
			return [];
		}

		// Injector extends Service to make it injectable, but bootstrapping the
		// object graph is infrastructure work, outside service lifecycle checks.
		if ( $class->implementsInterface( $this->injector ) ) {
			return [];
		}

		$location = ( $class->isAnonymous() ? 'class@anonymous' : $class->getName() ) . '::__construct';
		if ( ! $class->isAnonymous()
			&& in_array( PluginNamespace::normalize( $location ), $this->allowed, true ) ) {
			return [];
		}

		$parameters = [];
		foreach ( $method->params as $parameter ) {
			if ( $parameter->var instanceof Variable && is_string( $parameter->var->name ) ) {
				$parameters[ $parameter->var->name ] = $parameter;
			}
		}

		$errors = [];
		foreach ( $method->stmts ?? [] as $statement ) {
			if ( $statement instanceof Nop ) {
				continue;
			}

			if ( $statement instanceof Expression
				&& ( $this->is_assignment( $statement->expr, $parameters, $scope )
					|| self::is_parent_constructor( $statement->expr ) ) ) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(
				$location . '() does work beyond storing dependencies.'
				. ' Keep only parameter-to-property assignments and poka-yoke defaults in service constructors.'
				. ' Move the work into register() or a callback, or into a factory if it is construction logic.'
			)
				->identifier( RuleManifest::CONSTRUCTORS_ONLY_ASSIGN )
				->tip( RuleManifest::get_explanation( RuleManifest::CONSTRUCTORS_ONLY_ASSIGN ) )
				->line( $statement->getStartLine() )
				->build();
		}

		return $errors;
	}

	/**
	 * @param Expr                 $expression Constructor expression.
	 * @param array<string, Param> $parameters Constructor parameters, keyed by name.
	 * @param Scope                $scope      Scope for resolving the default's type.
	 * @return bool Whether this only stores a parameter or its poka-yoke default.
	 */
	private function is_assignment( Expr $expression, array $parameters, Scope $scope ): bool {
		if ( ! $expression instanceof Assign
			|| ! $expression->var instanceof PropertyFetch
			|| ! $expression->var->var instanceof Variable
			|| $expression->var->var->name !== 'this'
			|| ! $expression->var->name instanceof Identifier ) {
			return false;
		}

		$value = $expression->expr;
		if ( $value instanceof Variable && is_string( $value->name ) ) {
			return isset( $parameters[ $value->name ] );
		}

		if ( ! $value instanceof Coalesce
			|| ! $value->left instanceof Variable
			|| ! is_string( $value->left->name )
			|| ! $value->right instanceof New_ ) {
			return false;
		}

		$parameter = $parameters[ $value->left->name ] ?? null;
		if ( $parameter === null ) {
			return false;
		}

		$type     = $parameter->type instanceof NullableType ? $parameter->type->type : $parameter->type;
		$nullable = $parameter->type instanceof NullableType
			|| ( $parameter->default instanceof ConstFetch && $parameter->default->name->toLowerString() === 'null' );

		if ( ! $nullable || ! $type instanceof Name ) {
			return false;
		}

		$interface = $scope->resolveName( $type );

		return $this->reflection->hasClass( $interface )
			&& $this->reflection->getClass( $interface )->isInterface()
			&& ( new ObjectType( $interface ) )->isSuperTypeOf( $scope->getType( $value->right ) )->yes();
	}

	/**
	 * WordPress base classes may require their own constructor to be called.
	 *
	 * @param Expr $expression Constructor expression.
	 * @return bool Whether this calls the parent's constructor.
	 */
	private static function is_parent_constructor( Expr $expression ): bool {
		if ( ! $expression instanceof StaticCall
			|| ! $expression->class instanceof Name
			|| $expression->class->toLowerString() !== 'parent'
			|| ! $expression->name instanceof Identifier
			|| $expression->name->toLowerString() !== '__construct' ) {
			return false;
		}

		// A first-class callable only creates a callback; it does not call parent.
		foreach ( $expression->args as $argument ) {
			if ( ! $argument instanceof Arg ) {
				return false;
			}
		}

		return true;
	}
}
