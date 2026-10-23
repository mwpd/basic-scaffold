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
		return $this->make_dependency(
			new InjectionChain(),
			$interface_or_class,
			$arguments
		);
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
	 * Both make() and the dependencies it resolves go through this one path,
	 * so sharing and delegation behave the same at the top of the object
	 * graph and anywhere inside it.
	 *
	 * @param InjectionChain       $injection_chain    Injection chain to track
	 *                                                 resolutions.
	 * @param class-string         $interface_or_class Interface or class to make an
	 *                                                 object instance out of.
	 * @param array<string, mixed> $arguments          Optional. Additional arguments
	 *                                                 to pass to the constructor.
	 *                                                 Defaults to an empty array.
	 * @return object Instantiated object.
	 * @throws FailedToMakeInstance If the object could not be instantiated.
	 */
	private function make_dependency(
		InjectionChain $injection_chain,
		string $interface_or_class,
		array $arguments = []
	): object {
		$injection_chain = $this->resolve(
			$injection_chain,
			$interface_or_class
		);

		$class_name      = $injection_chain->get_class();
		$resolution_path = $this->get_resolution_path( $interface_or_class );
		$shared_instance = $this->find_shared_instance( $resolution_path );

		if ( null !== $shared_instance ) {
			$this->store_shared_instance( $resolution_path, $shared_instance );

			return $shared_instance;
		}

		if ( $this->has_delegate( $class_name ) ) {
			$object = $this->make_with_delegate( $class_name );
		} else {
			$reflection = $this->get_class_reflection( $class_name );
			$this->ensure_is_instantiable( $reflection );

			$dependencies = $this->get_dependencies_for(
				$injection_chain,
				$reflection,
				$arguments
			);

			$object = $this->instantiator->instantiate( $class_name, $dependencies );
		}

		$this->store_shared_instance( $resolution_path, $object );

		return $object;
	}

	/**
	 * Get the names a requested interface or class resolves through.
	 *
	 * Sharing is configured by name, and the name that was shared can be any
	 * step between what was asked for and the class that gets built: an
	 * interface, an alias bound to another one, or the class itself.
	 * resolve() has already rejected cycles, so the walk ends.
	 *
	 * @param class-string $interface_or_class Interface or class that was requested.
	 * @return array<int, class-string> The requested name, every name it is
	 *                                  bound to in turn, and the class that is
	 *                                  built, in that order.
	 */
	private function get_resolution_path( string $interface_or_class ): array {
		$path = [ $interface_or_class ];

		while ( \array_key_exists( $interface_or_class, $this->mappings ) ) {
			$interface_or_class = $this->mappings[ $interface_or_class ];
			$path[]             = $interface_or_class;
		}

		return $path;
	}

	/**
	 * Find an instance already shared under any name on a resolution path.
	 *
	 * @param array<int, class-string> $resolution_path Names the request
	 *                                                  resolves through.
	 * @return object|null Shared instance, or null if none was made yet.
	 */
	private function find_shared_instance( array $resolution_path ): ?object {
		foreach ( $resolution_path as $name ) {
			if ( isset( $this->shared_instances[ $name ] ) ) {
				return $this->shared_instances[ $name ];
			}
		}

		return null;
	}

	/**
	 * Store an instance under every shared name on a resolution path.
	 *
	 * Names that were not shared are left alone, so sharing an interface does
	 * not also share its implementation when that is asked for directly.
	 *
	 * @param array<int, class-string> $resolution_path Names the request
	 *                                                  resolves through.
	 * @param object                   $instance        Instance to share.
	 */
	private function store_shared_instance( array $resolution_path, object $instance ): void {
		foreach ( $resolution_path as $name ) {
			if ( \array_key_exists( $name, $this->shared_instances ) ) {
				$this->shared_instances[ $name ] = $instance;
			}
		}
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
		// The chain only holds the ancestry of the current branch, so meeting
		// a resolution again here means a real cycle, whether through
		// constructors or through bindings.
		if ( $injection_chain->has_resolution( $interface_or_class ) ) {
			throw FailedToMakeInstance::for_circular_reference(
				$interface_or_class,
				$injection_chain->get_resolutions()
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
		$type = $parameter->getType();

		/*
		 * Only a single class or interface name can be autowired. Anything
		 * else is resolved by the parameter's name, through the arguments
		 * passed to make(), the bound arguments and the default value: no
		 * type, a builtin type, and a union or intersection type (PHP 8.0 and
		 * 8.1), which names several types and so no single one to build.
		 * Checking for ReflectionNamedType, the only type reflection PHP 7.4
		 * returns, keeps the newer reflection classes out of the code.
		 */
		if ( ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
			return $this->resolve_argument_by_name(
				$class_name,
				$parameter,
				$arguments
			);
		}

		/**
		 * A non-builtin named type is a class or interface name.
		 *
		 * @var class-string $dependency
		 */
		$dependency = $type->getName();

		return $this->make_dependency( $injection_chain, $dependency );
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
		} catch ( Throwable $throwable ) {
			throw FailedToMakeInstance::for_unresolved_argument( $name, $class_name, $throwable );
		}

		// Out of options, fail with an exception.
		throw FailedToMakeInstance::for_unresolved_argument( $name, $class_name );
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
	 * Make an object instance through the delegate of a given class.
	 *
	 * The delegate is configuration from outside, so what it returns is
	 * checked: whoever asked for the class relies on getting an instance of it
	 * (principle 9).
	 *
	 * @param class-string $class_name Class to make an object instance out of.
	 * @return object Instance of the class.
	 * @throws FailedToMakeInstance If the delegate returns no instance of the
	 *                              class.
	 */
	private function make_with_delegate( string $class_name ): object {
		$delegate = $this->get_delegate( $class_name );
		$object   = $delegate( $class_name );

		if ( ! $object instanceof $class_name ) {
			throw FailedToMakeInstance::for_invalid_delegate_result( $class_name, $object );
		}

		return $object;
	}

	/**
	 * Get the reflection for a class or throw an exception.
	 *
	 * @param class-string $class_name Class to get the reflection for.
	 * @return ReflectionClass Class reflection.
	 * @throws FailedToMakeInstance If the class could not be reflected.
	 */
	private function get_class_reflection( string $class_name ): ReflectionClass {
		try {
			$reflection = $this->create_class_reflection( $class_name );
		} catch ( Throwable $throwable ) {
			throw FailedToMakeInstance::for_unreflectable_class( $class_name, $throwable );
		}

		// Validate outside the catch so this precise failure is not translated again.
		if ( $reflection->getName() !== $class_name ) {
			throw FailedToMakeInstance::for_invalid_reflection( $class_name );
		}

		return $reflection;
	}

	/**
	 * Create native reflection, which may fail while autoloading the class.
	 *
	 * @param class-string $class_name Class to reflect upon.
	 * @return ReflectionClass<object> Native class reflection.
	 */
	private function create_class_reflection( string $class_name ): ReflectionClass {
		return new ReflectionClass( $class_name );
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
