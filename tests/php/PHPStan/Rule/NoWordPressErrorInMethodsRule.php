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
use PHPStan\Node\InClassMethodNode;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * No method of the plugin takes or returns a WP_Error.
 *
 * Part of architecture.noWordPressErrorMechanisms, see WordPressErrorTransport.
 *
 * @implements Rule<InClassMethodNode>
 */
final class NoWordPressErrorInMethodsRule implements Rule {

	/**
	 * What the WP_Error rules share.
	 */
	private WordPressErrorTransport $transport;

	/**
	 * Instantiate a NoWordPressErrorInMethodsRule object.
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
		return InClassMethodNode::class;
	}

	/**
	 * Report a method that takes or returns a WP_Error.
	 *
	 * @param InClassMethodNode $node  Node to inspect.
	 * @param Scope $scope Scope of the node.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $this->transport->applies_to( $scope ) ) {
			return [];
		}

		$method = $node->getMethodReflection();

		if ( ! $this->transport->signature_mentions_wp_error( $method->getVariants() ) ) {
			return [];
		}

		return [
			$this->transport->build_error(
				sprintf(
					'Method %s::%s() takes or returns a WP_Error.',
					$method->getDeclaringClass()->getName(),
					$method->getName()
				)
			),
		];
	}
}
