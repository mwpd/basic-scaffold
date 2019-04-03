<?php declare( strict_types=1 );

/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

namespace MWPD\BasicScaffold\Infrastructure;

use MWPD\BasicScaffold\Exception\InvalidService;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\LazilyInstantiatedService;

/**
 * This abstract base plugin provides all the boilerplate code for working with
 * the dependency injector and the service container.
 */
abstract class ServiceBasedPlugin implements Plugin {

	// Main filters to control the flow of the plugin from outside code.
	public const SERVICES_FILTER         = 'services';
	public const BINDINGS_FILTER         = 'bindings';
	public const SHARED_INSTANCES_FILTER = 'shared_instances';
	public const ARGUMENTS_FILTER        = 'arguments';

	// Service identifier for the injector.
	public const INJECTOR_ID = 'injector';

	/*
	 * Override the following 5 constants in the actual plugin implementation
	 * class to define how to assemble this specific plugin.
	 */

	// Services that make up this plugin.
	protected const SERVICES = [];
	// Interface to implementation bindings for the injector.
	protected const BINDINGS = [];
	// Instances that are meant to be reused instead of reinstantiated.
	protected const SHARED_INSTANCES = [];

	// Prefixes to use.
	protected const HOOK_PREFIX    = '';
	protected const SERVICE_PREFIX = '';

	/** @var Injector */
	protected $injector;

	/** @var ServiceContainer */
	protected $service_container;

	/**
	 * Instantiate a Plugin object.
	 *
	 * @param Injector|null         $injector          Optional. Injector
	 *                                                 instance to use.
	 * @param ServiceContainer|null $service_container Optional. Service
	 *                                                 container instance to
	 *                                                 use.
	 */
	public function __construct(
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

		$this->injector = $injector ?? new Injector\SimpleInjector();
		$this->injector = $this->configure_injector( $this->injector );

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
		\add_action( 'plugins_loaded', [ $this, 'register_services' ] );
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

		foreach ( $this->get_service_classes() as $id => $class ) {
			// Only instantiate services that are actually needed.
			if ( is_a( $class, Conditional::class, true )
			     && ! $class::is_needed() ) {
				continue;
			}

			$this->service_container->put(
				$id,
				$this->instantiate_service( $class )
			);
		}

		// Give all Registerables the opportunity to register themselves.
		foreach ( $this->service_container as $service ) {
			if ( $service instanceof Registerable ) {
				$service->register();
			}
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
	 * @param string $class Service class to instantiate.
	 *
	 * @throws InvalidService If the service could not be properly instantiated.
	 *
	 * @return Service Instantiated service.
	 */
	protected function instantiate_service( $class ): Service {
		/*
		 * If the service is not registerable, we default to lazily instantiated
		 * services here for some basic optimization.
		 *
		 * The services will be properly instantiated once they are retrieved
		 * from the service container.
		 */
		if ( ! is_a( $class, Registerable::class, true ) ) {
			return new LazilyInstantiatedService(
				function () use ( $class ): object {
					return $this->injector->make( $class );
				}
			);
		}

		// The service needs to be registered, so instantiate right away.
		$service = $this->injector->make( $class );

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
	 * For more complex plugins, this should be extracted into a separate object
	 * or into configuration files.
	 *
	 * @param Injector $injector Injector instance to configure.
	 * @return Injector Configured injector instance.
	 */
	protected function configure_injector( Injector $injector ): Injector {
		foreach ( $this->get_bindings() as $from => $to ) {
			$injector = $injector->bind( $from, $to );
		}

		foreach ( $this->get_shared_instances() as $shared_instance ) {
			$injector = $injector->share( $shared_instance );
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
		/**
		 * Filter the default services that make up this plugin.
		 *
		 * This can be used to add services to the service container for this
		 * plugin.
		 *
		 * @param array<string> $services Associative array of identifier =>
		 *                                class mappings. The provided classes
		 *                                need to implement the Service
		 *                                interface.
		 */
		return \apply_filters(
			static::HOOK_PREFIX . static::SERVICES_FILTER,
			static::SERVICES
		);
	}

	/**
	 * Get the bindings for the dependency injector.
	 *
	 * The bindings let you map interfaces (or classes) to the classes that
	 * should be used to implement them.
	 *
	 * @return array Array of fully qualified class names.
	 */
	protected function get_bindings(): array {
		/**
		 * Filter the default bindings that are provided by the plugin.
		 *
		 * This can be used to swap implementations out for alternatives.
		 *
		 * @param array<string> $bindings Associative array of interface =>
		 *                                implementation bindings. Both should
		 *                                be FQCNs.
		 */
		return (array) \apply_filters(
			static::HOOK_PREFIX . static::BINDINGS_FILTER,
			static::BINDINGS
		);
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
	 * @return array Array of fully qualified class names.
	 */
	protected function get_shared_instances(): array {
		/**
		 * Filter the instances that are shared by default by the plugin.
		 *
		 * This can be used to turn objects that were added externally into
		 * shared instances.
		 *
		 * @param array<string> $shared_instances Array of FQCNs to turn into
		 *                                        shared objects..
		 */
		return (array) \apply_filters(
			static::HOOK_PREFIX . static::SHARED_INSTANCES_FILTER,
			static::SHARED_INSTANCES
		);
	}
}
