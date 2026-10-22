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
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * The plugin's exceptions are created through their named constructors.
 *
 * A named constructor states the cause and builds the message, so the same
 * failure always reads the same. Only an exception's own static methods
 * create it with `new`.
 *
 * @implements Rule<New_>
 */
final class ExceptionsThroughNamedConstructorsRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Marker interface every exception of the plugin implements.
	 */
	private string $marker;

	/**
	 * Instantiate an ExceptionsThroughNamedConstructorsRule object.
	 *
	 * @param PluginNamespace $plugin_namespace Code the rule applies to.
	 * @param string          $marker           Marker interface of the
	 *                                          plugin's exceptions.
	 */
	public function __construct( PluginNamespace $plugin_namespace, string $marker ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->marker           = trim( $marker, '\\' );
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
	 * Report an exception of the plugin created outside its named constructors.
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
			if ( ! $created->implementsInterface( $this->marker )
				|| $this->is_inside_named_constructor( $created, $scope ) ) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'new %1$s creates an exception outside its named constructors. Use %2$s instead, or add a'
					. ' named constructor to %1$s that states the cause.',
					$created->getName(),
					$this->describe_named_constructors( $created )
				)
			)
				->identifier( RuleManifest::EXCEPTIONS_THROUGH_NAMED_CONSTRUCTORS )
				->tip( RuleManifest::get_explanation( RuleManifest::EXCEPTIONS_THROUGH_NAMED_CONSTRUCTORS ) )
				->build();
		}

		return $errors;
	}

	/**
	 * Check whether the code runs in a static method of the created exception.
	 *
	 * @param ClassReflection $created Exception being created.
	 * @param Scope           $scope   Scope of the object creation.
	 * @return bool Whether the exception is created by its own static method.
	 */
	private function is_inside_named_constructor( ClassReflection $created, Scope $scope ): bool {
		$class    = $scope->getClassReflection();
		$function = $scope->getFunction();

		return $class !== null
			&& $class->getName() === $created->getName()
			&& $function !== null
			&& $class->hasNativeMethod( $function->getName() )
			&& $class->getNativeMethod( $function->getName() )->isStatic();
	}

	/**
	 * Describe the named constructors of an exception for a message.
	 *
	 * @param ClassReflection $created Exception being created.
	 * @return string Named constructors, such as `Foo::from_bar()`.
	 */
	private function describe_named_constructors( ClassReflection $created ): string {
		$names = array_map(
			static fn( string $method ): string => sprintf( '%s::%s()', $created->getName(), $method ),
			NamedConstructors::of( $created )
		);

		return $names === [] ? 'a named constructor' : implode( ' or ', $names );
	}
}
