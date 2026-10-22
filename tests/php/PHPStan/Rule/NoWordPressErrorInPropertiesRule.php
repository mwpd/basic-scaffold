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

use PhpParser\Node;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * No property of the plugin holds a WP_Error.
 *
 * Part of architecture.noWordPressErrorMechanisms, see WordPressErrorTransport.
 *
 * @implements Rule<ClassPropertyNode>
 */
final class NoWordPressErrorInPropertiesRule implements Rule {

	/**
	 * What the WP_Error rules share.
	 */
	private WordPressErrorTransport $transport;

	/**
	 * Instantiate a NoWordPressErrorInPropertiesRule object.
	 *
	 * @param WordPressErrorTransport $transport What the WP_Error rules share.
	 */
	public function __construct( WordPressErrorTransport $transport ) {
		$this->transport = $transport;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return ClassPropertyNode::class;
	}

	/**
	 * Report a property whose type allows a WP_Error.
	 *
	 * @param ClassPropertyNode $node  Node to inspect.
	 * @param Scope $scope Scope of the node.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $this->transport->applies_to( $scope ) ) {
			return [];
		}

		$class = $node->getClassReflection();
		$name  = $node->getName();

		if ( ! $class->hasNativeProperty( $name )
			|| ! $this->transport->mentions_wp_error( $class->getNativeProperty( $name )->getReadableType() ) ) {
			return [];
		}

		return [
			$this->transport->build_error(
				sprintf( 'Property %s::$%s holds a WP_Error.', $class->getName(), $name )
			),
		];
	}
}
