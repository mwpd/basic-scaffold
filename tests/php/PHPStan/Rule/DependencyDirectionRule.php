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
use PHPStan\Node\FileNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Dependencies point from the outside in.
 *
 * The plugin's code falls into layers, and each may only depend on some of
 * the others:
 *
 * - The exceptions depend on nothing but each other.
 * - The infrastructure depends on itself and the exceptions. Generated
 *   plugins build on it, so it never knows about their features.
 * - A feature subsystem depends on the infrastructure's interfaces and on
 *   the exceptions, never on a concrete infrastructure class, so the
 *   implementation can be swapped in the composition root. It may use an
 *   infrastructure trait only if the trait supplies nothing but methods of
 *   infrastructure interfaces the using class implements, such as
 *   DefaultRegistrationPriority for Delayed: a trait is not an object that
 *   could be bound instead.
 * - Only the composition root, an explicit list of classes, knows the
 *   concrete classes and wires everything together.
 * - Nothing depends on the tests.
 *
 * - A view template may depend on anything but the tests.
 *
 * Classes outside the plugin, from PHP or WordPress, are always fine.
 *
 * The rule reads whole files, so it sees every class a file mentions: in its
 * imports, its code, its native types and its documentation. Each mention
 * counts for the class, interface or trait it appears in. Imports and code
 * outside any class count for the file's class, or, in a file without one,
 * for the file's namespace or view template directory.
 *
 * @implements Rule<FileNode>
 */
final class DependencyDirectionRule implements Rule {

	private DependencyContext $context;

	private ReflectionProvider $reflection;

	/**
	 * @param DependencyContext  $context    Shared positions and layers.
	 * @param ReflectionProvider $reflection Reflection, to check interfaces.
	 */
	public function __construct( DependencyContext $context, ReflectionProvider $reflection ) {
		$this->context    = $context;
		$this->reflection = $reflection;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return FileNode::class;
	}

	/**
	 * Report every class a file mentions against the direction of the layers.
	 *
	 * @param FileNode $node  File node to inspect.
	 * @param Scope    $scope Scope of the file.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$mentions = $this->context->get_mentions( $node, $scope );
		$file     = $scope->getFile();

		$errors = [];
		foreach ( $mentions as [ $name, $dependency, $line ] ) {
			$from = $this->context->get_source_layer( $name, $file );
			$to   = $this->context->get_layer( $dependency );

			if ( $from === null
				|| $to === null
				|| PluginNamespace::normalize( $name ) === PluginNamespace::normalize( $dependency )
				|| $this->is_allowed( $from, $name, $to, $dependency ) ) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message( $this->describe( $from, $name, $to, $dependency ) )
				->identifier( RuleManifest::DEPENDENCY_DIRECTION )
				->tip( RuleManifest::get_explanation( RuleManifest::DEPENDENCY_DIRECTION ) )
				->line( $line )
				->build();
		}

		return $errors;
	}

	/**
	 * Check whether one layer may depend on another.
	 *
	 * @param string $from       Layer of the class.
	 * @param string $name       The class.
	 * @param string $to         Layer of the dependency.
	 * @param string $dependency The dependency.
	 * @return bool Whether the dependency is allowed.
	 */
	private function is_allowed( string $from, string $name, string $to, string $dependency ): bool {
		if ( $to === DependencyContext::TESTS ) {
			return false;
		}

		if ( $from === DependencyContext::ROOT || $from === DependencyContext::VIEW ) {
			return true;
		}

		if ( $from === DependencyContext::EXCEPTIONS ) {
			return $to === DependencyContext::EXCEPTIONS;
		}

		if ( $from === DependencyContext::INFRASTRUCTURE ) {
			return $to === DependencyContext::INFRASTRUCTURE || $to === DependencyContext::EXCEPTIONS;
		}

		if ( $to === DependencyContext::EXCEPTIONS ) {
			return true;
		}

		if ( $to === DependencyContext::INFRASTRUCTURE ) {
			if ( ! $this->reflection->hasClass( $dependency ) ) {
				return false;
			}

			$type = $this->reflection->getClass( $dependency );

			return $type->isInterface() || ( $type->isTrait() && $this->supplies_contract( $name, $type ) );
		}

		// A subsystem may use its own classes and those of other subsystems,
		// a top-level class only itself.
		return $from === DependencyContext::TOP_LEVEL
			? PluginNamespace::normalize( $name ) === PluginNamespace::normalize( $dependency )
			: $to !== DependencyContext::ROOT && $to !== DependencyContext::TOP_LEVEL;
	}

	/**
	 * Describe a dependency against the direction of the layers.
	 *
	 * @param string $from       Layer of the class.
	 * @param string $name       The class.
	 * @param string $to         Layer of the dependency.
	 * @param string $dependency The dependency.
	 * @return string What is wrong, and what to do instead.
	 */
	private function describe( string $from, string $name, string $to, string $dependency ): string {
		if ( $from === DependencyContext::VIEW ) {
			$name = basename( dirname( $name ) ) . '/' . basename( $name );
		}

		$problem = sprintf( '%1$s, in %2$s, depends on %3$s, in %4$s.', $name, $from, $dependency, $to );

		if ( $to === DependencyContext::TESTS ) {
			return $problem . ' Production code never depends on the tests; move what it needs into src/.';
		}

		if ( $from === DependencyContext::EXCEPTIONS ) {
			return $problem . ' Exceptions only depend on each other and on PHP; pass what the message needs as'
				. ' a value to the named constructor.';
		}

		if ( $from === DependencyContext::INFRASTRUCTURE ) {
			return $problem . ' Generated plugins build on the infrastructure, so it may only depend on itself'
				. ' and the exceptions; add an interface to the infrastructure that the other class implements.';
		}

		if ( $to === DependencyContext::INFRASTRUCTURE && $this->is_trait( $dependency ) ) {
			return $problem . ' A subsystem only uses a trait of the infrastructure that supplies methods of the'
				. ' infrastructure interfaces its class implements; implement the interface the trait is for, or'
				. ' keep the code in the subsystem.';
		}

		if ( $to === DependencyContext::INFRASTRUCTURE ) {
			return $problem . sprintf(
				' Depend on %s instead, and bind the implementation in the composition root.',
				$this->describe_interfaces( $dependency )
			);
		}

		return $problem . ' Only the composition root (parameters.architecture.dependencyDirection.compositionRoot)'
			. ' knows how the plugin is put together; receive what you need through the constructor.';
	}

	/**
	 * Check whether a trait supplies only methods of the infrastructure
	 * interfaces that the class using it implements.
	 *
	 * @param string          $name  The class using the trait.
	 * @param ClassReflection $used  The trait.
	 * @return bool Whether the trait only fulfils that contract.
	 */
	private function supplies_contract( string $name, ClassReflection $used ): bool {
		if ( ! $this->reflection->hasClass( $name ) ) {
			return false;
		}

		$contract = [];
		foreach ( $this->reflection->getClass( $name )->getInterfaces() as $interface ) {
			if ( $this->context->get_layer( $interface->getName() ) !== DependencyContext::INFRASTRUCTURE ) {
				continue;
			}

			foreach ( $interface->getNativeReflection()->getMethods() as $method ) {
				$contract[ strtolower( $method->getName() ) ] = true;
			}
		}

		$methods = $used->getNativeReflection()->getMethods();
		foreach ( $methods as $method ) {
			if ( ! isset( $contract[ strtolower( $method->getName() ) ] ) ) {
				return false;
			}
		}

		return $methods !== [];
	}

	/**
	 * Check whether a dependency is a trait.
	 *
	 * @param string $dependency The dependency.
	 * @return bool Whether it is a trait.
	 */
	private function is_trait( string $dependency ): bool {
		return $this->reflection->hasClass( $dependency ) && $this->reflection->getClass( $dependency )->isTrait();
	}

	/**
	 * Describe the infrastructure interfaces a concrete class implements.
	 *
	 * @param string $dependency Concrete infrastructure class.
	 * @return string Interfaces, or a general description.
	 */
	private function describe_interfaces( string $dependency ): string {
		if ( ! $this->reflection->hasClass( $dependency ) ) {
			return 'an interface of the infrastructure';
		}

		$interfaces = [];
		foreach ( $this->reflection->getClass( $dependency )->getInterfaces() as $interface ) {
			// A marker interface such as Service names a concept, not what the
			// class can do for its caller.
			if ( $this->context->get_layer( $interface->getName() ) === DependencyContext::INFRASTRUCTURE
				&& $interface->getNativeReflection()->getMethods() !== [] ) {
				$interfaces[] = $interface->getName();
			}
		}

		return $interfaces === []
			? 'an interface of the infrastructure'
			: 'the interface it implements (' . implode( ' or ', $interfaces ) . ')';
	}
}
