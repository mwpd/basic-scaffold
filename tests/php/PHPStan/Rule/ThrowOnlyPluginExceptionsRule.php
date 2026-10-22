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
use PhpParser\Node\Expr\Throw_ as ThrowExpression;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Throw_ as ThrowStatement;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;

/**
 * The plugin only throws its own exceptions.
 *
 * Every exception the plugin throws implements its marker interface, so
 * everything it throws can be caught in one place, and every failure has its
 * own class and named constructor instead of a generic exception with a
 * message.
 *
 * Throwing a variable is not checked here. A variable holds either an
 * exception a catch block caught, which may be passed on, or one the plugin
 * created, and CreateOnlyPluginExceptionsRule checks every exception the
 * plugin creates.
 *
 * PHP-Parser 4, which PHPStan 1 uses, parses a throw statement as a
 * statement, and PHP-Parser 5, which PHPStan 2 uses, as an expression, so
 * the rule listens to both.
 *
 * @implements Rule<Node>
 */
final class ThrowOnlyPluginExceptionsRule implements Rule {

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
	 * Instantiate a ThrowOnlyPluginExceptionsRule object.
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
		return Node::class;
	}

	/**
	 * Report a throw of anything that is not one of the plugin's exceptions.
	 *
	 * @param Node  $node  Node to inspect.
	 * @param Scope $scope Scope of the node.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! ( $node instanceof ThrowStatement || $node instanceof ThrowExpression )
			|| $node->expr instanceof Variable
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		$type = $scope->getType( $node->expr );

		if ( ( new ObjectType( $this->marker ) )->isSuperTypeOf( $type )->yes() ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'Throwing %1$s, which does not implement %2$s. Add an exception to %3$s that extends the'
					. ' matching SPL exception, implements %2$s and has a named constructor, and throw that.',
					$type->describe( VerbosityLevel::typeOnly() ),
					$this->marker,
					$this->exception_namespace
				)
			)
				->identifier( RuleManifest::THROW_ONLY_PLUGIN_EXCEPTIONS )
				->tip( RuleManifest::get_explanation( RuleManifest::THROW_ONLY_PLUGIN_EXCEPTIONS ) )
				->build(),
		];
	}
}
