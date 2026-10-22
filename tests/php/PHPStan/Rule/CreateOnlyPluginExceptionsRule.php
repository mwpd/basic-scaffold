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
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Throwable;

/**
 * The plugin only creates its own exceptions.
 *
 * Together with ThrowOnlyPluginExceptionsRule, under the same identifier,
 * this makes sure everything the plugin throws implements its marker
 * interface: an exception the plugin throws is either one it created, which
 * this rule checks, or one a catch block caught and passes on.
 *
 * @implements Rule<New_>
 */
final class CreateOnlyPluginExceptionsRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Marker interface every exception of the plugin implements.
	 */
	private string $marker;

	/**
	 * Namespace the plugin's exceptions live in.
	 */
	private string $exception_namespace;

	/**
	 * Instantiate a CreateOnlyPluginExceptionsRule object.
	 *
	 * @param PluginNamespace $plugin_namespace    Code the rule applies to.
	 * @param string          $marker              Marker interface of the
	 *                                             plugin's exceptions.
	 * @param string          $exception_namespace Namespace the plugin's
	 *                                             exceptions live in.
	 */
	public function __construct( PluginNamespace $plugin_namespace, string $marker, string $exception_namespace ) {
		$this->plugin_namespace    = $plugin_namespace;
		$this->marker              = trim( $marker, '\\' );
		$this->exception_namespace = trim( $exception_namespace, '\\' );
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
	 * Report creating an exception that is not one of the plugin's.
	 *
	 * @param New_  $node  Object creation to inspect.
	 * @param Scope $scope Scope of the object creation.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		$errors = [];
		foreach ( $scope->getType( $node )->getObjectClassReflections() as $created ) {
			// Throwable itself does not implement Throwable, but a class-string
			// typed as Throwable creates one all the same.
			$is_throwable = strtolower( $created->getName() ) === 'throwable'
				|| $created->implementsInterface( Throwable::class );

			if ( ! $is_throwable || $created->implementsInterface( $this->marker ) ) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'new %1$s creates an exception that does not implement %2$s. Add an exception to %3$s that'
					. ' extends the matching SPL exception, implements %2$s and has a named constructor, and'
					. ' create that instead.',
					$created->getName(),
					$this->marker,
					$this->exception_namespace
				)
			)
				->identifier( RuleManifest::THROW_ONLY_PLUGIN_EXCEPTIONS )
				->tip( RuleManifest::get_explanation( RuleManifest::THROW_ONLY_PLUGIN_EXCEPTIONS ) )
				->build();
		}

		return $errors;
	}
}
