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
use MWPD\BasicScaffold\Exception\FailedToResolveDependencies;
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
	 * Services of this plugin, with their class names resolved.
	 *
	 * @var array<string,class-string>
	 */
	private array $services = [];

	/**
	 * Services that wait for their dependencies to be registered.
	 *
	 * @var array<string,class-string>
	 */
	private array $pending_services = [];

	/**
	 * Actions after which the pending services are checked again.
	 *
	 * @var array<non-empty-string,true>
	 */
	private array $resumption_actions = [];

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
		 *
		 * The constructor only stores them. The injector is configured when the
		 * services are registered, see register_services().
		 */

		$this->enable_filters    = $enable_filters;
		$this->injector          = $injector ?? new SimpleInjector();
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

		/*
		 * The injector is configured here rather than in the constructor. The
		 * plugin is constructed while its main file is being included, when
		 * plugins loaded after it have had no chance yet to hook into the
		 * injector filters (principle 12). Here, they have. Callables in the
		 * configuration also receive the service container, which is fully set
		 * up by now. The bail-out above keeps this from running twice.
		 */
		$this->injector = $this->configure_injector( $this->injector );

		/*
		 * A constructor parameter typed Injector receives the plugin's own
		 * injector, whichever implementation was passed in. This is what a
		 * service takes when it has to make objects on demand, instead of
		 * reaching for a static service locator (principle 5). It is set up
		 * here, after the composition root's configuration, so that every
		 * plugin gets it and no delegation can hand out a different injector.
		 */
		$this->injector = $this->injector->delegate(
			Injector::class,
			fn(): Injector => $this->injector
		);

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
			 * @psalm-suppress InvalidArgument
			 */
			$services = \apply_filters(
				static::HOOK_PREFIX . static::SERVICES_FILTER,
				$services
			);
		}

		/**
		 * The filter may return anything, so check every entry (principle 9).
		 *
		 * @var array<mixed> $services
		 */

		$this->services         = [];
		$this->pending_services = [];

		// The first pass goes through the services in the order they were
		// provided, and sets aside those whose dependencies come later.
		foreach ( $services as $id => $service ) {
			if ( ! is_string( $id ) ) {
				throw InvalidService::from_invalid_identifier( $id );
			}

			$class_name = $this->maybe_resolve( $service );

			if ( ! is_string( $class_name ) ) {
				throw InvalidService::from_invalid_class_name( $class_name );
			}

			/**
			 * The resolved value is guaranteed to be a class name at this point.
			 *
			 * @var class-string $class_name
			 */

			$services[ $id ]       = $class_name;
			$this->services[ $id ] = $class_name;

			// Only services with dependencies can have to wait, so only those
			// are checked, and only those ever become pending.
			if ( is_a( $class_name, HasDependencies::class, true )
				&& ! $this->dependencies_are_met( $id, $class_name, $services ) ) {
				$this->pending_services[ $id ] = $class_name;
				continue;
			}

			$this->schedule_potential_service_registration( $id, $class_name );
		}

		$this->register_pending_services();
	}

	/**
	 * Register the pending services whose dependencies are met by now, then
	 * wait for the others or report the ones that can never be registered.
	 *
	 * Scheduling a service can register it at once, which can meet the
	 * dependencies of another pending service. So the passes over the pending
	 * services repeat until one of them schedules nothing.
	 *
	 * The services left after that wait for a delayed service. This method runs
	 * again after each registration action they wait for, once the delayed
	 * services hooked to that action have been registered.
	 *
	 * @throws InvalidService If a dependency is not recognized.
	 * @throws FailedToResolveDependencies If the dependencies of a service can
	 *                                     never be met.
	 */
	private function register_pending_services(): void {
		$services = $this->services;

		do {
			$scheduled_any = false;

			foreach ( $this->pending_services as $id => $class_name ) {
				if ( ! $this->dependencies_are_met( $id, $class_name, $services ) ) {
					continue;
				}

				unset( $this->pending_services[ $id ] );
				$this->schedule_potential_service_registration( $id, $class_name );
				$scheduled_any = true;
			}
		} while ( $scheduled_any );

		$this->drop_or_report_unregistrable_services();

		foreach ( array_keys( $this->collect_awaited_actions() ) as $action ) {
			if ( isset( $this->resumption_actions[ $action ] ) ) {
				continue;
			}

			$this->resumption_actions[ $action ] = true;

			// The latest priority, so that the delayed services hooked to this
			// action are registered by the time the pending ones are checked.
			\add_action(
				$action,
				function (): void {
					$this->register_pending_services();
				},
				PHP_INT_MAX,
				0
			);
		}
	}

	/**
	 * Deal with the pending services that depend on a service that will not be
	 * registered in this request.
	 *
	 * A Conditional service that is not needed is never registered, so a
	 * service that depends on it can never be registered either. If that
	 * service is not needed in this request itself, nothing is lost and it is
	 * dropped. Otherwise it is a mistake that must not go unnoticed.
	 *
	 * Dropping a service can leave another one without a dependency, so the
	 * passes repeat until one of them drops nothing.
	 *
	 * @throws FailedToResolveDependencies If a service that is needed depends on
	 *                                     a service that is not registered.
	 */
	private function drop_or_report_unregistrable_services(): void {
		do {
			$dropped_any = false;

			foreach ( $this->pending_services as $id => $class_name ) {
				$dependency_id = $this->find_unregistered_dependency( $class_name );

				if ( null === $dependency_id ) {
					continue;
				}

				if ( is_a( $class_name, Conditional::class, true ) ) {
					// A delayed service may only be asked whether it is needed
					// once its registration action has run.
					if ( is_a( $class_name, Delayed::class, true )
						&& ! \did_action( $class_name::get_registration_action() ) ) {
						continue;
					}

					if ( ! $class_name::is_needed() ) {
						unset( $this->pending_services[ $id ] );
						$dropped_any = true;
						continue;
					}
				}

				throw FailedToResolveDependencies::for_unregistered_dependency( $id, $dependency_id );
			}
		} while ( $dropped_any );
	}

	/**
	 * Find a dependency of a service that is not registered and will not be in
	 * this request.
	 *
	 * @param class-string $class_name Class of the service to check.
	 *
	 * @throws InvalidService If a dependency is not recognized.
	 *
	 * @return string|null ID of the dependency, or null if there is none.
	 */
	private function find_unregistered_dependency( string $class_name ): ?string {
		$missing_dependencies = $this->collect_missing_dependencies( $class_name, $this->services );

		foreach ( array_keys( $missing_dependencies ) as $dependency_id ) {
			if ( ! isset( $this->pending_services[ $dependency_id ] )
				&& null === $this->get_awaited_action( $dependency_id ) ) {
				return $dependency_id;
			}
		}

		return null;
	}

	/**
	 * Collect the registration actions that the pending services wait for.
	 *
	 * A pending service waits for the actions of the delayed services it
	 * depends on, directly or through other pending services. A Conditional
	 * service whose dependency will not be registered waits for its own action,
	 * to find out whether it is needed at all.
	 *
	 * @throws InvalidService If a dependency is not recognized.
	 * @throws FailedToResolveDependencies If pending services depend on each
	 *                                     other.
	 *
	 * @return array<non-empty-string,true> Actions to wait for, as keys.
	 */
	private function collect_awaited_actions(): array {
		$actions   = [];
		$collected = [];

		foreach ( array_keys( $this->pending_services ) as $id ) {
			$actions += $this->collect_actions_awaited_by( $id, [], $collected );
		}

		return $actions;
	}

	/**
	 * Collect the registration actions that one pending service waits for.
	 *
	 * Each service is only followed once, however many services depend on it,
	 * so the work stays linear in the number of dependencies.
	 *
	 * @param string                                     $id        ID of the pending service.
	 * @param array<int,string>                          $path      IDs of the pending services that
	 *                                                              depend on this one, in the order
	 *                                                              they were followed.
	 * @param array<string,array<non-empty-string,true>> $collected Actions already collected, by the
	 *                                                              ID of the service that waits for
	 *                                                              them.
	 *
	 * @throws FailedToResolveDependencies If pending services depend on each
	 *                                     other.
	 *
	 * @return array<non-empty-string,true> Actions to wait for, as keys.
	 */
	private function collect_actions_awaited_by( string $id, array $path, array &$collected ): array {
		if ( isset( $collected[ $id ] ) ) {
			return $collected[ $id ];
		}

		$path[]     = $id;
		$class_name = $this->pending_services[ $id ];
		$actions    = [];

		$missing_dependencies = $this->collect_missing_dependencies( $class_name, $this->services );

		foreach ( array_keys( $missing_dependencies ) as $dependency_id ) {
			$cycle_start = array_search( $dependency_id, $path, true );

			if ( false !== $cycle_start ) {
				throw FailedToResolveDependencies::for_dependency_cycle(
					array_merge( array_slice( $path, $cycle_start ), [ $dependency_id ] )
				);
			}

			if ( isset( $this->pending_services[ $dependency_id ] ) ) {
				$actions += $this->collect_actions_awaited_by( $dependency_id, $path, $collected );
				continue;
			}

			$action = $this->get_awaited_action( $dependency_id );

			if ( null !== $action ) {
				$actions[ $action ] = true;
				continue;
			}

			// The dependency will not be registered. Only a delayed Conditional
			// service that cannot be asked yet whether it is needed is left
			// pending in that case, see drop_or_report_unregistrable_services().
			if ( ! is_a( $class_name, Delayed::class, true ) ) {
				throw FailedToResolveDependencies::for_unregistered_dependency( $id, $dependency_id );
			}

			$actions[ $class_name::get_registration_action() ] = true;
		}

		$collected[ $id ] = $actions;

		return $actions;
	}

	/**
	 * Get the registration action that a scheduled service still waits for.
	 *
	 * @param string $id ID of a service that is not pending.
	 *
	 * @return non-empty-string|null Registration action, or null if the service does not
	 *                               wait for one, because it is not delayed or its
	 *                               action has already run.
	 */
	private function get_awaited_action( string $id ): ?string {
		$class_name = $this->services[ $id ];

		if ( ! is_a( $class_name, Delayed::class, true ) ) {
			return null;
		}

		$action = $class_name::get_registration_action();

		return \did_action( $action ) ? null : $action;
	}

	/**
	 * The service registration works in three steps:
	 *
	 * 1. All services that need to be registered are gathered.
	 * 2. Each service is scheduled once all of its dependencies are registered.
	 * 3. A scheduled service is registered, if it is needed.
	 *
	 * The second step starts in register_services(), which goes through the
	 * services in the order they were provided and sets aside those whose
	 * dependencies are not registered yet. register_pending_services() then
	 * schedules them as their dependencies arrive, including dependencies that
	 * are delayed to a later action.
	 *
	 * Scheduling is done here, so every service goes through it, whether or not
	 * it had to wait for its dependencies. For regular services, this means they
	 * are registered immediately. For delayed services, this means they are only
	 * registered upon their own registration action, even when their
	 * dependencies arrived earlier.
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
	 * Determine if the dependencies for a service to be registered are met,
	 * meaning that all of them are registered.
	 *
	 * This only checks. Waiting for the dependencies that are not met yet is up
	 * to register_services().
	 *
	 * @param string                              $id         Service ID of the service with dependencies.
	 * @param class-string                        $class_name Service class name of the service with dependencies.
	 * @param array<string,class-string|callable> $services   List of services to be registered. It is not changed.
	 *
	 * @throws InvalidService If the required service is not recognized.
	 *
	 * @return bool Whether the dependencies for the service have been met.
	 */
	protected function dependencies_are_met( string $id, string $class_name, array &$services ): bool {
		return empty( $this->collect_missing_dependencies( $class_name, $services ) );
	}
}
