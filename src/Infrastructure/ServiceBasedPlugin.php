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

use MWPD\BasicScaffold\Exception\InvalidArgument;
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
	 *
	 * @var bool
	 */
	protected $enable_filters;

	/**
	 * Injector instance.
	 *
	 * @var Injector
	 */
	protected $injector;

	/**
	 * Service container instance.
	 *
	 * @var ServiceContainer
	 */
	protected $service_container;

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
		$this->injector       = $injector ?? new Injector\SimpleInjector();
		$this->injector       = $this->configure_injector( $this->injector );

		$this->service_container = $service_container ?? new ServiceContainer\SimpleServiceContainer();
	}

	/**
	 * Activate the plugin.
	 *
	 * @return void
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
	 *
	 * @return void
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
	 * @return void
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
	 *
	 * @return void
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
			 * @param array<string> $services Associative array of identifier =>
			 *                                class mappings. The provided
			 *                                classes need to implement the
			 *                                Service interface.
			 */
			$services = \apply_filters(
				static::HOOK_PREFIX . static::SERVICES_FILTER,
				$services
			);
		}

		foreach ( $services as $id => $class_name ) {
			$id         = $this->maybe_resolve( $id );
			$class_name = $this->maybe_resolve( $class_name );

			if ( ! is_string( $id ) ) {
				throw InvalidService::from_invalid_identifier( $id );
			}

			if ( ! is_string( $class_name ) ) {
				throw InvalidService::from_invalid_class_name( $class_name );
			}

			/**
			 * The class name is guaranteed to be a string at this point.
			 *
			 * @var class-string $class_name
			 */

			// Allow the services to delay their registration.
			if ( is_a( $class_name, Delayed::class, true ) ) {
				$registration_action = $class_name::get_registration_action();

				if ( \did_action( $registration_action ) ) {
					$this->register_service( $id, $class_name );

					continue;
				}

				\add_action(
					$class_name::get_registration_action(),
					function () use ( $id, $class_name ) {
						$this->register_service( $id, $class_name );
					},
					10,
					0
				);

				continue;
			}

			$this->register_service( $id, $class_name );
		}
	}

	/**
	 * Register a single service.
	 *
	 * @param string       $id         Identifier of the service.
	 * @param class-string $class_name Class name of the service.
	 */
	protected function register_service( string $id, string $class_name ): void {
		// Only instantiate services that are actually needed.
		if ( is_a( $class_name, Conditional::class, true )
			&& ! $class_name::is_needed() ) {
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
	 * @throws InvalidService If the injector configuration is invalid.
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
			 * @param array<string> $bindings Associative array of interface =>
			 *                                implementation bindings. Both
			 *                                should be FQCNs.
			 * @psalm-suppress RedundantCast
			 */
			$bindings = (array) \apply_filters(
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
			 * @psalm-suppress RedundantCast,InvalidArgument
			 */
			$arguments = (array) \apply_filters(
				static::HOOK_PREFIX . static::ARGUMENTS_FILTER,
				$arguments
			);

			/**
			 * Filter the instances that are shared by default by the plugin.
			 *
			 * This can be used to turn objects that were added externally into
			 * shared instances.
			 *
			 * @param array<string> $shared_instances Array of FQCNs to turn
			 *                                        into shared objects.
			 * @psalm-suppress RedundantCast
			 */
			$shared_instances = (array) \apply_filters(
				static::HOOK_PREFIX . static::SHARED_INSTANCES_FILTER,
				$shared_instances
			);

			/**
			 * Filter the instances that are shared by default by the plugin.
			 *
			 * This can be used to turn objects that were added externally into
			 * shared instances.
			 *
			 * @param array<callable> $delegations Associative array of class =>
			 *                                     callable mappings.
			 * @psalm-suppress RedundantCast,InvalidArgument
			 */
			$delegations = (array) \apply_filters(
				static::HOOK_PREFIX . static::DELEGATIONS_FILTER,
				$delegations
			);
		}

		foreach ( $bindings as $from => $to ) {
			$from = $this->maybe_resolve( $from );
			$to   = $this->maybe_resolve( $to );

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
	 * @return array<string> Associative array of identifiers mapped to fully
	 *                       qualified class names.
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
	 * @return array<string> Associative array of fully qualified class names.
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
	 * @return array<array<string, mixed>> Associative array of arrays mapping
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
	 * @return array<string> Array of fully qualified class names.
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
	 * @return array<callable> Associative array of callables.
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
}
