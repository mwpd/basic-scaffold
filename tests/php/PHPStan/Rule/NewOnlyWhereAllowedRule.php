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
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * `new` only creates what principle 5 allows it to.
 *
 * Collaborators come from the injector, so a class that needs one receives it
 * through its constructor. `new` is left for three cases:
 *
 * - inside a factory, whose job is to create objects: a class named
 *   `…Factory`, one implementing an interface named `…Factory`, or one
 *   implementing a configured factory interface, such as the injector's;
 * - for a poka-yoke default: `$this->property = $parameter ?? new Default()`
 *   in a constructor, where the parameter is nullable and typed to an
 *   interface the default implements;
 * - for value objects and exceptions: classes in the configured list, and
 *   anything throwable, whose creation the exception rules check.
 *
 * Everything else, including `new` of a WordPress class such as WP_Query,
 * is reported.
 *
 * @implements Rule<InClassMethodNode>
 */
final class NewOnlyWhereAllowedRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Where `new` is allowed.
	 */
	private CreationAllowances $allowances;

	/**
	 * Reflection, to inspect the classes being created.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a NewOnlyWhereAllowedRule object.
	 *
	 * @param PluginNamespace    $plugin_namespace Code the rule applies to.
	 * @param CreationAllowances $allowances       Where `new` is allowed.
	 * @param ReflectionProvider $reflection       Reflection, to inspect the
	 *                                             classes being created.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		CreationAllowances $allowances,
		ReflectionProvider $reflection
	) {
		$this->plugin_namespace = $plugin_namespace;
		$this->allowances       = $allowances;
		$this->reflection       = $reflection;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * The rule looks at whole methods, because whether `new` is a poka-yoke
	 * default depends on the expression around it.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return InClassMethodNode::class;
	}

	/**
	 * Report every `new` in a method that principle 5 does not allow.
	 *
	 * @param InClassMethodNode $node  Method node to inspect.
	 * @param Scope             $scope Scope of the method.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$class  = $node->getClassReflection();
		$method = $node->getOriginalNode();

		if ( ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() )
			|| $this->allowances->is_factory( $class ) ) {
			return [];
		}

		$location = sprintf(
			'%s::%s()',
			$class->isAnonymous() ? 'class@anonymous' : $class->getName(),
			$method->name->toString()
		);
		$defaults = $method->name->toLowerString() === '__construct' ? self::find_defaults( $method->stmts ?? [] ) : [];
		$errors   = [];

		foreach ( self::find_creations( $method->stmts ?? [] ) as $creation ) {
			$problem = $this->check( $creation, $class, $scope, $method->params, $defaults, $location );

			if ( $problem !== null ) {
				$errors[] = RuleErrorBuilder::message( $problem )
					->identifier( RuleManifest::NEW_ONLY_WHERE_ALLOWED )
					->tip( RuleManifest::get_explanation( RuleManifest::NEW_ONLY_WHERE_ALLOWED ) )
					->line( $creation->getStartLine() )
					->build();
			}
		}

		return $errors;
	}

	/**
	 * Check one `new` expression.
	 *
	 * @param New_                 $creation Object creation.
	 * @param ClassReflection      $owner    Class the method belongs to.
	 * @param Scope                $scope    Scope of the method.
	 * @param array<Param>         $params   Parameters of the method.
	 * @param array<int, Variable> $defaults Poka-yoke defaults: the parameter
	 *                                       each `new` is the default for,
	 *                                       keyed by object id.
	 * @param string               $location Method, for the message.
	 * @return string|null What is wrong and what to do instead, or null.
	 */
	private function check(
		New_ $creation,
		ClassReflection $owner,
		Scope $scope,
		array $params,
		array $defaults,
		string $location
	): ?string {
		// A class chosen at runtime needs the types inside the method, see
		// DynamicCreationRule.
		if ( ! $creation->class instanceof Name && ! $creation->class instanceof Class_ ) {
			return null;
		}

		$created   = $this->resolve_created( $creation, $owner, $scope );
		$parameter = $defaults[ spl_object_id( $creation ) ] ?? null;

		if ( $this->allowances->is_value_object( $created ) ) {
			return null;
		}

		if ( $parameter !== null ) {
			return $this->check_default( $created, $parameter, $params, $location );
		}

		if ( $creation->class instanceof Class_ ) {
			return sprintf(
				'new class() in %s creates a collaborator. Write it as a class of its own, inject it through the'
				. ' constructor and bind it in the composition root, or create it in a factory.',
				$location
			);
		}

		return sprintf(
			'new %1$s in %2$s creates a collaborator. Inject it through the constructor, typed to its interface,'
			. ' and bind it in the composition root; if %1$s is a value object, add it to'
			. ' parameters.architecture.newOnlyWhereAllowed.valueObjects.',
			$created,
			$location
		);
	}

	/**
	 * Get the name of the class a `new` expression creates.
	 *
	 * @param New_            $creation Object creation with a class name or an
	 *                                  anonymous class.
	 * @param ClassReflection $owner    Class the method belongs to.
	 * @param Scope           $scope    Scope of the method.
	 * @return string Name of the class, as PHPStan knows it.
	 */
	private function resolve_created( New_ $creation, ClassReflection $owner, Scope $scope ): string {
		if ( $creation->class instanceof Class_ ) {
			return $this->reflection->getAnonymousClassReflection( $creation->class, $scope )->getName();
		}

		$name = $creation->class;
		if ( ! $name instanceof Name ) {
			return 'class()';
		}

		if ( $name->toLowerString() === 'parent' ) {
			$parent = $owner->getParentClass();

			return $parent === null ? 'parent' : $parent->getName();
		}

		return $name->isSpecialClassName() ? $owner->getName() : $name->toString();
	}

	/**
	 * Check a poka-yoke default.
	 *
	 * @param string       $created   Class being created.
	 * @param Variable     $parameter Parameter the object is the default for.
	 * @param array<Param> $params    Parameters of the constructor.
	 * @param string       $location  Constructor, for the message.
	 * @return string|null What is wrong and what to do instead, or null.
	 */
	private function check_default( string $created, Variable $parameter, array $params, string $location ): ?string {
		foreach ( $params as $param ) {
			if ( ! $param->var instanceof Variable || $param->var->name !== $parameter->name ) {
				continue;
			}

			$type     = $param->type instanceof NullableType ? $param->type->type : $param->type;
			$nullable = $param->type instanceof NullableType
				|| ( $param->default instanceof ConstFetch && $param->default->name->toLowerString() === 'null' );

			if ( $nullable
				&& $type instanceof Name
				&& $this->reflection->hasClass( $type->toString() )
				&& $this->reflection->getClass( $type->toString() )->isInterface()
				&& $this->reflection->hasClass( $created )
				&& $this->reflection->getClass( $created )->implementsInterface( $type->toString() ) ) {
				return null;
			}
		}

		return sprintf(
			'new %1$s in %2$s is a default for $%3$s, but $%3$s is not a nullable parameter typed to an interface'
			. ' %1$s implements. Type it as ?SomeInterface with a default of null, so callers can pass their own'
			. ' implementation.',
			strpos( $created, 'AnonymousClass' ) === 0 ? 'class()' : $created,
			$location,
			is_string( $parameter->name ) ? $parameter->name : '…'
		);
	}

	/**
	 * Find the `new` expressions in statements.
	 *
	 * A nested class's methods are inspected on their own.
	 *
	 * @param array<Node> $nodes Statements to search.
	 * @return array<New_> Object creations.
	 */
	private static function find_creations( array $nodes ): array {
		$creations = [];

		foreach ( $nodes as $node ) {
			if ( $node instanceof New_ ) {
				$creations[] = $node;
			}

			foreach ( $node->getSubNodeNames() as $sub_node_name ) {
				$children = $node->$sub_node_name;
				foreach ( is_array( $children ) ? $children : [ $children ] as $child ) {
					if ( $child instanceof Node && ! $child instanceof ClassLike ) {
						$creations = array_merge( $creations, self::find_creations( [ $child ] ) );
					}
				}
			}
		}

		return $creations;
	}

	/**
	 * Find the poka-yoke defaults in a constructor.
	 *
	 * A default is `$this->property = $parameter ?? new Default()`.
	 *
	 * @param array<Node> $nodes Statements of the constructor.
	 * @return array<int, Variable> The variable each default stands in for,
	 *                              keyed by the object id of its `new`.
	 */
	private static function find_defaults( array $nodes ): array {
		$defaults = [];

		foreach ( $nodes as $node ) {
			if ( $node instanceof Assign
				&& $node->var instanceof PropertyFetch
				&& $node->var->var instanceof Variable
				&& $node->var->var->name === 'this'
				&& $node->expr instanceof Coalesce
				&& $node->expr->left instanceof Variable
				&& $node->expr->right instanceof New_ ) {
				$defaults[ spl_object_id( $node->expr->right ) ] = $node->expr->left;
			}

			foreach ( $node->getSubNodeNames() as $sub_node_name ) {
				$children = $node->$sub_node_name;
				foreach ( is_array( $children ) ? $children : [ $children ] as $child ) {
					// A closure's variables are not the constructor's parameters.
					if ( $child instanceof Node && ! $child instanceof ClassLike && ! $child instanceof FunctionLike ) {
						$defaults += self::find_defaults( [ $child ] );
					}
				}
			}
		}

		return $defaults;
	}
}
