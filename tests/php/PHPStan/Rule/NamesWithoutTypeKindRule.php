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
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Type names survive promotion from a concrete class to an interface.
 *
 * Implementations say what distinguishes them. A name that encodes the kind
 * of type would force every caller to rename its type hints on promotion.
 * Inspect declarations directly so unused traits are checked too.
 *
 * @implements Rule<ClassLike>
 */
final class NamesWithoutTypeKindRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * The only interface allowed the Exception suffix, normalized.
	 */
	private string $marker;

	/**
	 * Reviewed allowances, normalized fully qualified names.
	 *
	 * @var array<string>
	 */
	private array $allowed;

	/**
	 * Instantiate a NamesWithoutTypeKindRule object.
	 *
	 * @param PluginNamespace $plugin_namespace Code the rule applies to.
	 * @param string          $marker           Marker interface of the plugin's exceptions.
	 * @param array<string>   $allowed          Reviewed type names allowed to encode their kind.
	 */
	public function __construct( PluginNamespace $plugin_namespace, string $marker, array $allowed ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->marker           = PluginNamespace::normalize( $marker );
		$this->allowed          = array_map( [ PluginNamespace::class, 'normalize' ], $allowed );
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return ClassLike::class;
	}

	/**
	 * Report names that encode whether a type is a class, interface or trait.
	 *
	 * @param ClassLike $node  Type declaration to inspect.
	 * @param Scope     $scope Scope of the declaration.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( $node->name === null
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		$name       = $scope->getNamespace() . '\\' . $node->name->toString();
		$normalized = PluginNamespace::normalize( $name );

		if ( in_array( $normalized, $this->allowed, true ) ) {
			return [];
		}

		// The marker's suffix is an exception, not its other kind markers.
		$suffixes   = $node instanceof Interface_ && $normalized === $this->marker
			? 'Interface|Trait|Abstract|Impl|Implementation|Class'
			: 'Interface|Trait|Abstract|Impl|Implementation|Class|Exception';
		$suggestion = preg_replace( '/^(?:I(?=[A-Z])|Abstract|Base)+/', '', $node->name->toString() );
		$suggestion = preg_replace( '/(?:' . $suffixes . ')+$/D', '', $suggestion ?? $node->name->toString() );

		if ( $suggestion === $node->name->toString() ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'Type %s encodes its kind in its name. Use %s instead, so promoting a class to an interface keeps'
					. ' type hints, instanceof and catch unchanged. Name implementations by what distinguishes them;'
					. ' reviewed exceptions belong in parameters.architecture.namesWithoutTypeKind.allowed.',
					$name,
					$suggestion === null || $suggestion === '' ? 'a name for the concept' : $suggestion
				)
			)
				->identifier( RuleManifest::NAMES_WITHOUT_TYPE_KIND )
				->tip( RuleManifest::get_explanation( RuleManifest::NAMES_WITHOUT_TYPE_KIND ) )
				->build(),
		];
	}
}
