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

namespace MWPD\BasicScaffold\Infrastructure;

use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\SimpleServiceContainer;
use MWPD\BasicScaffold\Exception\InvalidArgument;
use MWPD\BasicScaffold\Exception\InvalidConfiguration;
use MWPD\BasicScaffold\Exception\InvalidService;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\LazilyInstantiatedService;

/**
 * This abstract base plugin provides all the boilerplate code for working with
 * the dependency injector and the service container.
 */
abstract class ServiceBasedPlugin implements Plugin {

	// Main filters to control the flow of the plugin from outside code.

	/**
	 * Filter to control the services that are registered by the plugin.
	 *
	 * @var non-empty-string
	 */
	public const SERVICES_FILTER = 'services';

	/**
	 * Filter to control the bindings of the dependency injector.
	 *
	 * @var non-empty-string
	 */
	public const BINDINGS_FILTER = 'bindings';

	/**
	 * Filter to control the argument bindings of the dependency injector.
	 *
	 * @var non-empty-string
	 */
	public const ARGUMENTS_FILTER = 'arguments';

	/**
	 * Filter to control the shared instances of the dependency injector.
	 *
	 * @var non-empty-string
	 */
	public const SHARED_INSTANCES_FILTER = 'shared_instances';

	/**
	 * Filter to control the delegations of the dependency injector.
	 *
	 * @var non-empty-string
	 */
	public const DELEGATIONS_FILTER = 'delegations';

	/**
	 * Identifier for the injector service.
	 *
	 * @var non-empty-string
	 */
	public const INJECTOR_ID = 'injector';

	/**
	 * WordPress action to trigger the service registration on.
	 *
	 * @var non-empty-string
	 */
	protected const REGISTRATION_ACTION = 'plugins_loaded';

	/**
	 * Hook prefix to use.
	 *
	 * This is used to prefix all the hooks that are used by the plugin to avoid conflicts.
	 *
	 * @var string
	 */
	protected const HOOK_PREFIX = '';

	/**
	 * Service prefix to use.
	 *
	 * This is used to prefix all the services that are registered by the plugin.
	 *
	 * @var string
	 */
	protected const SERVICE_PREFIX = '';

	/**
	 * Whether to enable filtering of the injector configuration.
	 */
	protected bool $enable_filters;

	/**
	 * Injector instance.
	 */
	protected Injector $injector;

	/**
	 * Service container instance.
	 */
	protected ServiceContainer $service_container;

	/**
	 * Instantiate a Plugin object.
	 *
	 * @param bool                  $enable_filters    Optional. Whether to
	 *                                                 enable filtering of the
	 *                                                 injector configuration.
	 * @param Injector|null         $injector          Optional. Injector
	 *                                                 instance to use.
	 * @param ServiceContainer|null $service_container Optional. Service
	 *                                                 container instance to
	 *                                                 use.
	 */
	public function __construct(
		bool $enable_filters = true,
		?Injector $injector = null,
		?ServiceContainer $service_container = null
	) {
		/*
		 * We use what is commonly referred to as a "poka-yoke" here.
		 *
		 * We need an injector and a container. We make them injectable so that
		 * we can easily provide overrides for testing, but we also make them
		 * optional and provide default implementations for easy regular usage.
		 */

		$this->enable_filters = $enable_filters;
		$this->injector       = $injector ?? new SimpleInjector();
		$this->injector       = $this->configure_injector( $this->injector );

		$this->service_container = $service_container ?? new SimpleServiceContainer();
	}

	/**
	 * Activate the plugin.
	 */
	public function activate(): void {
		$this->register_services();

		foreach ( $this->service_container as $service ) {
			if ( $service instanceof Activateable ) {
				$service->activate();
			}
		}

		\flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 */
	public function deactivate(): void {
		$this->register_services();

		foreach ( $this->service_container as $service ) {
			if ( $service instanceof Deactivateable ) {
				$service->deactivate();
			}
		}

		\flush_rewrite_rules();
	}

	/**
	 * Register the plugin with the WordPress system.
	 *
	 * @throws InvalidService If a service is not valid.
	 */
	public function register(): void {
		\add_action(
			static::REGISTRATION_ACTION,
			[ $this, 'register_services' ],
			10,
			0
		);
	}

	/**
	 * Register the individual services of this plugin.
	 *
	 * @throws InvalidService If a service is not valid.
	 */
	public function register_services(): void {
		// Bail early so we don't instantiate services twice.
		if ( count( $this->service_container ) > 0 ) {
			return;
		}

		// Add the injector as the very first service.
		$this->service_container->put(
			static::SERVICE_PREFIX . static::INJECTOR_ID,
			$this->injector
		);

		$services = $this->get_service_classes();

		if ( $this->enable_filters ) {
			/**
			 * Filter the default services that make up this plugin.
			 *
			 * This can be used to add services to the service container for
			 * this plugin.
			 *
			 * @param array<string,class-string|callable> $services Associative array of
			 *                                                      identifiers mapped to
			 *                                                      fully qualified class
			 *                                                      names or callables.
			 */
			$services = \apply_filters(
				static::HOOK_PREFIX . static::SERVICES_FILTER,
				$services
			);
		}

		while ( null !== key( $services ) ) {
			$id         = key( $services );
			$class_name = $this->maybe_resolve( current( $services ) );

			if ( ! is_string( $id ) ) {
				throw InvalidService::from_invalid_identifier( $id );
			}

			if ( ! is_string( $class_name ) ) {
				throw InvalidService::from_invalid_class_name( $class_name );
			}

			/**
			 * The resolved value is guaranteed to be a class name at this point.
			 *
			 * @var class-string $class_name
			 */

			if ( $class_name !== current( $services ) ) {
				$services[ $id ] = $class_name;
			}

			/**
			 * The resolved value is guaranteed to be a class name at this point.
			 *
			 * @var class-string $class_name
			 */

			// Delay registering the service until all dependencies are met.
			if ( is_a( $class_name, HasDependencies::class, true ) &&
				! $this->dependencies_are_met( $id, $class_name, $services ) ) {
				next( $services );
				continue;
			}

			$this->schedule_potential_service_registration( $id, $class_name );
			next( $services );
		}
	}

	/**
	 * The service registration works in three steps:
	 *
	 * 1. All services that need to be registered are gathered.
	 * 2. A first pass over the services registers all those that either don't have
	 *    dependencies or where all dependencies are met already.
	 * 3. A second pass registers the remaining services as soon as their
	 *    dependencies are met.
	 *
	 * The first pass is done directly from the register_services() method, as it
	 * needs to ensure that the services are registered in the order they were
	 * provided.
	 *
	 * The second pass is done through schedule_potential_service_registration(),
	 * which adds the service to the registration schedule. For regular services,
	 * this means they are registered immediately. For delayed services, this means
	 * they are only registered upon their registration action.
	 *
	 * Services that have delayed dependencies are registered as soon as all their
	 * dependencies are available. This is done by registering a callback to each
	 * dependency's registration action hook with a high priority. This means that
	 * the service's registration is triggered by the first dependency that was
	 * registered. It then checks if all other dependencies are available as well,
	 * and if so, registers the service.
	 *
	 * @param string       $id         ID of the service to register.
	 * @param class-string $class_name Class of the service to register.
	 */
	protected function schedule_potential_service_registration( string $id, string $class_name ): void {
		if ( is_a( $class_name, Delayed::class, true ) ) {
			$registration_action = $class_name::get_registration_action();

			if ( \did_action( $registration_action ) ) {
				$this->maybe_register_service( $id, $class_name );
			} else {
				\add_action(
					$registration_action,
					function () use ( $id, $class_name ): void {
						$this->maybe_register_service( $id, $class_name );
					},
					10,
					0
				);
			}
		} else {
			$this->maybe_register_service( $id, $class_name );
		}
	}

	/**
	 * The maybe_register_service() method is the third step of registering a service.
	 * It checks whether the service was registered before and whether it is actually
	 * needed, and only then registers it.
	 *
	 * The three checks being done are:
	 * 1. Is the service already registered? => Skip if yes.
	 * 2. Is the service conditional? => Skip if conditions not met.
	 * 3. Register the service.
	 *
	 * @param string       $id         ID of the service to register.
	 * @param class-string $class_name Class of the service to register.
	 */
	protected function maybe_register_service( string $id, string $class_name ): void {
		// Ensure we don't register the same service more than once.
		if ( $this->service_container->has( $id ) ) {
			return;
		}

		// Only instantiate services that are actually needed.
		if ( is_a( $class_name, Conditional::class, true ) && ! $class_name::is_needed() ) {
			return;
		}

		$service = $this->instantiate_service( $class_name );
		$this->service_container->put( $id, $service );

		if ( $service instanceof Registerable ) {
			$service->register();
		}
	}

	/**
	 * Get the service container that contains the services that make up the
	 * plugin.
	 *
	 * @return ServiceContainer Service container of the plugin.
	 */
	public function get_container(): ServiceContainer {
		return $this->service_container;
	}

	/**
	 * Instantiate a single service.
	 *
	 * @param class-string $class_name Service class to instantiate.
	 *
	 * @throws InvalidService If the service could not be properly instantiated.
	 *
	 * @return Service Instantiated service.
	 */
	protected function instantiate_service( $class_name ): Service {
		/*
		 * If the service is not registerable, we default to lazily instantiated
		 * services here for some basic optimization.
		 *
		 * The services will be properly instantiated once they are retrieved
		 * from the service container.
		 */
		if ( ! is_a( $class_name, Registerable::class, true ) ) {
			return new LazilyInstantiatedService(
				fn(): object => $this->injector->make( $class_name )
			);
		}

		// The service needs to be registered, so instantiate right away.
		$service = $this->injector->make( $class_name );

		if ( ! $service instanceof Service ) {
			throw InvalidService::from_service( $service );
		}

		return $service;
	}

	/**
	 * Configure the provided injector.
	 *
	 * This method defines the mappings that the injector knows about, and the
	 * logic it requires to make more complex instantiations work.
	 *
	 * For more complex plugins, this should be extracted into a separate
	 * object
	 * or into configuration files.
	 *
	 * @param Injector $injector Injector instance to configure.
	 * @return Injector Configured injector instance.
	 * @throws InvalidArgument If an argument is not recognized.
	 * @throws InvalidConfiguration If the injector configuration structure is invalid.
	 * @throws InvalidService If the injector configuration details are invalid.
	 */
	protected function configure_injector( Injector $injector ): Injector {
		$bindings         = $this->get_bindings();
		$shared_instances = $this->get_shared_instances();
		$arguments        = $this->get_arguments();
		$delegations      = $this->get_delegations();

		if ( $this->enable_filters ) {
			/**
			 * Filter the default bindings that are provided by the plugin.
			 *
			 * This can be used to swap implementations out for alternatives.
			 *
			 * @param array<class-string,class-string|callable> $bindings Associative array of
			 *                                                            interface =>
			 *                                                            implementation
			 *                                                            bindings. Both
			 *                                                            should be FQCNs.
			 * @return array<class-string,class-string|callable> Modified bindings.
			 * @psalm-suppress InvalidArgument
			 */
			$bindings = \apply_filters(
				static::HOOK_PREFIX . static::BINDINGS_FILTER,
				$bindings
			);

			/**
			 * Filter the default argument bindings that are provided by the
			 * plugin.
			 *
			 * This can be used to override scalar values.
			 *
			 * @param array<array<string, mixed>> $arguments Associative array of
			 *                                               class => arguments
			 *                                               mappings. The arguments
			 *                                               array maps argument names
			 *                                               to values.
			 * @return array<array<string, mixed>> Modified arguments.
			 */
			$arguments = \apply_filters(
				static::HOOK_PREFIX . static::ARGUMENTS_FILTER,
				$arguments
			);

			/**
			 * Filter the instances that are shared by default by the plugin.
			 *
			 * This can be used to turn objects that were added externally into
			 * shared instances.
			 *
			 * @param array<class-string|callable> $shared_instances Array of FQCNs to turn
			 *                                              into shared objects.
			 * @return array<class-string|callable> Modified shared instances.
			 * @psalm-suppress InvalidArgument
			 */
			$shared_instances = \apply_filters(
				static::HOOK_PREFIX . static::SHARED_INSTANCES_FILTER,
				$shared_instances
			);

			/**
			 * Filter the delegations that are provided by the plugin.
			 *
			 * This can be used to override the default delegation logic for a
			 * class.
			 *
			 * @param array<class-string,callable> $delegations Associative array of class =>
			 *                                                  callable mappings.
			 * @return array<class-string,callable> Modified delegations.
			 */
			$delegations = \apply_filters(
				static::HOOK_PREFIX . static::DELEGATIONS_FILTER,
				$delegations
			);
		}

		$injector = $this->parse_bindings( $bindings, $injector );
		$injector = $this->parse_arguments( $arguments, $injector );
		$injector = $this->parse_shared_instances( $shared_instances, $injector );
		$injector = $this->parse_delegations( $delegations, $injector );

		return $injector;
	}

	/**
	 * Parse the bindings configuration.
	 *
	 * @param mixed    $bindings Associative array of fully qualified class names.
	 * @param Injector $injector Injector instance to configure.
	 * @return Injector Configured injector instance.
	 * @throws InvalidConfiguration If the bindings configuration is invalid.
	 * @throws InvalidService If the bindings configuration details are invalid.
	 */
	protected function parse_bindings( $bindings, Injector $injector ): Injector {
		if ( ! is_array( $bindings ) ) {
			throw InvalidConfiguration::from_invalid_bindings( $bindings );
		}

		foreach ( $bindings as $from => $to ) {
			$to = $this->maybe_resolve( $to );

			if ( ! is_string( $from ) ) {
				throw InvalidService::from_invalid_identifier( $from );
			}

			if ( ! is_string( $to ) ) {
				throw InvalidService::from_invalid_identifier( $to );
			}

			/**
			 * The resolved values are guaranteed to be strings at this point.
			 *
			 * @var class-string $from
			 * @var class-string $to
			 */

			$injector = $injector->bind( $from, $to );
		}

		return $injector;
	}

	/**
	 * Parse the arguments configuration.
	 *
	 * @param mixed    $arguments Associative array of class names and argument maps.
	 * @param Injector $injector Injector instance to configure.
	 * @return Injector Configured injector instance.
	 * @throws InvalidArgument If the argument name is not a string.
	 * @throws InvalidConfiguration If the arguments configuration is invalid.
	 * @throws InvalidService If the arguments configuration details are invalid.
	 */
	protected function parse_arguments( $arguments, Injector $injector ): Injector {
		if ( ! is_array( $arguments ) ) {
			throw InvalidConfiguration::from_invalid_arguments( $arguments );
		}

		foreach ( $arguments as $class_name => $argument_map ) {
			$class_name = $this->maybe_resolve( $class_name );

			if ( ! is_string( $class_name ) ) {
				throw InvalidService::from_invalid_identifier( $class_name );
			}

			/**
			 * The resolved value is guaranteed to be a string at this point.
			 *
			 * @var class-string $class_name
			 */

			if ( ! is_array( $argument_map ) ) {
				throw InvalidService::from_invalid_argument_map( $class_name, $argument_map );
			}

			foreach ( $argument_map as $name => $value ) {
				// We don't try to resolve the $value here, as we might want to
				// pass a callable as-is.
				$name = $this->maybe_resolve( $name );

				if ( ! is_string( $name ) ) {
					throw InvalidArgument::from_name( $name );
				}

				$injector = $injector->bind_argument( $class_name, $name, $value );
			}
		}

		return $injector;
	}

	/**
	 * Parse the shared instances configuration.
	 *
	 * @param mixed    $shared_instances Array of class names.
	 * @param Injector $injector Injector instance to configure.
	 * @return Injector Configured injector instance.
	 * @throws InvalidConfiguration If the shared instances configuration is invalid.
	 * @throws InvalidService If the shared instances configuration details are invalid.
	 */
	protected function parse_shared_instances( $shared_instances, Injector $injector ): Injector {
		if ( ! is_array( $shared_instances ) ) {
			throw InvalidConfiguration::from_invalid_shared_instances( $shared_instances );
		}

		foreach ( $shared_instances as $shared_instance ) {
			$shared_instance = $this->maybe_resolve( $shared_instance );

			if ( ! is_string( $shared_instance ) ) {
				throw InvalidService::from_invalid_identifier( $shared_instance );
			}

			/**
			 * The resolved value is guaranteed to be a string at this point.
			 *
			 * @var class-string $shared_instance
			 */

			$injector = $injector->share( $shared_instance );
		}

		return $injector;
	}

	/**
	 * Parse the delegations configuration.
	 *
	 * @param mixed    $delegations Associative array of class names and callables.
	 * @param Injector $injector Injector instance to configure.
	 * @return Injector Configured injector instance.
	 * @throws InvalidConfiguration If the delegations configuration is invalid.
	 * @throws InvalidService If the delegations configuration details are invalid.
	 */
	protected function parse_delegations( $delegations, Injector $injector ): Injector {
		if ( ! is_array( $delegations ) ) {
			throw InvalidConfiguration::from_invalid_delegations( $delegations );
		}

		foreach ( $delegations as $class_name => $delegation ) {
			// We don't try to resolve the $callable here, as we want to pass it
			// on as-is.
			$class_name = $this->maybe_resolve( $class_name );

			if ( ! is_string( $class_name ) ) {
				throw InvalidService::from_invalid_identifier( $class_name );
			}

			/**
			 * The resolved value is guaranteed to be a string at this point.
			 *
			 * @var class-string $class_name
			 */

			if ( ! is_callable( $delegation ) ) {
				throw InvalidService::from_invalid_delegation( $class_name, $delegation );
			}

			$injector = $injector->delegate( $class_name, $delegation );
		}

		return $injector;
	}

	/**
	 * Get the list of services to register.
	 *
	 * @return array<string,class-string|callable> Associative array of identifiers
	 *                                             mapped to fully qualified class
	 *                                             names or callables.
	 */
	protected function get_service_classes(): array {
		return [];
	}

	/**
	 * Get the bindings for the dependency injector.
	 *
	 * The bindings let you map interfaces (or classes) to the classes that
	 * should be used to implement them.
	 *
	 * @return array<class-string,class-string|callable> Associative array of fully qualified class names.
	 */
	protected function get_bindings(): array {
		return [];
	}

	/**
	 * Get the argument bindings for the dependency injector.
	 *
	 * The argument bindings let you map specific argument values for specific
	 * classes.
	 *
	 * @return array<array<string,mixed>> Associative array of arrays mapping
	 *                                     argument names to argument values.
	 */
	protected function get_arguments(): array {
		return [];
	}

	/**
	 * Get the shared instances for the dependency injector.
	 *
	 * These classes will only be instantiated once by the injector and then
	 * reused on subsequent requests.
	 *
	 * This effectively turns them into singletons, without any of the
	 * drawbacks of the actual Singleton anti-pattern.
	 *
	 * @return array<class-string|callable> Array of fully qualified class names.
	 */
	protected function get_shared_instances(): array {
		return [];
	}

	/**
	 * Get the delegations for the dependency injector.
	 *
	 * These are basically factories to provide custom instantiation logic for
	 * classes.
	 *
	 * @return array<class-string,callable> Associative array of callables.
	 */
	protected function get_delegations(): array {
		return [];
	}

	/**
	 * Maybe resolve a value that is a callable instead of a scalar.
	 *
	 * Values that are passed through this method can optionally be provided as
	 * callables instead of direct values and will be evaluated when needed.
	 *
	 * @param mixed $value Value to potentially resolve.
	 * @return mixed Resolved or unchanged value.
	 */
	protected function maybe_resolve( $value ) {
		if ( is_callable( $value ) ) {
			return $value( $this->injector, $this->service_container );
		}

		return $value;
	}

	/**
	 * The collect_missing_dependencies() method is a helper for the dependency
	 * resolution process. It returns an array of service IDs that are required by
	 * the current service but not yet registered.
	 *
	 * Note: This is different from requirements in that dependencies are always
	 * other services, while requirements can be arbitrary conditions.
	 *
	 * @param class-string                        $class_name Service class name of the service with dependencies.
	 * @param array<string,class-string|callable> $services List of services to register.
	 *
	 * @throws InvalidService If the required service is not recognized.
	 *
	 * @return array<string,class-string|callable> List of missing dependencies as a
	 *                                             $service_id => $service_class mapping.
	 */
	protected function collect_missing_dependencies( string $class_name, array $services ): array {
		if ( ! is_a( $class_name, HasDependencies::class, true ) ) {
			return [];
		}

		$dependencies = $class_name::get_dependencies();
		$missing      = [];

		foreach ( $dependencies as $dependency ) {
			// Bail if it depends on a service that is not recognized.
			if ( ! array_key_exists( $dependency, $services ) ) {
				throw InvalidService::from_service_id( $dependency );
			}

			if ( $this->service_container->has( $dependency ) ) {
				continue;
			}

			$missing[ $dependency ] = $services[ $dependency ];
		}

		return $missing;
	}

	/**
	 * Determine if the dependencies for a service to be registered are met.
	 *
	 * @param string                              $id         Service ID of the service with dependencies.
	 * @param class-string                        $class_name Service class name of the service with dependencies.
	 * @param array<string,class-string|callable> $services   List of services to be registered.
	 *
	 * @throws InvalidService If the required service is not recognized.
	 *
	 * @return bool Whether the dependencies for the service have been met.
	 */
	protected function dependencies_are_met( string $id, string $class_name, array &$services ): bool {
		$missing_dependencies = $this->collect_missing_dependencies( $class_name, $services );

		if ( empty( $missing_dependencies ) ) {
			return true;
		}

		$registration_actions = [];
		foreach ( $missing_dependencies as $dependency_id => $dependency_class ) {
			$resolved_dependency_class = $this->maybe_resolve( $dependency_class );

			if ( ! is_string( $resolved_dependency_class ) ) {
				throw InvalidService::from_invalid_identifier( $dependency_id );
			}

			/**
			 * The resolved value is guaranteed to be a string at this point.
			 *
			 * @var class-string $resolved_dependency_class
			 */

			if ( $resolved_dependency_class !== $dependency_class ) {
				$services[ $dependency_id ] = $resolved_dependency_class;
				$dependency_class           = $resolved_dependency_class;
			}

			// Check if dependency is delayed.
			if ( is_a( $dependency_class, Delayed::class, true ) ) {
				$action = $dependency_class::get_registration_action();

				if ( ! \did_action( $action ) ) {
					$registration_actions[ $action ][] = [
						'id'    => $dependency_id,
						'class' => $dependency_class,
					];
				}
			}
		}

		// If we have delayed dependencies, schedule registration after they're loaded.
		if ( ! empty( $registration_actions ) ) {
			foreach ( $registration_actions as $action => $dependencies ) {
				\add_action(
					$action,
					function () use ( $id, $class_name, $services, $dependencies ): void {
						// Check if all dependencies from this action are now available.
						foreach ( $dependencies as $dependency ) {
							if ( ! $this->service_container->has( $dependency['id'] ) ) {
								return;
							}
						}

						// Recheck all dependencies in case there are others.
						if ( $this->dependencies_are_met( $id, $class_name, $services ) ) {
							$this->maybe_register_service( $id, $class_name );
						}
					},
					PHP_INT_MAX,
					0
				);
			}
			return false;
		}

		// Move this service to the end of the services array since its dependencies
		// haven't been registered yet but will be encountered later.
		unset( $services[ $id ] );
		$services[ $id ] = $class_name;

		return false;
	}
}
