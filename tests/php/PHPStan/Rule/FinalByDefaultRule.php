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
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Concrete classes are final unless they are designed as a base.
 *
 * Variation happens through interfaces and injected collaborators, not
 * through inheritance. A class that is open by accident becomes part of the
 * public API the moment a generated plugin extends it.
 *
 * Abstract classes, interfaces, traits and anonymous classes are outside the
 * rule: they cannot be final, or are designed to be extended.
 *
 * @implements Rule<InClassNode>
 */
final class FinalByDefaultRule implements Rule {

	/**
	 * Root namespace the rule applies to, in lowercase.
	 */
	private string $root_namespace;

	/**
	 * Namespaces inside the checked namespace that the rule skips, in lowercase.
	 *
	 * @var array<string>
	 */
	private array $excluded_namespaces;

	/**
	 * Classes deliberately designed as a base for other classes, in lowercase.
	 *
	 * @var array<string>
	 */
	private array $base_classes;

	/**
	 * Instantiate a FinalByDefaultRule object.
	 *
	 * @param string        $root_namespace      Root namespace the rule applies to.
	 * @param array<string> $excluded_namespaces Namespaces inside it to skip.
	 * @param array<string> $base_classes        Classes designed as a base.
	 */
	public function __construct( string $root_namespace, array $excluded_namespaces, array $base_classes ) {
		// PHP compares namespaces and class names without regard to case.
		$this->root_namespace      = strtolower( trim( $root_namespace, '\\' ) ) . '\\';
		$this->excluded_namespaces = array_map(
			static fn( string $excluded ): string => strtolower( trim( $excluded, '\\' ) ) . '\\',
			$excluded_namespaces
		);
		$this->base_classes        = array_map(
			static fn( string $base_class ): string => strtolower( trim( $base_class, '\\' ) ),
			$base_classes
		);
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return InClassNode::class;
	}

	/**
	 * Report a concrete class that is neither final nor a designed base.
	 *
	 * @param InClassNode $node  Class node to inspect.
	 * @param Scope       $scope Scope of the class.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$class = $node->getClassReflection();
		$name  = $class->getName();

		if ( ! $class->isClass()
			|| $class->isAnonymous()
			|| $class->isAbstract()
			|| $class->isFinalByKeyword()
			|| ! $this->is_checked( $name )
			|| in_array( strtolower( $name ), $this->base_classes, true ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'Class %s is not final. Declare it final and vary its behaviour through interfaces and injected '
					. 'collaborators; if it is designed as a base class, add it to '
					. 'parameters.architecture.finalByDefault.baseClasses.',
					$name
				)
			)
				->identifier( RuleManifest::FINAL_BY_DEFAULT )
				->tip( RuleManifest::get_explanation( RuleManifest::FINAL_BY_DEFAULT ) )
				->build(),
		];
	}

	/**
	 * Check whether a class falls under the rule.
	 *
	 * @param string $name Fully qualified class name.
	 * @return bool Whether the class is checked.
	 */
	private function is_checked( string $name ): bool {
		$name = strtolower( $name );

		if ( strpos( $name, $this->root_namespace ) !== 0 ) {
			return false;
		}

		foreach ( $this->excluded_namespaces as $excluded ) {
			if ( strpos( $name, $excluded ) === 0 ) {
				return false;
			}
		}

		return true;
	}
}
