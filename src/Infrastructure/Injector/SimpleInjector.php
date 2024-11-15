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

namespace MWPD\BasicScaffold\Infrastructure\Injector;

use MWPD\BasicScaffold\Exception\FailedToMakeInstance;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Instantiator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use Throwable;

/**
 * A simplified implementation of a dependency injector.
 */
final class SimpleInjector implements Injector {

	/**
	 * Special-case index key for handling globally defined named arguments.
	 *
	 * This is typed as a class-string to ensure that it fits the type requirements.
	 *
	 * @var string
	 */
	public const GLOBAL_ARGUMENTS = '__global__';

	/**
	 * Mapping of interfaces to classes.
	 *
	 * @var array<class-string,class-string>
	 */
	private array $mappings = [];

	/**
	 * Mapping of shared instances.
	 *
	 * @var array<object|null>
	 */
	private array $shared_instances = [];

	/**
	 * Mapping of delegates.
	 *
	 * @var array<callable>
	 */
	private array $delegates = [];

	/**
	 * Mapping of argument names to values.
	 *
	 * @var array<array<string, mixed>>
	 */
	private array $argument_mappings = [
		self::GLOBAL_ARGUMENTS => [],
	];

	/**
	 * Instantiator to use.
	 */
	private Instantiator $instantiator;

	/**
	 * Instantiate a SimpleInjector object.
	 *
	 * @param Instantiator|null $instantiator Optional. Instantiator to use.
	 */
	public function __construct( ?Instantiator $instantiator = null ) {
		$this->instantiator = $instantiator ?? $this->get_fallback_instantiator();
	}

	/**
	 * Make an object instance out of an interface or class.
	 *
	 * @param class-string         $interface_or_class Interface or class to make an object
	 *                                                 instance out of.
	 * @param array<string, mixed> $arguments          Optional. Additional arguments
	 *                                                 to pass to the constructor.
	 *                                                 Defaults to an empty array.
	 * @return object Instantiated object.
	 * @throws FailedToMakeInstance If the object could not be instantiated.
	 */
	public function make( string $interface_or_class, array $arguments = [] ): object {
		$injection_chain = $this->resolve(
			new InjectionChain(),
			$interface_or_class
		);

		$class_name = $injection_chain->get_class();

		if ( $this->has_shared_instance( $class_name ) ) {
			return $this->get_shared_instance( $class_name );
		}

		if ( $this->has_delegate( $class_name ) ) {
			$delegate = $this->get_delegate( $class_name );
			$object   = $delegate( $class_name );

			if ( ! is_object( $object ) ) {
				throw FailedToMakeInstance::for_invalid_delegate( $class_name );
			}

			return $object;
		}

		$reflection = $this->get_class_reflection( $class_name );
		$this->ensure_is_instantiable( $reflection );
		$dependencies = $this->get_dependencies_for(
			$injection_chain,
			$reflection,
			$arguments
		);
		$object       = $this->instantiator->instantiate( $class_name, $dependencies );

		if ( \array_key_exists( $class_name, $this->shared_instances ) ) {
			$this->shared_instances[ $class_name ] = $object;
		}

		return $object;
	}

	/**
	 * Bind a given interface or class to an implementation.
	 *
	 * Note: The implementation can be an interface as well, as long as it can
	 * be resolved to an instantiatable class at runtime.
	 *
	 * @param class-string $from Interface or class to bind an implementation to.
	 * @param class-string $to   Interface or class that provides the implementation.
	 *
	 * @return self
	 */
	public function bind( string $from, string $to ): Injector {
		$this->mappings[ $from ] = $to;

		return $this;
	}

	/**
	 * Bind an argument for a class to a specific value.
	 *
	 * @param class-string $interface_or_class Interface or class to bind an argument
	 *                                   for.
	 * @param string       $argument_name      Argument name to bind a value to.
	 * @param mixed        $value              Value to bind the argument to.
	 *
	 * @return self
	 */
	public function bind_argument(
		string $interface_or_class,
		string $argument_name,
		$value
	): Injector {
		$this->argument_mappings[ $interface_or_class ][ $argument_name ] = $value;

		return $this;
	}

	/**
	 * Always reuse and share the same instance for the provided interface or
	 * class.
	 *
	 * @param class-string $interface_or_class Interface or class to reuse.
	 *
	 * @return self
	 */
	public function share( string $interface_or_class ): Injector {
		$this->shared_instances[ $interface_or_class ] = null;

		return $this;
	}

	/**
	 * Delegate instantiation of an interface or class to a callable.
	 *
	 * @param class-string $interface_or_class Interface or class to delegate the
	 *                                   instantiation of.
	 * @param callable     $delegation         Callable to use for instantiation.
	 *
	 * @return self
	 */
	public function delegate( string $interface_or_class, callable $delegation ): Injector {
		$this->delegates[ $interface_or_class ] = $delegation;

		return $this;
	}

	/**
	 * Make an object instance out of an interface or class.
	 *
	 * @param InjectionChain $injection_chain    Injection chain to track
	 *                                           resolutions.
	 * @param class-string   $interface_or_class Interface or class to make an
	 *                                           object instance out of.
	 * @return object Instantiated object.
	 * @throws FailedToMakeInstance If the object could not be instantiated.
	 */
	private function make_dependency(
		InjectionChain $injection_chain,
		string $interface_or_class
	): object {
		$injection_chain = $this->resolve(
			$injection_chain,
			$interface_or_class
		);

		$class_name = $injection_chain->get_class();

		if ( $this->has_shared_instance( $class_name ) ) {
			return $this->get_shared_instance( $class_name );
		}

		if ( $this->has_delegate( $class_name ) ) {
			$delegate = $this->get_delegate( $class_name );
			$object   = $delegate( $class_name );

			if ( ! is_object( $object ) ) {
				throw FailedToMakeInstance::for_invalid_delegate( $class_name );
			}

			return $object;
		}

		$reflection = $this->get_class_reflection( $class_name );
		$this->ensure_is_instantiable( $reflection );

		$dependencies = $this->get_dependencies_for(
			$injection_chain,
			$reflection
		);

		$object = $this->instantiator->instantiate( $class_name, $dependencies );

		if ( \array_key_exists( $class_name, $this->shared_instances ) ) {
			$this->shared_instances[ $class_name ] = $object;
		}

		return $object;
	}

	/**
	 * Recursively resolve an interface to the class it should be bound to.
	 *
	 * @param InjectionChain $injection_chain    Injection chain to track
	 *                                           resolutions.
	 * @param class-string   $interface_or_class Interface or class to resolve.
	 * @return InjectionChain Modified Injection chain.
	 * @throws FailedToMakeInstance If a circular reference is detected.
	 */
	private function resolve(
		InjectionChain $injection_chain,
		string $interface_or_class
	): InjectionChain {
		if ( $injection_chain->has_resolution( $interface_or_class ) ) {
			// Circular reference detected, aborting.
			throw FailedToMakeInstance::for_circular_reference(
				$interface_or_class
			);
		}

		$injection_chain = $injection_chain->add_resolution( $interface_or_class );

		if ( \array_key_exists( $interface_or_class, $this->mappings ) ) {
			return $this->resolve(
				$injection_chain,
				$this->mappings[ $interface_or_class ]
			);
		}

		return $injection_chain->add_to_chain( $interface_or_class );
	}

	/**
	 * Get the array of constructor dependencies for a given reflected class.
	 *
	 * @param InjectionChain       $injection_chain Injection chain to track
	 *                                              resolutions.
	 * @param ReflectionClass      $reflection      Reflected class to get the
	 *                                              dependencies for.
	 * @param array<string, mixed> $arguments       Associative array of directly
	 *                                              provided arguments.
	 * @return array<string, mixed> Array of dependencies that represent the arguments for the class' constructor.
	 */
	private function get_dependencies_for(
		InjectionChain $injection_chain,
		ReflectionClass $reflection,
		array $arguments = []
	): array {
		$constructor = $reflection->getConstructor();
		$class       = $reflection->getName();

		if ( null === $constructor ) {
			return [];
		}

		/**
		 * The keys will be preserved in the returned array.
		 *
		 * @var array<string, mixed>
		 */
		return \array_map(
			/**
			 * Mixed return can only be provided directly from PHP 8.0 onwards.
			 *
			 * @return mixed
			 */
			fn( ReflectionParameter $parameter ) => $this->resolve_argument(
				$injection_chain,
				$class,
				$parameter,
				$arguments
			),
			$constructor->getParameters()
		);
	}

	/**
	 * Ensure that a given reflected class is instantiable.
	 *
	 * @param ReflectionClass $reflection Reflected class to check.
	 * @return void
	 * @throws FailedToMakeInstance If the interface could not be resolved.
	 */
	private function ensure_is_instantiable( ReflectionClass $reflection ): void {
		if ( ! $reflection->isInstantiable() ) {
			throw FailedToMakeInstance::for_unresolved_interface( $reflection->getName() );
		}
	}

	/**
	 * Resolve a given reflected argument.
	 *
	 * @param InjectionChain       $injection_chain Injection chain to track
	 *                                              resolutions.
	 * @param class-string         $class_name      Name of the class to
	 *                                              resolve the arguments for.
	 * @param ReflectionParameter  $parameter       Parameter to resolve.
	 * @param array<string, mixed> $arguments       Associative array of
	 *                                              directly provided
	 *                                              arguments.
	 * @return mixed Resolved value of the argument.
	 */
	private function resolve_argument(
		InjectionChain $injection_chain,
		string $class_name,
		ReflectionParameter $parameter,
		array $arguments
	) {
		if ( ! $parameter->hasType() ) {
			return $this->resolve_argument_by_name(
				$class_name,
				$parameter,
				$arguments
			);
		}

		/**
		 * Type can vary based on PHP version.
		 *
		 * @var ReflectionType|ReflectionNamedType|null $type
		 */
		$type = $parameter->getType();

		/*
		 * @psalm-suppress UndefinedMethod,TypeDoesNotContainNull
		 * @phpstan-ignore method.notFound (Method was moved to ReflectionNamedType in PHP 8.0)
		 */
		if ( null === $type || $type->isBuiltin() ) {
			return $this->resolve_argument_by_name(
				$class_name,
				$parameter,
				$arguments
			);
		}

		/**
		 * We need to deal with differences between PHP versions here.
		 *
		 * @var class-string $type
		 *
		 * @disregard P1009 as this is a different type in PHP 8.
		 */
		$type = $type instanceof ReflectionNamedType
			? $type->getName()
			: (string) $type;

		return $this->make_dependency( $injection_chain, $type );
	}

	/**
	 * Resolve a given reflected argument by its name.
	 *
	 * @param class-string         $class_name Class to resolve the argument for.
	 * @param ReflectionParameter  $parameter  Argument to resolve by name.
	 * @param array<string, mixed> $arguments  Associative array of directly
	 *                                         provided arguments.
	 * @return mixed Resolved value of the argument.
	 * @throws FailedToMakeInstance If the argument could not be resolved.
	 */
	private function resolve_argument_by_name(
		string $class_name,
		ReflectionParameter $parameter,
		array $arguments
	) {
		$name = $parameter->getName();

		// The argument was directly provided to the make() call.
		if ( \array_key_exists( $name, $arguments ) ) {
			return $arguments[ $name ];
		}

		// Check if we have mapped this argument for the specific class.
		if ( \array_key_exists( $class_name, $this->argument_mappings )
			&& \array_key_exists( $name, $this->argument_mappings[ $class_name ] ) ) {
			return $this->argument_mappings[ $class_name ][ $name ];
		}

		// No argument found for the class, check if we have a global value.
		if ( \array_key_exists( $name, $this->argument_mappings[ self::GLOBAL_ARGUMENTS ] ) ) {
			return $this->argument_mappings[ self::GLOBAL_ARGUMENTS ][ $name ];
		}

		// No provided argument found, check if it has a default value.
		try {
			if ( $parameter->isDefaultValueAvailable() ) {
				return $parameter->getDefaultValue();
			}
		} catch ( Throwable $throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			// Just fall through into the FailedToMakeInstance exception.
		}

		// Out of options, fail with an exception.
		throw FailedToMakeInstance::for_unresolved_argument( $name, $class_name );
	}

	/**
	 * Check whether a shared instance exists for a given class.
	 *
	 * @param class-string $class_name Class to check for a shared instance.
	 * @return bool Whether a shared instance exists.
	 */
	private function has_shared_instance( string $class_name ): bool {
		return \array_key_exists( $class_name, $this->shared_instances )
				&& null !== $this->shared_instances[ $class_name ];
	}

	/**
	 * Get the shared instance for a given class.
	 *
	 * @param class-string $class_name Class to get the shared instance for.
	 * @return object Shared instance.
	 * @throws FailedToMakeInstance If the shared instance could not be found.
	 */
	private function get_shared_instance( string $class_name ): object {
		if ( ! $this->has_shared_instance( $class_name ) ) {
			throw FailedToMakeInstance::for_uninstantiated_shared_instance( $class_name );
		}

		return (object) $this->shared_instances[ $class_name ];
	}

	/**
	 * Check whether a delegate exists for a given class.
	 *
	 * @param class-string $class_name Class to check for a delegate.
	 * @return bool Whether a delegate exists.
	 */
	private function has_delegate( string $class_name ): bool {
		return \array_key_exists( $class_name, $this->delegates );
	}

	/**
	 * Get the delegate for a given class.
	 *
	 * @param class-string $class_name Class to get the delegate for.
	 * @return callable Delegate.
	 * @throws FailedToMakeInstance If the delegate could not be found.
	 */
	private function get_delegate( string $class_name ): callable {
		if ( ! $this->has_delegate( $class_name ) ) {
			throw FailedToMakeInstance::for_invalid_delegate( $class_name );
		}

		return $this->delegates[ $class_name ];
	}

	/**
	 * Get the reflection for a class or throw an exception.
	 *
	 * @param class-string $class_name Class to get the reflection for.
	 * @return ReflectionClass Class reflection.
	 * @throws FailedToMakeInstance If the class could not be reflected.
	 * @phpstan-param class-string $class_name
	 */
	private function get_class_reflection( string $class_name ): ReflectionClass {
		try {
			$reflection = new ReflectionClass( $class_name );

			if ( $reflection->getName() !== $class_name ) {
				throw FailedToMakeInstance::for_invalid_reflection( $class_name );
			}

			return $reflection;
		} catch ( Throwable $throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			// Just fall through into the FailedToMakeInstance exception.
		}

		throw FailedToMakeInstance::for_unreflectable_class( $class_name );
	}

	/**
	 * Get a fallback instantiator in case none was provided.
	 *
	 * @return Instantiator Simplistic fallback instantiator.
	 */
	private function get_fallback_instantiator(): Instantiator {
		return new class() implements Instantiator {

			/**
			 * Make an object instance out of an interface or class.
			 *
			 * @param class-string         $class_name   Class to make an object instance out of.
			 * @param array<string, mixed> $dependencies Optional. Dependencies of the class.
			 * @return object Instantiated object.
			 */
			public function instantiate( string $class_name, array $dependencies = [] ): object {
				return new $class_name( ...$dependencies );
			}
		};
	}
}
