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
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Static_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * No static state outside the plugin factory.
 *
 * Static properties and static variables outlive every object that uses them,
 * so they turn into hidden global state: the Singleton pattern in disguise.
 * Shared instances come from the injector instead. The one exception is the
 * plugin factory, which WordPress needs to reach from outside the object
 * graph.
 *
 * Static methods and class constants hold no state and are not reported.
 *
 * @implements Rule<Stmt>
 */
final class NoStaticStateRule implements Rule {

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Methods and properties allowed to hold static state, normalized.
	 *
	 * @var array<string>
	 */
	private array $allowed;

	/**
	 * Instantiate a NoStaticStateRule object.
	 *
	 * @param PluginNamespace $plugin_namespace Code the rule applies to.
	 * @param array<string>   $allowed          Methods (`Class::method`) and
	 *                                          properties (`Class::$property`)
	 *                                          allowed to hold static state.
	 */
	public function __construct( PluginNamespace $plugin_namespace, array $allowed ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->allowed          = array_map( [ PluginNamespace::class, 'normalize' ], $allowed );
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * Property declarations and static variables are both statements, so the
	 * rule listens to statements and picks out these two.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return Stmt::class;
	}

	/**
	 * Report static properties and static variables.
	 *
	 * @param Stmt  $node  Statement to inspect.
	 * @param Scope $scope Scope of the statement.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( $node instanceof Property && $node->isStatic() ) {
			return $this->check_static_property( $node, $scope );
		}

		if ( $node instanceof Static_ ) {
			return $this->check_static_variables( $node, $scope );
		}

		return [];
	}

	/**
	 * Report a static property declaration.
	 *
	 * @param Property $node  Property declaration.
	 * @param Scope    $scope Scope of the declaration.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	private function check_static_property( Property $node, Scope $scope ): array {
		$class = $scope->getClassReflection();

		if ( $class === null || ! $this->plugin_namespace->contains( $class->getName() ) ) {
			return [];
		}

		$errors = [];
		foreach ( $node->props as $property ) {
			$name = sprintf( '%s::$%s', $class->getName(), $property->name->toString() );

			if ( $this->is_allowed( $name ) ) {
				continue;
			}

			$errors[] = $this->build_error( sprintf( 'Static property %s keeps state outside any object.', $name ) );
		}

		return $errors;
	}

	/**
	 * Report static variables inside a function or method.
	 *
	 * @param Static_ $node  Static variable declaration.
	 * @param Scope   $scope Scope of the declaration.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	private function check_static_variables( Static_ $node, Scope $scope ): array {
		$function = $scope->getFunction();
		$class    = $scope->getClassReflection();

		if ( $function === null ) {
			return [];
		}

		$owner = $class === null
			? $function->getName()
			: sprintf( '%s::%s', $class->getName(), $function->getName() );

		if ( ! $this->plugin_namespace->contains( $owner ) || $this->is_allowed( $owner ) ) {
			return [];
		}

		$errors = [];
		foreach ( $node->vars as $variable ) {
			$errors[] = $this->build_error(
				sprintf(
					'Static variable $%s in %s() keeps state outside any object.',
					is_string( $variable->var->name ) ? $variable->var->name : '?',
					$owner
				)
			);
		}

		return $errors;
	}

	/**
	 * Check whether a method or property is allowed to hold static state.
	 *
	 * @param string $name Method (`Class::method`) or property (`Class::$property`).
	 * @return bool Whether it is allowed.
	 */
	private function is_allowed( string $name ): bool {
		return in_array( PluginNamespace::normalize( $name ), $this->allowed, true );
	}

	/**
	 * Build the error for one piece of static state.
	 *
	 * @param string $problem What is wrong, and where.
	 * @return IdentifierRuleError Error to report.
	 */
	private function build_error( string $problem ): IdentifierRuleError {
		return RuleErrorBuilder::message(
			$problem
			. ' Keep the value on an instance and share that instance through the injector with share();'
			. ' only the methods in parameters.architecture.noStaticState.allowed may hold static state.'
		)
			->identifier( RuleManifest::NO_STATIC_STATE )
			->tip( RuleManifest::get_explanation( RuleManifest::NO_STATIC_STATE ) )
			->build();
	}
}
