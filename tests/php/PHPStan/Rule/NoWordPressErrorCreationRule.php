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
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * No WP_Error is created inside the plugin.
 *
 * Part of architecture.noWordPressErrorMechanisms, see WordPressErrorTransport.
 *
 * @implements Rule<New_>
 */
final class NoWordPressErrorCreationRule implements Rule {

	/**
	 * What the WP_Error rules share.
	 */
	private WordPressErrorTransport $transport;

	/**
	 * Instantiate a NoWordPressErrorCreationRule object.
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
		return New_::class;
	}

	/**
	 * Report creating a WP_Error.
	 *
	 * @param New_ $node  Node to inspect.
	 * @param Scope $scope Scope of the node.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $this->transport->applies_to( $scope ) ) {
			return [];
		}

		// Only the class being created counts, not the classes a generic type
		// mentions, as in `new ReflectionClass( WP_Error::class )`.
		foreach ( $scope->getType( $node )->getObjectClassReflections() as $created ) {
			if ( $this->transport->is_wp_error( $created ) ) {
				return [ $this->transport->build_error( 'new WP_Error() carries an error through the plugin.' ) ];
			}
		}

		return [];
	}
}
