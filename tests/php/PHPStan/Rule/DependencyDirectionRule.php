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
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
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
 *   implementation can be swapped in the composition root.
 * - Only the composition root, an explicit list of classes, knows the
 *   concrete classes and wires everything together.
 * - Nothing depends on the tests.
 *
 * Classes outside the plugin, from PHP or WordPress, are always fine.
 *
 * The rule reads every class a class mentions: in its code, in its native
 * types and in its documented types.
 *
 * @implements Rule<InClassNode>
 */
final class DependencyDirectionRule implements Rule {

	private const EXCEPTIONS     = 'the exceptions';
	private const INFRASTRUCTURE = 'the infrastructure';
	private const ROOT           = 'the composition root';
	private const TESTS          = 'the tests';
	private const TOP_LEVEL      = 'a top-level class';

	/**
	 * Root namespace of the plugin, normalized, with a trailing separator.
	 */
	private string $root;

	/**
	 * Namespaces of the tests, normalized, with a trailing separator.
	 *
	 * @var array<string>
	 */
	private array $tests;

	/**
	 * Namespace of the exceptions, normalized, with a trailing separator.
	 */
	private string $exceptions;

	/**
	 * Namespace of the infrastructure, normalized, with a trailing separator.
	 */
	private string $infrastructure;

	/**
	 * Classes of the composition root, normalized.
	 *
	 * @var array<string>
	 */
	private array $composition_root;

	/**
	 * Reflection, to tell interfaces from classes.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a DependencyDirectionRule object.
	 *
	 * @param string             $root_namespace   Root namespace of the plugin.
	 * @param array<string>      $test_namespaces  Namespaces of the tests.
	 * @param string             $exceptions       Namespace of the exceptions.
	 * @param string             $infrastructure   Namespace of the
	 *                                             infrastructure.
	 * @param array<string>      $composition_root Classes of the composition
	 *                                             root.
	 * @param ReflectionProvider $reflection       Reflection, to tell
	 *                                             interfaces from classes.
	 */
	public function __construct(
		string $root_namespace,
		array $test_namespaces,
		string $exceptions,
		string $infrastructure,
		array $composition_root,
		ReflectionProvider $reflection
	) {
		$this->root             = PluginNamespace::normalize( $root_namespace ) . '\\';
		$this->tests            = array_map(
			static fn( string $name ): string => PluginNamespace::normalize( $name ) . '\\',
			$test_namespaces
		);
		$this->exceptions       = PluginNamespace::normalize( $exceptions ) . '\\';
		$this->infrastructure   = PluginNamespace::normalize( $infrastructure ) . '\\';
		$this->composition_root = array_map( [ PluginNamespace::class, 'normalize' ], $composition_root );
		$this->reflection       = $reflection;
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
	 * Report every class a class depends on against the direction of the layers.
	 *
	 * @param InClassNode $node  Class node to inspect.
	 * @param Scope       $scope Scope of the class.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$class = $node->getClassReflection();
		$name  = $class->isAnonymous()
			? ( (string) $scope->getNamespace() ) . '\\class@anonymous'
			: $class->getName();
		$from  = $this->get_layer( $name );

		if ( $from === null || $from === self::ROOT || $from === self::TESTS ) {
			return [];
		}

		$errors = [];
		foreach ( $this->get_dependencies( $node, $class ) as $dependency => $line ) {
			$to = $this->get_layer( $dependency );

			if ( $to === null || $this->is_allowed( $from, $name, $to, $dependency ) ) {
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
	 * Get the layer of a class, interface or trait.
	 *
	 * @param string $name Fully qualified name.
	 * @return string|null Layer, or null for code outside the plugin.
	 */
	private function get_layer( string $name ): ?string {
		$normalized = PluginNamespace::normalize( $name );

		if ( strpos( $normalized, $this->root ) !== 0 ) {
			return null;
		}

		foreach ( $this->tests as $tests ) {
			if ( strpos( $normalized, $tests ) === 0 ) {
				return self::TESTS;
			}
		}

		if ( in_array( $normalized, $this->composition_root, true ) ) {
			return self::ROOT;
		}

		if ( strpos( $normalized, $this->exceptions ) === 0 ) {
			return self::EXCEPTIONS;
		}

		if ( strpos( $normalized, $this->infrastructure ) === 0 ) {
			return self::INFRASTRUCTURE;
		}

		$relative = substr( $normalized, strlen( $this->root ) );
		$position = strpos( $relative, '\\' );

		// A feature subsystem is named after its top-level namespace.
		return $position === false
			? self::TOP_LEVEL
			: 'the ' . substr( $name, strlen( $this->root ), $position ) . ' subsystem';
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
		if ( $to === self::TESTS ) {
			return false;
		}

		if ( $from === self::EXCEPTIONS ) {
			return $to === self::EXCEPTIONS;
		}

		if ( $from === self::INFRASTRUCTURE ) {
			return $to === self::INFRASTRUCTURE || $to === self::EXCEPTIONS;
		}

		if ( $to === self::EXCEPTIONS ) {
			return true;
		}

		if ( $to === self::INFRASTRUCTURE ) {
			return $this->reflection->hasClass( $dependency )
				&& $this->reflection->getClass( $dependency )->isInterface();
		}

		// A subsystem may use its own classes and those of other subsystems,
		// a top-level class only itself.
		return $from === self::TOP_LEVEL
			? PluginNamespace::normalize( $name ) === PluginNamespace::normalize( $dependency )
			: $to !== self::ROOT && $to !== self::TOP_LEVEL;
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
		$problem = sprintf( '%1$s, in %2$s, depends on %3$s, in %4$s.', $name, $from, $dependency, $to );

		if ( $to === self::TESTS ) {
			return $problem . ' Production code never depends on the tests; move what it needs into src/.';
		}

		if ( $from === self::EXCEPTIONS ) {
			return $problem . ' Exceptions only depend on each other and on PHP; pass what the message needs as'
				. ' a value to the named constructor.';
		}

		if ( $from === self::INFRASTRUCTURE ) {
			return $problem . ' Generated plugins build on the infrastructure, so it may only depend on itself'
				. ' and the exceptions; add an interface to the infrastructure that the other class implements.';
		}

		if ( $to === self::INFRASTRUCTURE ) {
			return $problem . sprintf(
				' Depend on %s instead, and bind the implementation in the composition root.',
				$this->describe_interfaces( $dependency )
			);
		}

		return $problem . ' Only the composition root (parameters.architecture.dependencyDirection.compositionRoot)'
			. ' knows how the plugin is put together; receive what you need through the constructor.';
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
			if ( $this->get_layer( $interface->getName() ) === self::INFRASTRUCTURE
				&& $interface->getNativeReflection()->getMethods() !== [] ) {
				$interfaces[] = $interface->getName();
			}
		}

		return $interfaces === []
			? 'an interface of the infrastructure'
			: 'the interface it implements (' . implode( ' or ', $interfaces ) . ')';
	}

	/**
	 * Get every class a class mentions.
	 *
	 * @param InClassNode     $node       Class node.
	 * @param ClassReflection $reflection Reflection of the class.
	 * @return array<string, int> Line of the first mention, keyed by name.
	 */
	private function get_dependencies( InClassNode $node, ClassReflection $reflection ): array {
		$dependencies = [];
		$original     = $node->getOriginalNode();

		foreach ( self::find_class_names( $original, true ) as $mention ) {
			$dependencies[ $mention->toString() ] = $dependencies[ $mention->toString() ] ?? $mention->getStartLine();
		}

		// Documented types only show up in reflection.
		foreach ( $original->stmts as $statement ) {
			$types = [];

			if ( $statement instanceof ClassMethod && $reflection->hasNativeMethod( $statement->name->toString() ) ) {
				foreach ( $reflection->getNativeMethod( $statement->name->toString() )->getVariants() as $variant ) {
					$types[] = $variant->getReturnType();
					foreach ( $variant->getParameters() as $parameter ) {
						$types[] = $parameter->getType();
					}
				}
			}

			if ( $statement instanceof Property ) {
				foreach ( $statement->props as $property ) {
					if ( $reflection->hasNativeProperty( $property->name->toString() ) ) {
						$types[] = $reflection->getNativeProperty( $property->name->toString() )->getReadableType();
					}
				}
			}

			foreach ( $types as $type ) {
				foreach ( $type->getReferencedClasses() as $referenced ) {
					$dependencies[ $referenced ] = $dependencies[ $referenced ] ?? $statement->getStartLine();
				}
			}
		}

		unset( $dependencies[ $reflection->getName() ] );
		asort( $dependencies );

		return $dependencies;
	}

	/**
	 * Find the names of classes a node mentions.
	 *
	 * A nested class, such as an anonymous one, is a class of its own that the
	 * rule inspects separately, so its names are left out. Names of functions
	 * and constants are not classes.
	 *
	 * @param Node $node     Node to search.
	 * @param bool $is_start Whether the node is the class being inspected.
	 * @return array<Name> Names of classes.
	 */
	private static function find_class_names( Node $node, bool $is_start ): array {
		if ( ! $is_start && $node instanceof ClassLike ) {
			return [];
		}

		if ( $node instanceof Name ) {
			return $node->isSpecialClassName() ? [] : [ $node ];
		}

		$names = [];
		foreach ( $node->getSubNodeNames() as $sub_node_name ) {
			if ( ( $node instanceof FuncCall || $node instanceof ConstFetch ) && $sub_node_name === 'name' ) {
				continue;
			}

			$children = $node->$sub_node_name;
			foreach ( is_array( $children ) ? $children : [ $children ] as $child ) {
				if ( $child instanceof Node ) {
					$names = array_merge( $names, self::find_class_names( $child, false ) );
				}
			}
		}

		return $names;
	}
}
