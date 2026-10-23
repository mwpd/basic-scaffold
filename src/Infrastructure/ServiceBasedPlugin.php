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
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjectorFactory;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\LifecycleScope;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkContext;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkSelection;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteSelection;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SynchronousSiteRunner;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\SimpleServiceContainer;
use MWPD\BasicScaffold\Exception\FailedToResolveDependencies;
use MWPD\BasicScaffold\Exception\InvalidArgument;
use MWPD\BasicScaffold\Exception\InvalidConfiguration;
use MWPD\BasicScaffold\Exception\InvalidService;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\LazilyInstantiatedService;
use Throwable;
use WP_Site;

/**
 * This abstract base plugin provides all the boilerplate code for working with
 * the dependency injector and the service container.
 */
abstract class ServiceBasedPlugin implements Plugin, PluginLifecycle {

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
	 * Scoped lifecycle interfaces that replace the parameterless one of the
	 * same event. A service implementing any of them no longer receives the
	 * parameterless call for that event, in any scope.
	 */
	private const SCOPED_LIFECYCLE_INTERFACES = [
		Activateable::class   => [ SiteActivateable::class, NetworkActivateable::class ],
		Deactivateable::class => [ SiteDeactivateable::class, NetworkDeactivateable::class ],
	];

	/**
	 * Every scoped lifecycle interface.
	 */
	private const SCOPED_INTERFACES = [
		SiteActivateable::class,
		NetworkActivateable::class,
		SiteDeactivateable::class,
		NetworkDeactivateable::class,
		SiteInitializable::class,
		SiteUninitializable::class,
		SiteUninstallable::class,
		NetworkUninstallable::class,
	];

	/**
	 * Scoped lifecycle interfaces whose dispatch needs to know the plugin's
	 * main file: whether the plugin is network-active, or still active on a
	 * site, is recorded under it.
	 */
	private const INTERFACES_NEEDING_MAIN_FILE = [
		SiteDeactivateable::class,
		SiteInitializable::class,
	];

	/**
	 * Scoped lifecycle interfaces that WordPress only reaches through the
	 * uninstall callback.
	 */
	private const UNINSTALL_INTERFACES = [
		SiteUninstallable::class,
		NetworkUninstallable::class,
	];

	/**
	 * Priority on wp_initialize_site: after core sets the new site up at 10.
	 */
	private const SITE_INITIALIZATION_PRIORITY = 11;

	/**
	 * Priority on wp_uninitialize_site: before core drops the site's tables
	 * at 10.
	 */
	private const SITE_REMOVAL_PRIORITY = 5;

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
	 * Delayed services hooked to their registration action, whose callback
	 * has not run yet.
	 *
	 * Once the action has started, did_action() can no longer tell such a
	 * service from one that will never be registered: its callback may still
	 * be ahead, even at the priority the pending services are checked at.
	 *
	 * @var array<string,non-empty-string>
	 */
	private array $hooked_services = [];

	/**
	 * Whether register_services() has already run in this request.
	 */
	private bool $services_registered = false;

	/**
	 * Runner that visits the sites and networks of a scoped lifecycle event.
	 */
	private SiteRunner $site_runner;

	/**
	 * Factory for the fresh injector of each scoped lifecycle graph.
	 */
	private InjectorFactory $injector_factory;

	/**
	 * Whether the injector factory creates the kind of injector the plugin
	 * uses. It does unless a custom injector was passed in without a factory.
	 */
	private bool $injector_factory_matches;

	/**
	 * Whether this plugin instance only runs lifecycle work, as it does in
	 * the request that uninstalls it. Normal service registration is then
	 * skipped, even if plugins_loaded fires afterwards.
	 */
	private bool $lifecycle_only = false;

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
	 * @param SiteRunner|null       $site_runner       Optional. Runner that
	 *                                                 visits the sites of a
	 *                                                 multisite lifecycle
	 *                                                 event.
	 * @param InjectorFactory|null  $injector_factory  Optional. Factory for
	 *                                                 the fresh injector of
	 *                                                 each scoped lifecycle
	 *                                                 call. Required with a
	 *                                                 custom injector once a
	 *                                                 service implements a
	 *                                                 scoped lifecycle
	 *                                                 interface.
	 */
	public function __construct(
		bool $enable_filters = true,
		?Injector $injector = null,
		?ServiceContainer $service_container = null,
		?SiteRunner $site_runner = null,
		?InjectorFactory $injector_factory = null
	) {
		/*
		 * We use what is commonly referred to as a "poka-yoke" here.
		 *
		 * We need an injector and a container. We make them injectable so that
		 * we can easily provide overrides for testing, but we also make them
		 * optional and provide default implementations for easy regular usage.
		 * The same goes for the runner that visits the sites of a multisite
		 * lifecycle event, and for the factory that creates a fresh injector
		 * for each of them.
		 *
		 * The default factory creates SimpleInjector instances, so it only
		 * matches the default injector. A custom injector without a factory is
		 * remembered rather than rejected here: it only matters once a service
		 * implements a scoped lifecycle interface.
		 *
		 * The constructor only stores them. The injector is configured when the
		 * services are registered, see register_services().
		 */

		$this->enable_filters           = $enable_filters;
		$this->injector                 = $injector ?? new SimpleInjector();
		$this->service_container        = $service_container ?? new SimpleServiceContainer();
		$this->site_runner              = $site_runner ?? new SynchronousSiteRunner();
		$this->injector_factory         = $injector_factory ?? new SimpleInjectorFactory();
		$this->injector_factory_matches = null === $injector || null !== $injector_factory;
	}

	/**
	 * Activate the plugin in the current site.
	 *
	 * @throws InvalidConfiguration If the lifecycle configuration is invalid.
	 * @throws InvalidService If a service is not valid.
	 */
	public function activate(): void {
		$this->activate_in( LifecycleScope::current_site() );
	}

	/**
	 * Deactivate the plugin in the current site.
	 *
	 * @throws InvalidConfiguration If the lifecycle configuration is invalid.
	 * @throws InvalidService If a service is not valid.
	 */
	public function deactivate(): void {
		$this->deactivate_in( LifecycleScope::current_site() );
	}

	/**
	 * Activate the plugin in the current site, or in the whole current network.
	 *
	 * The uninstall callback is registered first, before any service sets
	 * anything up, so that an activation that fails part-way can still be
	 * uninstalled. Then the services are called in three phases:
	 *
	 * 1. Every service implementing Activateable, and none of the scoped
	 *    activation interfaces, is activated once, in the current site, as
	 *    get_lifecycle_services() describes.
	 * 2. On a network activation, every NetworkActivateable service is called
	 *    once, for the current network.
	 * 3. Every SiteActivateable service is called in the current site, or on a
	 *    network activation in every site of the network.
	 *
	 * Phases 2 and 3 build a fresh object graph in each site and network, see
	 * dispatch_in_fresh_graph(). The first exception stops the activation and
	 * propagates; what already ran is not undone. The rewrite rules are flushed
	 * once afterwards, in the current site, so a service that adds rewrite
	 * rules registers them in its activation method itself.
	 *
	 * @param LifecycleScope $scope The current site, or the current network.
	 *
	 * @throws InvalidConfiguration If the lifecycle configuration is invalid.
	 * @throws InvalidService If a service is not valid.
	 */
	public function activate_in( LifecycleScope $scope ): void {
		$this->register_services();
		$this->register_uninstall_callback();

		foreach ( $this->get_lifecycle_services( Activateable::class ) as $service ) {
			if ( $service instanceof Activateable ) {
				$service->activate();
			}
		}

		if ( $scope->is_network_wide() && $this->declares( NetworkActivateable::class ) ) {
			$this->site_runner->run_on_networks(
				NetworkSelection::current(),
				function ( NetworkId $network_id ): void {
					$context = NetworkContext::for_network( $network_id );

					$this->dispatch_in_fresh_graph(
						NetworkActivateable::class,
						static function ( NetworkActivateable $service ) use ( $context ): void {
							$service->activate_network( $context );
						}
					);
				}
			);
		}

		if ( $this->declares( SiteActivateable::class ) ) {
			$this->site_runner->run_on_sites(
				$this->select_sites( $scope ),
				function ( SiteId $site_id, NetworkId $network_id ) use ( $scope ): void {
					$context = SiteContext::for_site( $site_id, $network_id, $scope );

					$this->dispatch_in_fresh_graph(
						SiteActivateable::class,
						static function ( SiteActivateable $service ) use ( $context ): void {
							$service->activate_site( $context );
						}
					);
				}
			);
		}

		$this->request_soft_rewrite_flush();
	}

	/**
	 * Deactivate the plugin in the current site, or in the whole current
	 * network.
	 *
	 * The services are called in the reverse order of activation:
	 *
	 * 1. Every SiteDeactivateable service is called in the current site, or on
	 *    a network deactivation in every site of the network. A network
	 *    deactivation skips a site that still has the plugin activated on its
	 *    own, and a deactivation on one site tears nothing down while the
	 *    plugin stays network-active.
	 * 2. On a network deactivation, every NetworkDeactivateable service is
	 *    called once, for the current network.
	 * 3. Every service implementing Deactivateable, and none of the scoped
	 *    deactivation interfaces, is deactivated once, in the current site.
	 *
	 * The first exception stops the deactivation and propagates. The rewrite
	 * rules are flushed once afterwards, in the current site.
	 *
	 * @param LifecycleScope $scope The current site, or the current network.
	 *
	 * @throws InvalidConfiguration If the lifecycle configuration is invalid.
	 * @throws InvalidService If a service is not valid.
	 */
	public function deactivate_in( LifecycleScope $scope ): void {
		$this->register_services();

		if ( $this->declares( SiteDeactivateable::class ) ) {
			$this->site_runner->run_on_sites(
				$this->select_sites( $scope ),
				function ( SiteId $site_id, NetworkId $network_id ) use ( $scope ): void {
					// The plugin stays active where it was activated on its own
					// (network deactivation) or for the whole network (a
					// deactivation on one site), so nothing is torn down there.
					$stays_active = $scope->is_network_wide()
						? $this->is_active_on_current_site()
						: $this->is_network_active( $network_id );

					if ( $stays_active ) {
						return;
					}

					$context = SiteContext::for_site( $site_id, $network_id, $scope );

					$this->dispatch_in_fresh_graph(
						SiteDeactivateable::class,
						static function ( SiteDeactivateable $service ) use ( $context ): void {
							$service->deactivate_site( $context );
						}
					);
				}
			);
		}

		if ( $scope->is_network_wide() && $this->declares( NetworkDeactivateable::class ) ) {
			$this->site_runner->run_on_networks(
				NetworkSelection::current(),
				function ( NetworkId $network_id ): void {
					$context = NetworkContext::for_network( $network_id );

					$this->dispatch_in_fresh_graph(
						NetworkDeactivateable::class,
						static function ( NetworkDeactivateable $service ) use ( $context ): void {
							$service->deactivate_network( $context );
						}
					);
				}
			);
		}

		foreach ( $this->get_lifecycle_services( Deactivateable::class ) as $service ) {
			if ( $service instanceof Deactivateable ) {
				$service->deactivate();
			}
		}

		$this->request_soft_rewrite_flush();
	}

	/**
	 * Uninstall the plugin.
	 *
	 * WordPress calls this through the static uninstall bridge, in a request
	 * where the plugin is not active, after including the main plugin file.
	 * This instance then only runs lifecycle work: normal service registration
	 * is skipped, even if plugins_loaded fires afterwards, and no service is
	 * built that does not take part in the uninstall.
	 *
	 * On a single site, every SiteUninstallable service is called in the
	 * current site. On multisite, every site of every network is visited,
	 * whether the plugin was ever active there or not, and then every
	 * NetworkUninstallable service is called once for each network. Each call
	 * gets a fresh object graph, built in the site it runs in, so a Conditional
	 * service is asked there whether it is needed. Once a network is done, the
	 * copy of the uninstall callback that activation registered on its main
	 * site is removed.
	 *
	 * The first exception stops the uninstall. Before it propagates, the
	 * uninstall callback is registered again on the current site, where
	 * WordPress removed it before calling it, so that deleting the plugin again
	 * retries the cleanup. Cleanup that already ran is not undone; it must be
	 * safe to run again. If registering the callback again fails as well, the
	 * original exception still propagates, with that failure at the end of its
	 * chain of previous exceptions.
	 *
	 * @throws Throwable The first failure of the uninstall, unchanged.
	 */
	public function uninstall(): void {
		$this->lifecycle_only = true;

		try {
			if ( \is_multisite() ) {
				$this->site_runner->run_on_networks(
					NetworkSelection::all(),
					function ( NetworkId $network_id ): void {
						$this->uninstall_sites( SiteSelection::network( $network_id ), LifecycleScope::network_wide() );

						$context = NetworkContext::for_network( $network_id );

						$this->dispatch_in_fresh_graph(
							NetworkUninstallable::class,
							static function ( NetworkUninstallable $service ) use ( $context ): void {
								$service->uninstall_network( $context );
							}
						);

						$this->remove_uninstall_callback();
					}
				);
			} else {
				$this->uninstall_sites( SiteSelection::current(), LifecycleScope::current_site() );
			}
		} catch ( Throwable $failure ) {
			try {
				$this->register_uninstall_callback_in_current_site();
			} finally {
				// Throwing here keeps the original failure in front, even when
				// registering the callback again failed too.
				throw $failure;
			}
		}
	}

	/**
	 * Call every SiteUninstallable service in each selected site.
	 *
	 * @param SiteSelection  $sites Sites to clean up.
	 * @param LifecycleScope $scope Scope of the uninstall.
	 */
	private function uninstall_sites( SiteSelection $sites, LifecycleScope $scope ): void {
		$this->site_runner->run_on_sites(
			$sites,
			function ( SiteId $site_id, NetworkId $network_id ) use ( $scope ): void {
				$context = SiteContext::for_site( $site_id, $network_id, $scope );

				$this->dispatch_in_fresh_graph(
					SiteUninstallable::class,
					static function ( SiteUninstallable $service ) use ( $context ): void {
						$service->uninstall_site( $context );
					}
				);
			}
		);
	}

	/**
	 * Select the sites an activation or deactivation reaches.
	 *
	 * @param LifecycleScope $scope The current site, or the current network.
	 */
	private function select_sites( LifecycleScope $scope ): SiteSelection {
		if ( ! $scope->is_network_wide() ) {
			return SiteSelection::current();
		}

		return SiteSelection::network( NetworkId::from_wordpress( \get_current_network_id() ) );
	}

	/**
	 * Check whether a configured service implements a lifecycle interface.
	 *
	 * The decision is made by class, without building anything, from the
	 * services resolved in the current site.
	 *
	 * @param class-string ...$interfaces Lifecycle interfaces to look for.
	 */
	private function declares( string ...$interfaces ): bool {
		return null !== $this->find_declaring_interface( $this->services, $interfaces );
	}

	/**
	 * Find the first of some interfaces that a service implements.
	 *
	 * @param array<string,class-string> $services   Services to look through.
	 * @param array<class-string>        $interfaces Interfaces to look for.
	 *
	 * @return class-string|null An interface that is implemented, or null.
	 */
	private function find_declaring_interface( array $services, array $interfaces ): ?string {
		foreach ( $interfaces as $interface ) {
			foreach ( $services as $class_name ) {
				if ( is_a( $class_name, $interface, true ) ) {
					return $interface;
				}
			}
		}

		return null;
	}

	/**
	 * Build a fresh object graph in the current site and call every eligible
	 * service implementing a scoped lifecycle interface.
	 *
	 * The graph starts from an empty injector from the injector factory. The
	 * composition root's injector configuration and services are applied to
	 * it here, with their filters, so they see the site that is switched to.
	 * Each declared service is decided by class: one that does not implement
	 * the interface is skipped, and a Conditional one that is not needed in
	 * this site is skipped without being built. The others are made through
	 * the graph's injector and called, in the order they are configured.
	 *
	 * Nothing of the graph goes into the plugin's container, no service is
	 * registered, and the graph is dropped afterwards, so neither the services
	 * nor their shared collaborators carry anything from one site into the
	 * next. Callable declarations in the configuration run for each graph and
	 * receive its injector; they should only compute class names.
	 *
	 * @template T of object
	 *
	 * @param string   $capability Scoped lifecycle interface.
	 * @param callable $call       Lifecycle call to make on each service.
	 *
	 * @phpstan-param class-string<T>  $capability
	 * @phpstan-param callable(T):void $call
	 *
	 * @throws InvalidConfiguration If the configuration is invalid, or a custom
	 *                              injector has no matching factory.
	 * @throws InvalidService If a service is not valid.
	 */
	private function dispatch_in_fresh_graph( string $capability, callable $call ): void {
		if ( ! $this->injector_factory_matches ) {
			throw InvalidConfiguration::from_missing_injector_factory( \get_class( $this->injector ) );
		}

		$plugin_injector = $this->injector;

		try {
			/*
			 * The configuration methods and resolvers of the composition root
			 * read the injector from the property, as they do during
			 * registration. It points at the graph's injector while the graph
			 * is built and its services are called, and at the plugin's own
			 * injector again afterwards, whatever happens.
			 */
			$this->injector = $this->injector_factory->create();
			$this->injector = $this->configure_injector( $this->injector );

			$graph_injector = $this->injector;
			$graph_injector = $graph_injector->delegate(
				Injector::class,
				static function () use ( &$graph_injector ): Injector {
					return $graph_injector;
				}
			);

			$this->injector = $graph_injector;

			foreach ( $this->resolve_services() as $class_name ) {
				if ( ! is_a( $class_name, $capability, true ) ) {
					continue;
				}

				if ( ! $this->is_needed( $class_name ) ) {
					continue;
				}

				$service = $graph_injector->make( $class_name );

				if ( ! $service instanceof Service || ! $service instanceof $capability ) {
					throw InvalidService::from_service( $service );
				}

				$call( $service );
			}
		} finally {
			$this->injector = $plugin_injector;
		}
	}

	/**
	 * Check whether a service is needed in the current site.
	 *
	 * @param class-string $class_name Service class.
	 */
	private function is_needed( string $class_name ): bool {
		return ! is_a( $class_name, Conditional::class, true ) || $class_name::is_needed();
	}

	/**
	 * Register the uninstall callback where WordPress looks for it.
	 *
	 * WordPress reads the callback from the site the plugin is deleted in,
	 * which for a network admin is the main site of its network. So it is
	 * registered on the current site and, on multisite, on the main site of
	 * every network. register_uninstall_hook() only writes when the stored
	 * callback differs, so registering again changes nothing.
	 *
	 * Networks added later, or a network whose main site changes, get the
	 * callback at the next activation.
	 *
	 * @throws InvalidConfiguration If the uninstall configuration is invalid.
	 */
	private function register_uninstall_callback(): void {
		if ( null === $this->get_valid_uninstall_callback() ) {
			return;
		}

		$this->register_uninstall_callback_in_current_site();

		if ( ! \is_multisite() ) {
			return;
		}

		$activating_site = \get_current_blog_id();

		$this->site_runner->run_on_networks(
			NetworkSelection::all(),
			function () use ( $activating_site ): void {
				if ( \get_current_blog_id() !== $activating_site ) {
					$this->register_uninstall_callback_in_current_site();
				}
			}
		);
	}

	/**
	 * Register the uninstall callback in the current site.
	 *
	 * @throws InvalidConfiguration If the uninstall configuration is invalid.
	 */
	private function register_uninstall_callback_in_current_site(): void {
		$callback = $this->get_valid_uninstall_callback();

		if ( null === $callback ) {
			return;
		}

		\register_uninstall_hook( $this->get_valid_main_file( 'The uninstall callback' ), $callback );
	}

	/**
	 * Remove the copy of the uninstall callback that activation registered in
	 * the current site, if there is one.
	 *
	 * @throws InvalidConfiguration If the main file is not configured.
	 */
	private function remove_uninstall_callback(): void {
		$basename  = \plugin_basename( $this->get_valid_main_file( 'The uninstall callback' ) );
		$callbacks = \get_option( 'uninstall_plugins' );

		if ( ! is_array( $callbacks ) || ! array_key_exists( $basename, $callbacks ) ) {
			return;
		}

		unset( $callbacks[ $basename ] );
		\update_option( 'uninstall_plugins', $callbacks );
	}

	/**
	 * Check whether the plugin is network-active on a network.
	 *
	 * The option is read by network ID, because switching sites does not
	 * switch networks.
	 *
	 * @param NetworkId $network_id Network to check.
	 *
	 * @throws InvalidConfiguration If the main file is not configured.
	 */
	private function is_network_active( NetworkId $network_id ): bool {
		if ( ! \is_multisite() ) {
			return false;
		}

		$basename = \plugin_basename( $this->get_valid_main_file( SiteInitializable::class ) );
		$active   = \get_network_option( $network_id->to_int(), 'active_sitewide_plugins', [] );

		return is_array( $active ) && array_key_exists( $basename, $active );
	}

	/**
	 * Check whether the plugin is activated on the current site on its own.
	 *
	 * @throws InvalidConfiguration If the main file is not configured.
	 */
	private function is_active_on_current_site(): bool {
		$basename = \plugin_basename( $this->get_valid_main_file( SiteDeactivateable::class ) );
		$active   = \get_option( 'active_plugins', [] );

		return is_array( $active ) && in_array( $basename, $active, true );
	}

	/**
	 * Get the configured main plugin file, which a scoped lifecycle dispatch
	 * needs.
	 *
	 * @param string $needed_by What needs the main file, for the message.
	 *
	 * @throws InvalidConfiguration If the main file is not configured.
	 *
	 * @return non-empty-string Main plugin file.
	 */
	private function get_valid_main_file( string $needed_by ): string {
		$main_file = $this->get_main_file();

		if ( '' === $main_file ) {
			throw InvalidConfiguration::from_missing_main_file( $needed_by );
		}

		return $main_file;
	}

	/**
	 * Get the configured uninstall callback, after checking that WordPress
	 * can store and call it.
	 *
	 * @throws InvalidConfiguration If the callback is not a public static
	 *                              method named by two strings.
	 *
	 * @return callable|null Uninstall callback, or null if none is configured.
	 */
	private function get_valid_uninstall_callback(): ?callable {
		$callback = $this->get_uninstall_callback();

		return null === $callback ? null : $this->validate_uninstall_callback( $callback );
	}

	/**
	 * Check that an uninstall callback is a public static method named by two
	 * strings.
	 *
	 * The configuration comes from an overridable method, so its shape is
	 * checked rather than trusted. Called from here, is_callable() rejects a
	 * method that is not public, and on PHP 8 one that is not static.
	 *
	 * @param mixed $callback Configured uninstall callback.
	 *
	 * @throws InvalidConfiguration If the callback has another shape.
	 *
	 * @return callable Uninstall callback.
	 */
	private function validate_uninstall_callback( $callback ): callable {
		if ( ! is_array( $callback ) || 2 !== count( $callback ) || ! isset( $callback[0], $callback[1] )
			|| ! is_string( $callback[0] ) || ! is_string( $callback[1] )
			|| ! is_callable( $callback ) ) {
			throw InvalidConfiguration::from_invalid_uninstall_callback( $callback );
		}

		return $callback;
	}

	/**
	 * Check, by class, that the scoped lifecycle services have what they need.
	 *
	 * This runs while the services are registered, in every request, so a
	 * missing setting is reported during development rather than when a site
	 * is created or the plugin is deleted. It only compares classes and
	 * settings and loads nothing; the shape of the uninstall callback is
	 * checked when activation registers it.
	 *
	 * @param array<string,class-string> $services Validated services.
	 *
	 * @throws InvalidConfiguration If a setting a scoped service needs is missing.
	 */
	private function check_lifecycle_configuration( array $services ): void {
		$scoped = $this->find_declaring_interface( $services, self::SCOPED_INTERFACES );

		if ( null !== $scoped && ! $this->injector_factory_matches ) {
			throw InvalidConfiguration::from_missing_injector_factory( \get_class( $this->injector ) );
		}

		$needing_main_file = $this->find_declaring_interface( $services, self::INTERFACES_NEEDING_MAIN_FILE );

		if ( null !== $needing_main_file ) {
			$this->get_valid_main_file( $needing_main_file );
		}

		$uninstall = $this->find_declaring_interface( $services, self::UNINSTALL_INTERFACES );

		if ( null !== $uninstall && null === $this->get_uninstall_callback() ) {
			throw InvalidConfiguration::from_missing_uninstall_callback( $uninstall );
		}
	}

	/**
	 * Hook the site initialization and removal dispatch into WordPress.
	 *
	 * Only on multisite, and only when a service needs it. These callbacks
	 * only run in a request where the plugin is loaded: a site created from
	 * another network, or deleted from the network admin while the plugin is
	 * only active on that site, is not reached.
	 *
	 * @param array<string,class-string> $services Validated services.
	 */
	private function hook_site_lifecycle( array $services ): void {
		$initializes   = null !== $this->find_declaring_interface( $services, [ SiteInitializable::class ] );
		$uninitializes = null !== $this->find_declaring_interface( $services, [ SiteUninitializable::class ] );

		if ( ( ! $initializes && ! $uninitializes ) || ! \is_multisite() ) {
			return;
		}

		if ( $initializes ) {
			\add_action(
				'wp_initialize_site',
				function ( $site ): void {
					$this->initialize_site( $site );
				},
				self::SITE_INITIALIZATION_PRIORITY,
				1
			);
		}

		if ( $uninitializes ) {
			\add_action(
				'wp_uninitialize_site',
				function ( $site ): void {
					$this->uninitialize_site( $site );
				},
				self::SITE_REMOVAL_PRIORITY,
				1
			);
		}
	}

	/**
	 * Set up a site that was just created, if the plugin is network-active on
	 * its network and core set the site up successfully.
	 *
	 * @param mixed $site Site WordPress handed over.
	 *
	 * @throws InvalidArgument If WordPress handed over something other than a site.
	 * @throws InvalidConfiguration If the configuration is invalid.
	 * @throws InvalidService If a service is not valid.
	 */
	private function initialize_site( $site ): void {
		[ $site_id, $network_id ] = $this->normalize_site( $site );

		if ( ! \wp_is_site_initialized( $site_id->to_int() ) || ! $this->is_network_active( $network_id ) ) {
			return;
		}

		$this->site_runner->run_on_sites(
			SiteSelection::site( $site_id, $network_id ),
			function ( SiteId $site_id, NetworkId $network_id ): void {
				$context = SiteContext::for_site( $site_id, $network_id, LifecycleScope::network_wide() );

				$this->dispatch_in_fresh_graph(
					SiteInitializable::class,
					static function ( SiteInitializable $service ) use ( $context ): void {
						$service->initialize_site( $context );
					}
				);
			}
		);
	}

	/**
	 * Clean up a site that is about to be deleted, whether or not the plugin
	 * is still active on it: data from an earlier activation may be left.
	 *
	 * @param mixed $site Site WordPress handed over.
	 *
	 * @throws InvalidArgument If WordPress handed over something other than a site.
	 * @throws InvalidConfiguration If the configuration is invalid.
	 * @throws InvalidService If a service is not valid.
	 */
	private function uninitialize_site( $site ): void {
		[ $site_id, $network_id ] = $this->normalize_site( $site );

		$this->site_runner->run_on_sites(
			SiteSelection::site( $site_id, $network_id ),
			function ( SiteId $site_id, NetworkId $network_id ): void {
				$context = SiteContext::for_site( $site_id, $network_id, LifecycleScope::current_site() );

				$this->dispatch_in_fresh_graph(
					SiteUninitializable::class,
					static function ( SiteUninitializable $service ) use ( $context ): void {
						$service->uninitialize_site( $context );
					}
				);
			}
		);
	}

	/**
	 * Turn the site WordPress hands over into the IDs of the site and its
	 * network (principle 9).
	 *
	 * @param mixed $site Site WordPress handed over.
	 *
	 * @throws InvalidArgument If it is not a site.
	 *
	 * @return array{SiteId, NetworkId} Site and network.
	 */
	private function normalize_site( $site ): array {
		if ( ! $site instanceof WP_Site ) {
			throw InvalidArgument::from_site( $site );
		}

		return [ SiteId::from_wordpress( $site->blog_id ), NetworkId::from_wordpress( $site->site_id ) ];
	}

	/**
	 * Get the services that take part in a lifecycle event, one at a time.
	 *
	 * The container alone is not enough to find them. A service that is not
	 * Registerable is stored lazily, so the container holds a wrapper that
	 * implements no lifecycle interface. A Delayed service, or one waiting for
	 * a delayed dependency, is not in the container at all until its action
	 * fires, which may well not happen in the request that activates or
	 * deactivates the plugin.
	 *
	 * So the decision is made by class, before anything is built:
	 *
	 * 1. A service that does not implement the lifecycle interface is skipped,
	 *    and a lazy one stays unbuilt. So is a service that implements one of
	 *    the scoped interfaces of the same event, such as SiteActivateable or
	 *    NetworkActivateable for activation: those receive the scoped calls
	 *    instead, in every scope, so setup and cleanup never run twice.
	 * 2. A Conditional service that is not needed in this request is skipped
	 *    without being built. Lifecycle work that has to run regardless of the
	 *    condition belongs in a separate service without it.
	 * 3. A service already in the container takes part with that instance.
	 * 4. Any other service is made by the injector just for the lifecycle
	 *    call, with its usual constructor collaborators. It is not put into the
	 *    container and not registered: its register() still runs at its own
	 *    registration action, on an instance of its own.
	 *
	 * The services come in the order they are configured in. HasDependencies
	 * only governs registration: it neither holds back nor reorders lifecycle
	 * calls, and no dependency is registered early for them. Lifecycle steps
	 * that must run in sequence belong in one service that orchestrates them.
	 *
	 * A container passed in already filled can hold services that are not
	 * configured here. Those take part afterwards if the instance stored for
	 * them implements the lifecycle interface and none of its scoped ones.
	 *
	 * @param class-string<Activateable|Deactivateable> $lifecycle_interface Activateable or Deactivateable.
	 *
	 * @throws InvalidService If a service is not valid.
	 *
	 * @return iterable<Service> Services taking part in the lifecycle event.
	 */
	private function get_lifecycle_services( string $lifecycle_interface ): iterable {
		$scoped_interfaces = self::SCOPED_LIFECYCLE_INTERFACES[ $lifecycle_interface ];

		foreach ( $this->services as $id => $class_name ) {
			if ( ! is_a( $class_name, $lifecycle_interface, true )
				|| null !== $this->find_declaring_interface( [ $id => $class_name ], $scoped_interfaces ) ) {
				continue;
			}

			if ( is_a( $class_name, Conditional::class, true ) && ! $class_name::is_needed() ) {
				continue;
			}

			yield $this->service_container->has( $id )
				? $this->service_container->get( $id )
				: $this->make_service( $class_name );
		}

		foreach ( $this->service_container as $id => $service ) {
			if ( isset( $this->services[ $id ] ) || ! $service instanceof $lifecycle_interface ) {
				continue;
			}

			if ( null === $this->find_declaring_interface( [ $id => get_class( $service ) ], $scoped_interfaces ) ) {
				yield $service;
			}
		}
	}

	/**
	 * Request a soft flush of the rewrite rules after a lifecycle event.
	 *
	 * A soft flush rebuilds the rewrite rules stored in the database, but does
	 * not rewrite server configuration such as .htaccess. A service that needs
	 * that calls \flush_rewrite_rules( true ) in its own activate() or
	 * deactivate(). WordPress itself postpones a flush requested before
	 * wp_loaded until then, so there is no need to schedule one here.
	 */
	private function request_soft_rewrite_flush(): void {
		\flush_rewrite_rules( false );
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
	 * All filtered declarations are resolved once, in declaration order, and
	 * validated before dependency checks or scheduling configured services.
	 * When updating a generated plugin, keep resolvers independent of service
	 * registration: they now all run first. A later invalid entry can therefore
	 * fail before an earlier unknown dependency. Injector setup and resolver
	 * side effects are not rolled back if validation fails.
	 *
	 * @throws InvalidConfiguration If the services or injector configuration is invalid.
	 * @throws InvalidService If a service is not valid.
	 */
	public function register_services(): void {
		/*
		 * Bail early so we don't configure the injector or schedule services
		 * twice (principle 11). Whether registration has run is tracked
		 * explicitly: the container cannot tell, because one passed into the
		 * constructor may hold entries before registration starts.
		 *
		 * The flag is set before any work, so a registration that throws
		 * part-way is not retried by a later call either: the exception is
		 * the report, and running the steps again on a half-configured
		 * injector would not make the plugin whole.
		 */
		if ( $this->services_registered || $this->lifecycle_only ) {
			return;
		}

		$this->services_registered = true;

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
		 * injector, whichever implementation was passed in, instead of a
		 * static service locator (principle 5). Set up after the composition
		 * root's configuration, it reaches every plugin and replaces any
		 * delegation for Injector. The callable reads the property, so it
		 * also holds for an injector whose methods return a configured copy.
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

		$services = $this->resolve_services();

		$this->check_lifecycle_configuration( $services );
		$this->hook_site_lifecycle( $services );

		$this->services         = [];
		$this->pending_services = [];

		// The first pass goes through the services in the order they were
		// provided, and sets aside those whose dependencies come later.
		foreach ( $services as $id => $class_name ) {
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
	 * Get the services of the plugin, filtered, resolved and validated.
	 *
	 * @throws InvalidConfiguration If the services configuration is invalid.
	 * @throws InvalidService If an identifier or resolved service is not valid.
	 *
	 * @return array<string,class-string> Validated services in declaration order.
	 */
	private function resolve_services(): array {
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

		return $this->normalize_services( $services );
	}

	/**
	 * Normalize the whole filtered map so dependency overrides never receive
	 * unchecked later entries (principle 9).
	 *
	 * @param mixed $services Filtered service declarations.
	 *
	 * @throws InvalidConfiguration If the services configuration is not an array.
	 * @throws InvalidService If an identifier or resolved service is not valid.
	 *
	 * @return array<string,class-string> Validated services in declaration order.
	 */
	private function normalize_services( $services ): array {
		if ( ! is_array( $services ) ) {
			throw InvalidConfiguration::from_invalid_services( $services );
		}

		$normalized = [];

		foreach ( $services as $id => $service ) {
			if ( ! is_string( $id ) ) {
				throw InvalidService::from_invalid_identifier( $id );
			}

			$class_name = $this->maybe_resolve( $service );

			if ( ! is_string( $class_name ) ) {
				throw InvalidService::from_invalid_class_name( $class_name );
			}

			// Fail here rather than when the service is first used: a service
			// that is not Registerable is only made once it is retrieved.
			if ( ! $this->is_known_type( $class_name ) ) {
				throw InvalidService::from_missing_class( $id, $class_name );
			}

			if ( ! is_a( $class_name, Service::class, true ) ) {
				throw InvalidService::from_non_service_class( $id, $class_name );
			}

			$normalized[ $id ] = $class_name;
		}

		return $normalized;
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

		if ( isset( $this->hooked_services[ $id ] ) ) {
			return $this->hooked_services[ $id ];
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
	 * registered upon their own registration action, at their own registration
	 * priority, even when their dependencies arrived earlier.
	 *
	 * A delayed service whose action has already run by the time it is
	 * scheduled is registered at once. That is the case when its dependencies
	 * were only ready later than its action, or than its priority on it: the
	 * service waited for them, and is registered where the pending services
	 * resume, at the end of the action they arrived on. Dependencies take
	 * precedence over the priority, and an action is never run again to make
	 * up for a priority that has passed.
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
				$registration_priority = $class_name::get_registration_priority();

				$this->hooked_services[ $id ] = $registration_action;

				\add_action(
					$registration_action,
					function () use ( $id, $class_name, $registration_priority ): void {
						unset( $this->hooked_services[ $id ] );
						$this->maybe_register_service( $id, $class_name );

						// At the last priority, this can run after the pending
						// services were checked on this action, so they are
						// checked again for what this service brings.
						if ( PHP_INT_MAX === $registration_priority && [] !== $this->pending_services ) {
							$this->register_pending_services();
						}
					},
					$registration_priority,
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
		return $this->make_service( $class_name );
	}

	/**
	 * Make a service through the injector right away.
	 *
	 * @param class-string $class_name Service class to make.
	 *
	 * @throws InvalidService If the injector does not return a service.
	 *
	 * @return Service Service that was made.
	 */
	private function make_service( string $class_name ): Service {
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
	 * @throws InvalidConfiguration If the injector configuration is invalid or binds Injector.
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
	 * @throws InvalidConfiguration If the bindings configuration is invalid or binds Injector.
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

			// A binding would redirect resolution away from the plugin's own
			// injector delegation, giving services a different injector.
			if ( $from === Injector::class ) {
				throw InvalidConfiguration::from_reserved_injector_binding( Injector::class );
			}

			/**
			 * The resolved values are guaranteed to be strings at this point.
			 *
			 * @var class-string $from
			 * @var class-string $to
			 */

			// Whatever is made for $from has to be usable as $from. That can
			// only be checked when both types can be loaded; otherwise the
			// injector reports the problem when it makes the instance.
			if ( $this->is_known_type( $from ) && $this->is_known_type( $to )
				&& ! is_a( $to, $from, true ) ) {
				throw InvalidConfiguration::from_incompatible_binding( $from, $to );
			}

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
				// Neither the $name nor the $value is resolved here. The name is
				// always literal, so an argument called 'key' or 'date' never
				// runs a function. The value may be a callable that the
				// constructor wants as-is.
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
	 * Check whether a name refers to a class or interface that can be loaded.
	 *
	 * @param string $name Name to check.
	 * @return bool Whether the class or interface exists.
	 */
	private function is_known_type( string $name ): bool {
		return class_exists( $name ) || interface_exists( $name );
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
	 * Get the main plugin file.
	 *
	 * WordPress records whether a plugin is active, and its uninstall
	 * callback, under the main file's basename. The scoped lifecycle dispatch
	 * needs it for SiteDeactivateable and SiteInitializable services and for
	 * the uninstall callback. An empty string, the default, is only accepted
	 * while nothing needs it.
	 *
	 * @return string Absolute path of the main plugin file, or an empty string.
	 */
	protected function get_main_file(): string {
		return '';
	}

	/**
	 * Get the callback WordPress calls to uninstall the plugin.
	 *
	 * WordPress stores the callback in the database and calls it statically,
	 * outside the object graph, so it has to be a public static method given
	 * as a pair of strings: a listed bridge that gets the plugin through the
	 * plugin factory and calls its uninstall() method. It is registered when
	 * the plugin is activated. Null, the default, is only accepted while no
	 * service implements SiteUninstallable or NetworkUninstallable.
	 *
	 * @return array{0: class-string, 1: non-empty-string}|null Uninstall callback, or null.
	 */
	protected function get_uninstall_callback(): ?array {
		return null;
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
	 * callables instead of direct values and will be evaluated when needed,
	 * with the injector and the service container as arguments.
	 *
	 * A deferred resolver can be a closure, a static method string
	 * ('Class::method'), a callable array ([ $object, 'method' ]) or an
	 * invokable object. A string that names a function is not a resolver: it
	 * is returned unchanged, so that a value like 'key' or 'date' cannot run
	 * the PHP function of the same name. To resolve a value through a
	 * function, wrap it in a closure.
	 *
	 * @param mixed $value Value to potentially resolve.
	 * @return mixed Resolved or unchanged value.
	 */
	protected function maybe_resolve( $value ) {
		// Configuration can come through filters, so a plain string that
		// happens to name a function stays a value (principle 9).
		if ( is_string( $value ) && function_exists( $value ) ) {
			return $value;
		}

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
	 * During registration, the entire map has already been validated and its
	 * resolvers have run, including entries after this service. Overrides in
	 * generated plugins can rely on class names here and in
	 * collect_missing_dependencies(), without resolving declarations again.
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
