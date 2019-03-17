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

namespace MWPD\BasicScaffold;

use MWPD\BasicScaffold\Infrastructure\{
	Activateable,
	Deactivateable,
	Injector,
	Registerable,
	Service,
	Injector\SimpleInjector,
	View\TemplatedViewFactory,
	ViewFactory
};

/**
 * The Plugin class is the composition root of the plugin.
 *
 * In here we assemble our infrastructure, configure it for the specific use
 * case the plugin is meant to solve and then kick off the services so that they
 * can hook themselves into the WordPress lifecycle.
 */
final class Plugin implements Registerable, Activateable, Deactivateable {

	// Main filters to control the flow of the plugin from outside code.
	public const SERVICES_FILTER         = 'mwpd.basic_scaffold.services';
	public const BINDINGS_FILTER         = 'mwpd.basic_scaffold.bindings';
	public const SHARED_INSTANCES_FILTER = 'mwpd.basic_scaffold.shared_instances';
	public const ARGUMENTS_FILTER        = 'mwpd.basic_scaffold.arguments';

	/** @var Injector */
	private $injector;

	/**
	 * Array of instantiated services.
	 *
	 * @var Service[]
	 */
	private $services = [];

	/**
	 * Instantiate a Plugin object.
	 *
	 * @param Injector|null $injector Optional. Injector instance to use.
	 */
	public function __construct( ?Injector $injector = null ) {
		$this->injector = $injector ?? new SimpleInjector();
		$this->injector = $this->configure_injector( $this->injector );
	}

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->register_services();

		foreach ( $this->services as $service ) {
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

		foreach ( $this->services as $service ) {
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
	 * @throws Exception\InvalidService If a service is not valid.
	 */
	public function register(): void {
		\add_action( 'plugins_loaded', [ $this, 'register_services' ] );
	}

	/**
	 * Register the individual services of this plugin.
	 *
	 * @throws Exception\InvalidService If a service is not valid.
	 */
	public function register_services() {
		// Bail early so we don't instantiate services twice.
		if ( ! empty( $this->services ) ) {
			return;
		}

		$this->services[ Injector::class ] = $this->injector;

		foreach ( $this->get_service_classes() as $class ) {
			$this->services[ $class ] = $this->instantiate_service( $class );
		}

		foreach ( $this->services as $service ) {
			if ( $service instanceof Registerable ) {
				$service->register();
			}
		}
	}

	/**
	 * Instantiate a single service.
	 *
	 * @param string $class Service class to instantiate.
	 *
	 * @return Service
	 * @throws Exception\InvalidService If the service is not valid.
	 */
	private function instantiate_service( $class ): Service {
		$service = $this->injector->share( $class )
		                          ->make( $class );

		if ( ! $service instanceof Service ) {
			throw Exception\InvalidService::from_service( $service );
		}

		return $service;
	}

	/**
	 * Configure the provided injector.
	 *
	 * @param Injector $injector Injector instance to configure.
	 * @return Injector Configured injector instance.
	 */
	private function configure_injector( Injector $injector ): Injector {
		$bindings = \apply_filters( self::BINDINGS_FILTER, [
			ViewFactory::class => TemplatedViewFactory::class,
		] );

		foreach ( $bindings as $from => $to ) {
			$injector = $injector->bind( $from, $to );
		}

		$shared_instances = \apply_filters( self::SHARED_INSTANCES_FILTER, [
			Injector::class,
		] );

		foreach ( $shared_instances as $shared_instance ) {
			$injector = $injector->share( $shared_instance );
		}

		return $injector;
	}

	/**
	 * Get the list of services to register.
	 *
	 * @return array<string> Array of fully qualified class names.
	 */
	private function get_service_classes(): array {
		return \apply_filters( self::SERVICES_FILTER, [
			// Add services as FQCNs here.
			ViewFactory::class,
			SampleService::class,
		] );
	}
}
