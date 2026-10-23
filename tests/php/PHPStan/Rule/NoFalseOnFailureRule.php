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
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

/**
 * A declared value-or-false return transports a failure through the plugin.
 *
 * Inspect declarations rather than inferred returns: a boolean answer and
 * nullable graceful degradation remain matters for review (principle 10).
 *
 * @implements Rule<FunctionLike>
 */
final class NoFalseOnFailureRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Resolves documented return types, including tool-specific return tags.
	 */
	private FileTypeMapper $file_type_mapper;

	/**
	 * Resolves each return tag, including tags overridden by a tool-specific one.
	 */
	private TypeNodeResolver $type_node_resolver;

	/**
	 * Instantiate a NoFalseOnFailureRule object.
	 *
	 * @param PluginNamespace  $plugin_namespace   Code the rule applies to.
	 * @param FileTypeMapper   $file_type_mapper   Resolves documented return types.
	 * @param TypeNodeResolver $type_node_resolver Resolves each documented type.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		FileTypeMapper $file_type_mapper,
		TypeNodeResolver $type_node_resolver
	) {
		$this->plugin_namespace   = $plugin_namespace;
		$this->file_type_mapper   = $file_type_mapper;
		$this->type_node_resolver = $type_node_resolver;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node interface shared by methods, functions, closures and arrows.
	 */
	public function getNodeType(): string {
		return FunctionLike::class;
	}

	/**
	 * Report a declaration that returns a value or false.
	 *
	 * @param FunctionLike $node  Declaration to inspect.
	 * @param Scope        $scope Scope of the declaration.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		$native = $scope->getFunctionType( $node->getReturnType(), false, false );

		if ( ! $this->unions_false( $native ) && ! $this->documents_false_union( $node, $scope ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				$this->describe( $node, $scope ) . ' declares a return type that unions false with another type.'
				. ' Return the value and throw a dedicated exception on failure. Turn a false returned by a WordPress'
				. ' function into an exception where the call is made.'
			)
				->identifier( RuleManifest::NO_FALSE_ON_FAILURE )
				->tip( RuleManifest::get_explanation( RuleManifest::NO_FALSE_ON_FAILURE ) )
				->build(),
		];
	}

	/**
	 * Resolve every return tag rather than matching type names in text.
	 *
	 * Nested unions, such as array<string|false>, are values rather than
	 * failure alternatives and must not be rejected.
	 *
	 * @param FunctionLike $node  Declaration to inspect.
	 * @param Scope        $scope Scope of the declaration.
	 * @return bool Whether the documented return unions false with another type.
	 */
	private function documents_false_union( FunctionLike $node, Scope $scope ): bool {
		$doc = $node->getDocComment();
		if ( $doc === null ) {
			return false;
		}

		$class    = $scope->getClassReflection();
		$trait    = $scope->getTraitReflection();
		$function = $node instanceof ClassMethod || $node instanceof Function_
			? $node->name->toString()
			: $scope->getFunctionName();
		if ( $node instanceof Function_ ) {
			$function = $scope->getNamespace() . '\\' . $node->name->toString();
		}
		$phpdoc     = $this->file_type_mapper->getResolvedPhpDoc(
			$scope->getFile(),
			$class === null ? null : $class->getName(),
			$trait === null ? null : $trait->getName(),
			$function,
			$doc->getText()
		);
		$name_scope = $phpdoc->getNullableNameScope();
		if ( $name_scope === null ) {
			return false;
		}

		// A narrower phpstan-return must not hide a value-or-failure contract
		// still declared for other readers in return or psalm-return.
		foreach ( $phpdoc->getPhpDocNodes() as $doc_node ) {
			foreach ( [ '@return', '@phpstan-return', '@psalm-return' ] as $tag_name ) {
				foreach ( $doc_node->getReturnTagValues( $tag_name ) as $tag ) {
					if ( $this->unions_false( $this->type_node_resolver->resolve( $tag->type, $name_scope ) ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check only top-level unions that contain the literal false type.
	 *
	 * @param Type $type Return type to inspect.
	 * @return bool Whether false is an alternative to another type.
	 */
	private function unions_false( Type $type ): bool {
		if ( ! $type instanceof UnionType ) {
			return false;
		}

		foreach ( $type->getTypes() as $member ) {
			if ( $member->isFalse()->yes() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Name the declaration so the failure says where to change the contract.
	 *
	 * @param FunctionLike $node  Declaration to name.
	 * @param Scope        $scope Scope of the declaration.
	 * @return string Description of the declaration.
	 */
	private function describe( FunctionLike $node, Scope $scope ): string {
		$class = $scope->getClassReflection();
		if ( $node instanceof ClassMethod && $class !== null ) {
			return sprintf( 'Method %s::%s()', $class->getName(), $node->name->toString() );
		}

		if ( $node instanceof Function_ ) {
			return sprintf( 'Function %s()', $scope->getNamespace() . '\\' . $node->name->toString() );
		}

		return $node instanceof ArrowFunction ? 'An arrow function' : 'A closure';
	}
}
