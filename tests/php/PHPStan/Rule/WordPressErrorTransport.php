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
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * What the rules against passing WP_Error through the plugin share.
 *
 * WordPress speaks in WP_Error objects, the plugin speaks in exceptions. The
 * translation happens at the boundary: a WP_Error a WordPress function
 * returns is checked with is_wp_error() and turned into an exception where
 * the call is made, and a WP_Error is only produced where WordPress expects
 * one, in a configured boundary adapter.
 *
 * Each place a WP_Error can show up (an object creation, a method or
 * function signature, a property) is a different kind of node, so each has
 * its own rule, and they share this.
 */
final class WordPressErrorTransport {

	private const WP_ERROR = 'wp_error';

	/**
	 * Code the rules apply to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Classes allowed to produce and pass on WP_Error objects, normalized.
	 *
	 * @var array<string>
	 */
	private array $boundary_adapters;

	/**
	 * Instantiate a WordPressErrorTransport object.
	 *
	 * @param PluginNamespace $plugin_namespace  Code the rules apply to.
	 * @param array<string>   $boundary_adapters Classes allowed to produce and
	 *                                           pass on WP_Error objects.
	 */
	public function __construct( PluginNamespace $plugin_namespace, array $boundary_adapters ) {
		$this->plugin_namespace  = $plugin_namespace;
		$this->boundary_adapters = array_map( [ PluginNamespace::class, 'normalize' ], $boundary_adapters );
	}

	/**
	 * Check whether the rules apply to code.
	 *
	 * They apply to the plugin's code, except its boundary adapters.
	 *
	 * @param Scope $scope Scope of the code.
	 * @return bool Whether the rules apply.
	 */
	public function applies_to( Scope $scope ): bool {
		if ( ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return false;
		}

		$class = $scope->getClassReflection();

		return $class === null
			|| ! in_array( PluginNamespace::normalize( $class->getName() ), $this->boundary_adapters, true );
	}

	/**
	 * Check whether a type mentions WP_Error, on its own or in a union.
	 *
	 * @param Type $type Type to check.
	 * @return bool Whether the type mentions WP_Error.
	 */
	public function mentions_wp_error( Type $type ): bool {
		foreach ( $type->getReferencedClasses() as $class_name ) {
			if ( PluginNamespace::normalize( $class_name ) === self::WP_ERROR ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether any variant of a signature takes or returns a WP_Error.
	 *
	 * @param array<ParametersAcceptor> $variants Variants of the signature.
	 * @return bool Whether the signature mentions WP_Error.
	 */
	public function signature_mentions_wp_error( array $variants ): bool {
		foreach ( $variants as $variant ) {
			if ( $this->mentions_wp_error( $variant->getReturnType() ) ) {
				return true;
			}

			foreach ( $variant->getParameters() as $parameter ) {
				if ( $this->mentions_wp_error( $parameter->getType() ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Build the error for one use of WP_Error.
	 *
	 * @param string $problem What is wrong, and where.
	 * @return IdentifierRuleError Error to report.
	 */
	public function build_error( string $problem ): IdentifierRuleError {
		return RuleErrorBuilder::message(
			$problem
			. ' Throw a dedicated exception instead, and turn a WP_Error that WordPress returns into one with'
			. ' is_wp_error() where the call is made. Only the classes in'
			. ' parameters.architecture.noWordPressErrorMechanisms.boundaryAdapters may produce a WP_Error.'
		)
			->identifier( RuleManifest::NO_WORDPRESS_ERROR_MECHANISMS )
			->tip( RuleManifest::get_explanation( RuleManifest::NO_WORDPRESS_ERROR_MECHANISMS ) )
			->build();
	}
}
