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

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey\Container;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\LifecycleScope;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkContext;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteId;
use MWPD\BasicScaffold\Infrastructure\NetworkActivateable;
use MWPD\BasicScaffold\Infrastructure\NetworkDeactivateable;
use MWPD\BasicScaffold\Infrastructure\NetworkUninstallable;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationReport;
use MWPD\BasicScaffold\Infrastructure\Registration\SimpleRegistrationLog;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;
use MWPD\BasicScaffold\Infrastructure\SiteDeactivateable;
use MWPD\BasicScaffold\Infrastructure\SiteInitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;
use MWPD\BasicScaffold\Tests\PHPStan\Rule\PluginNamespace;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 * Checks the promises a composition root makes about the services it
 * declares.
 *
 * Static rules see the code, not the object graph the plugin wires. These
 * checks build the real plugin and every service it declares, through the
 * plugin's own injector configuration:
 *
 * - every declaration is a Service, its registration action and
 *   dependencies make sense;
 * - the plugin can build every service from its own wiring;
 * - building a service changes no hooks, not even through a collaborator its
 *   constructor creates;
 * - running a service lifecycle step twice leaves the recorded state unchanged.
 *
 * They need Brain Monkey to be set up, and every WordPress function the
 * wiring calls to be stubbed, but no binding the composition root lacks.
 */
final class CompositionRootContract {

	/**
	 * Hook mutations to record directly, including lifecycle aliases left by
	 * the previous service's check.
	 */
	private const WATCHED_FUNCTIONS = [
		'add_action',
		'add_filter',
		'remove_action',
		'remove_filter',
		'register_activation_hook',
		'register_deactivation_hook',
	];

	/**
	 * Composition root to check.
	 *
	 * @var class-string<ServiceBasedPlugin>
	 */
	private string $plugin_class;

	/**
	 * Repository configuration, including the known registration-action order.
	 */
	private RepositoryLayout $layout;

	/**
	 * Instantiate a CompositionRootContract object.
	 *
	 * @param class-string<ServiceBasedPlugin> $plugin_class Composition root to check.
	 * @param RepositoryLayout|null           $layout       Repository configuration, or the plugin's defaults.
	 */
	public function __construct( string $plugin_class, ?RepositoryLayout $layout = null ) {
		$this->plugin_class = $plugin_class;
		$this->layout       = $layout ?? RepositoryLayout::of_plugin();
	}

	/**
	 * Check every service the composition root declares.
	 *
	 * @return array<string> Problems found.
	 */
	public function check(): array {
		// Composition roots keep the constructor of ServiceBasedPlugin, so the
		// injector it takes is the one the plugin configures.
		$plugin = ( new ReflectionClass( $this->plugin_class ) )->newInstance( true, new SimpleInjector() );

		// The plugin validates its injector configuration as it applies it.
		// Without a configured injector, no service can be checked.
		try {
			$injector = $this->configure_injector( $plugin );
		} catch ( Throwable $throwable ) {
			return [
				sprintf(
					'[%1$s] The injector configuration of %2$s is invalid: %3$s Correct get_bindings(),'
					. ' get_arguments(), get_shared_instances() or get_delegations(). %4$s.',
					RuleManifest::COMPOSITION_ROOT_CONTRACTS,
					$this->plugin_class,
					$throwable->getMessage(),
					RuleManifest::get_explanation( RuleManifest::COMPOSITION_ROOT_CONTRACTS )
				),
			];
		}

		$declared = self::call( $plugin, 'get_service_classes' );
		$services = is_array( $declared ) ? $declared : [];
		$problems = [];
		$resolved = [];

		foreach ( $services as $id => $declaration ) {
			$id = (string) $id;

			// Resolving a callable declaration may build objects too, so the
			// watch starts before it and lasts through the construction.
			$calls  = [];
			$before = $this->start_watching( $calls );

			try {
				// The plugin resolves every declaration, so a callable given as
				// a string, such as 'Factory::service_class', resolves too.
				$class_name = self::call( $plugin, 'maybe_resolve', $declaration );
			} catch ( Throwable $throwable ) {
				$problems[] = $this->problem(
					$id,
					is_string( $declaration ) ? $declaration : 'callable',
					sprintf(
						'cannot be resolved: %s Make the declaration a class name, or a callable that returns one'
						. ' without needing anything the composition root does not provide.',
						$throwable->getMessage()
					)
				);
				continue;
			}

			if ( ! is_string( $class_name )
				|| ! class_exists( $class_name )
				|| ! is_a( $class_name, Service::class, true ) ) {
				$problems[] = $this->problem(
					$id,
					is_string( $class_name ) ? $class_name : gettype( $class_name ),
					'is not a class implementing Service. Declare a class name, or a callable that returns one,'
					. ' of a class that implements Service.'
				);
				continue;
			}

			$resolved[ $id ] = $class_name;
			$problems        = array_merge(
				$problems,
				$this->check_capabilities( $id, $class_name, array_keys( $services ) ),
				$this->check_construction( $id, $class_name, $injector, $calls, $before )
			);
		}

		return array_merge( $problems, $this->check_dependency_graph( $resolved ) );
	}

	/**
	 * Check dependency cycles and the registration order of resolved services.
	 *
	 * @param array<string, class-string<Service>> $services Resolved declarations.
	 * @return array<string> Problems found.
	 */
	private function check_dependency_graph( array $services ): array {
		$graph   = [];
		$actions = [];
		foreach ( $services as $id => $class_name ) {
			$dependencies = is_a( $class_name, HasDependencies::class, true ) ? $class_name::get_dependencies() : [];
			// Missing or invalid declarations already have their own diagnostic.
			$graph[ $id ]   = array_values( array_unique( array_intersect( $dependencies, array_keys( $services ) ) ) );
			$actions[ $id ] = is_a( $class_name, Delayed::class, true )
				? $class_name::get_registration_action()
				: 'plugins_loaded';
		}

		$order    = array_flip( $this->layout->get_registration_actions() );
		$problems = [];
		foreach ( $graph as $id => $dependencies ) {
			$id = strval( $id );
			foreach ( $this->find_cycles( $id, $graph, [ $id ] ) as $cycle ) {
				$problems[] = $this->problem(
					$id,
					$services[ $id ],
					'has a dependency cycle: ' . implode( ' -> ', $cycle )
					. '. Remove a dependency from the cycle so services can register.'
				);
			}
			foreach ( $dependencies as $dependency ) {
				$action          = $actions[ $id ];
				$dependency_hook = $actions[ $dependency ];
				// Action validity is checked separately; unordered actions cannot rank an edge.
				if ( ! isset( $order[ $action ], $order[ $dependency_hook ] )
					|| $order[ $action ] >= $order[ $dependency_hook ] ) {
					continue;
				}
				$problems[] = $this->problem(
					$id,
					$services[ $id ],
					sprintf(
						'registers at "%s" before its dependency "%s" at "%s".'
						. ' Delay this service until the dependency\'s action or later,'
						. ' or register the dependency earlier.',
						$action,
						$dependency,
						$dependency_hook
					)
				);
			}
		}

		return $problems;
	}

	/**
	 * Find each simple directed cycle from its lexically smallest identifier.
	 *
	 * Starting only at that identifier avoids reporting rotations of a cycle.
	 * Keeping the path local preserves distinct cycles that share services.
	 *
	 * @param string                      $start Starting service identifier.
	 * @param array<string, list<string>> $graph Declared dependency edges.
	 * @param non-empty-list<string>      $path  Path from the starting service.
	 * @return list<list<string>> Cycles, each closed by its starting identifier.
	 */
	private function find_cycles( string $start, array $graph, array $path ): array {
		$cycles = [];
		foreach ( $graph[ $path[ count( $path ) - 1 ] ] as $dependency ) {
			if ( $dependency === $start ) {
				$cycles[] = array_merge( $path, [ $start ] );
				continue;
			}
			if ( strcmp( $dependency, $start ) < 0 || in_array( $dependency, $path, true ) ) {
				continue;
			}
			$cycles = array_merge(
				$cycles,
				$this->find_cycles( $start, $graph, array_merge( $path, [ $dependency ] ) )
			);
		}

		return $cycles;
	}

	/**
	 * Check that the composition root declares every concrete subsystem service.
	 *
	 * @param RepositoryLayout $layout Repository whose services to discover.
	 * @return array<string> Problems found.
	 */
	public function check_services_declared( RepositoryLayout $layout ): array {
		$plugin = ( new ReflectionClass( $this->plugin_class ) )->newInstance( true, new SimpleInjector() );
		$this->configure_injector( $plugin );
		$declarations = self::call( $plugin, 'get_service_classes' );
		$declared     = [];
		foreach ( is_array( $declarations ) ? $declarations : [] as $declaration ) {
			try {
				$class = self::call( $plugin, 'maybe_resolve', $declaration );
				if ( is_string( $class ) ) {
					$declared[] = PluginNamespace::normalize( $class );
				}
			} catch ( Throwable $throwable ) {
				// check() reports malformed or failing declarations with the fix.
				continue;
			}
		}
		$built_elsewhere = array_map(
			[ PluginNamespace::class, 'normalize' ],
			array_keys(
				array_filter(
					$layout->get_services_built_elsewhere(),
					static fn( string $route ): bool => trim( $route ) !== ''
				)
			)
		);
		$problems        = [];
		foreach ( $layout->get_subsystem_declarations() as $class ) {
			if ( ! class_exists( $class ) || ! is_a( $class, Service::class, true )
				|| ( new ReflectionClass( $class ) )->isAbstract()
				|| in_array( PluginNamespace::normalize( $class ), $declared, true )
				|| in_array( PluginNamespace::normalize( $class ), $built_elsewhere, true ) ) {
				continue;
			}
			$problems[] = sprintf(
				'[%s] Service %s is not declared in %s. Declare it in get_service_classes(),'
				. ' or list it in RepositoryLayout::SERVICES_BUILT_ELSEWHERE with the route that builds it. %s.',
				RuleManifest::SERVICES_DECLARED,
				$class,
				$this->plugin_class,
				RuleManifest::get_explanation( RuleManifest::SERVICES_DECLARED )
			);
		}

		return $problems;
	}

	/**
	 * Check the answers a service gives before it is built.
	 *
	 * @param string                $id         Identifier of the service.
	 * @param class-string<Service> $class_name Class of the service.
	 * @param array<int|string>     $declared   Identifiers of all services.
	 * @return array<string> Problems found.
	 */
	private function check_capabilities( string $id, string $class_name, array $declared ): array {
		$problems = [];

		if ( is_a( $class_name, Delayed::class, true ) && trim( $class_name::get_registration_action() ) === '' ) {
			$problems[] = $this->problem(
				$id,
				$class_name,
				'is Delayed, but get_registration_action() returns no action. Return the action whose firing the'
				. ' service has to wait for, such as "init" or "wp".'
			);
		} elseif ( is_a( $class_name, Delayed::class, true ) ) {
			$problems = array_merge( $problems, $this->check_registration_action( $id, $class_name ) );
		}

		if ( is_a( $class_name, HasDependencies::class, true ) ) {
			foreach ( $class_name::get_dependencies() as $dependency ) {
				if ( ! in_array( $dependency, $declared, true ) ) {
					$problems[] = $this->problem(
						$id,
						$class_name,
						sprintf(
							'depends on "%s", which the composition root does not declare. Name the identifier of a'
							. ' service in get_service_classes(), or declare the missing service.',
							$dependency
						)
					);
				}
			}
		}

		return $problems;
	}

	/**
	 * Check a nonempty action against core names and the root's own hook prefix.
	 *
	 * @param string                        $id         Service identifier.
	 * @param class-string<Service&Delayed> $class_name Delayed service class.
	 * @return array<string> Problems found.
	 */
	private function check_registration_action( string $id, string $class_name ): array {
		$action = $class_name::get_registration_action();
		$known  = array_merge(
			$this->layout->get_registration_actions(),
			$this->layout->get_additional_core_actions()
		);
		// Reading the protected constant follows a generated plugin's renaming.
		$prefix = ( new ReflectionClass( $this->plugin_class ) )->getConstant( 'HOOK_PREFIX' );
		assert( is_string( $prefix ) );

		if ( in_array( $action, $known, true ) || ( $prefix !== '' && strpos( $action, $prefix ) === 0 ) ) {
			return [];
		}

		return [
			sprintf(
				'[%1$s] The service "%2$s" (%3$s) of %4$s is Delayed, but returns the unknown action "%5$s".'
				. ' Use a core action or one starting with the plugin\'s hook prefix "%6$s". %7$s.',
				RuleManifest::REGISTRATION_ACTION_KNOWN,
				$id,
				$class_name,
				$this->plugin_class,
				$action,
				$prefix,
				RuleManifest::get_explanation( RuleManifest::REGISTRATION_ACTION_KNOWN )
			),
		];
	}

	/**
	 * Start watching for hook changes.
	 *
	 * Record mutations directly so a previous service's lifecycle watcher cannot
	 * hide constructor calls. Firing hooks still shows in Brain Monkey's storage.
	 *
	 * @param array<string> $calls Receives the watched functions called.
	 * @return mixed Snapshot of Brain Monkey's hook storage.
	 */
	private function start_watching( array &$calls ) {
		foreach ( self::WATCHED_FUNCTIONS as $function ) {
			Functions\when( $function )->alias(
				static function () use ( &$calls, $function ): bool {
					$calls[] = $function;

					return true;
				}
			);
		}

		return self::get_hooks();
	}

	/**
	 * Build a service and check that nothing since the watch started changed
	 * hooks.
	 *
	 * @param string                $id         Identifier of the service.
	 * @param class-string<Service> $class_name Class of the service.
	 * @param Injector              $injector   Injector the plugin configured.
	 * @param array<string>         $calls      Watched functions called.
	 * @param mixed                 $before     Hook storage when the watch
	 *                                          started.
	 * @return array<string> Problems found.
	 */
	private function check_construction(
		string $id,
		string $class_name,
		Injector $injector,
		array &$calls,
		$before
	): array {
		try {
			$service = $injector->make( $class_name );
		} catch ( Throwable $throwable ) {
			return [
				$this->problem(
					$id,
					$class_name,
					sprintf(
						'cannot be built from the composition root: %s Bind what it needs in get_bindings(),'
						. ' get_arguments() or get_delegations().',
						$throwable->getMessage()
					)
				),
			];
		}

		if ( ! $service instanceof Service ) {
			return [
				$this->problem(
					$id,
					$class_name,
					sprintf(
						'is built as %s, which is not a Service. Correct the binding or delegation for it in the'
						. ' composition root.',
						get_class( $service )
					)
				),
			];
		}

		$problems = [];
		if ( $calls !== [] || self::get_hooks() !== $before ) {
			$problems = [
				$this->problem(
					$id,
					$class_name,
					'changes hooks while it is being built, directly or through a collaborator its constructor'
					. ' creates. Constructors only store dependencies; add hooks in register().'
				),
			];
		}

		return array_merge( $problems, $this->check_lifecycle( $id, $class_name, $service ) );
	}

	/**
	 * Build the steps of the scoped lifecycle capabilities a service has.
	 *
	 * @param Service $service Service built by the injector.
	 * @return array<string,callable():void> Steps, by method name.
	 */
	private static function scoped_lifecycle_steps( Service $service ): array {
		$network = NetworkId::from_int( 1 );
		$site    = SiteContext::for_site( SiteId::from_int( 1 ), $network, LifecycleScope::current_site() );
		$network = NetworkContext::for_network( $network );
		$steps   = [];

		if ( $service instanceof SiteActivateable ) {
			$steps['activate_site'] = static function () use ( $service, $site ): void {
				$service->activate_site( $site );
			};
		}
		if ( $service instanceof NetworkActivateable ) {
			$steps['activate_network'] = static function () use ( $service, $network ): void {
				$service->activate_network( $network );
			};
		}
		if ( $service instanceof SiteDeactivateable ) {
			$steps['deactivate_site'] = static function () use ( $service, $site ): void {
				$service->deactivate_site( $site );
			};
		}
		if ( $service instanceof NetworkDeactivateable ) {
			$steps['deactivate_network'] = static function () use ( $service, $network ): void {
				$service->deactivate_network( $network );
			};
		}
		if ( $service instanceof SiteInitializable ) {
			$steps['initialize_site'] = static function () use ( $service, $site ): void {
				$service->initialize_site( $site );
			};
		}
		if ( $service instanceof SiteUninitializable ) {
			$steps['uninitialize_site'] = static function () use ( $service, $site ): void {
				$service->uninitialize_site( $site );
			};
		}
		if ( $service instanceof SiteUninstallable ) {
			$steps['uninstall_site'] = static function () use ( $service, $site ): void {
				$service->uninstall_site( $site );
			};
		}
		if ( $service instanceof NetworkUninstallable ) {
			$steps['uninstall_network'] = static function () use ( $service, $network ): void {
				$service->uninstall_network( $network );
			};
		}

		return $steps;
	}

	/**
	 * Run each declared lifecycle capability twice on the same service.
	 *
	 * The scoped capabilities run with the context of site 1 on network 1, as
	 * on a single site. Whether the plugin calls them in the right sites is
	 * for the plugin's own tests; this checks that a service can be called
	 * twice in the same one.
	 *
	 * @param string                $id         Service identifier.
	 * @param class-string<Service> $class_name Declared service class.
	 * @param Service               $service    Service built by the injector.
	 * @return array<string> Problems found.
	 */
	private function check_lifecycle( string $id, string $class_name, Service $service ): array {
		$steps = [];
		if ( $service instanceof Registerable ) {
			$steps['register'] = static function () use ( $service ): void {
				$service->register();
			};
		}
		if ( $service instanceof Activateable ) {
			$steps['activate'] = static function () use ( $service ): void {
				$service->activate();
			};
		}
		if ( $service instanceof Deactivateable ) {
			$steps['deactivate'] = static function () use ( $service ): void {
				$service->deactivate();
			};
		}

		$steps += self::scoped_lifecycle_steps( $service );

		$effects = new LifecycleEffects();
		$effects->watch();
		$problems = [];
		foreach ( $steps as $method => $step ) {
			$before = [];
			foreach ( [ 'first', 'second' ] as $run ) {
				try {
					$step();
				} catch ( Throwable $throwable ) {
					$problems[] = $this->problem(
						$id,
						$class_name,
						sprintf(
							'throws on the %s call of %s(): %s. Make the lifecycle step safe to run twice.',
							$run,
							$method,
							$throwable->getMessage()
						)
					);
					break;
				}

				if ( $run === 'first' ) {
					$before = $effects->snapshot();
					continue;
				}

				foreach ( $effects->changes_since( $before ) as $effect ) {
					$problems[] = $this->problem(
						$id,
						$class_name,
						sprintf(
							'changes the plugin on the second call of %s(): it %s.'
							. ' Guard the step so a second run does nothing, or make it set rather than add.',
							$method,
							$effect
						)
					);
				}
			}
		}

		return $problems;
	}

	/**
	 * Get every hook Brain Monkey has seen added, removed or fired.
	 *
	 * @return mixed Snapshot of Brain Monkey's hook storage.
	 */
	private static function get_hooks() {
		$storage  = Container::instance()->hookStorage();
		$property = new ReflectionProperty( $storage, 'storage' );
		$property->setAccessible( true );

		return $property->getValue( $storage );
	}

	/**
	 * Call one of the composition root's protected methods.
	 *
	 * @param ServiceBasedPlugin $plugin    Composition root.
	 * @param string             $method    Method to call.
	 * @param mixed              ...$values Arguments.
	 * @return mixed What the method returns.
	 */
	private static function call( ServiceBasedPlugin $plugin, string $method, ...$values ) {
		$reflection = new ReflectionMethod( $plugin, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( $plugin, ...$values );
	}

	/**
	 * Configure the plugin's injector the way registering its services does,
	 * without registering anything.
	 *
	 * The plugin keeps the injector that configure_injector() returns, which
	 * may be a new instance, and resolves callables in its configuration with
	 * it, so the check does the same.
	 *
	 * Registering also hands a RegistrationReport to the constructors that
	 * ask for one, once a service observes the registration. The check
	 * stands in an empty one, so that such a service, like the optional
	 * Query Monitor panel, can be built and checked too.
	 *
	 * @param ServiceBasedPlugin $plugin Plugin whose injector to configure.
	 * @return Injector Configured injector.
	 */
	private function configure_injector( ServiceBasedPlugin $plugin ): Injector {
		$property = new ReflectionProperty( ServiceBasedPlugin::class, 'injector' );
		$property->setAccessible( true );

		$injector = $property->getValue( $plugin );
		assert( $injector instanceof Injector );

		$configured = self::call( $plugin, 'configure_injector', $injector );
		assert( $configured instanceof Injector );

		$report     = new SimpleRegistrationLog();
		$configured = $configured->delegate(
			RegistrationReport::class,
			static fn(): RegistrationReport => $report
		);

		$property->setValue( $plugin, $configured );

		return $configured;
	}

	/**
	 * Complete a problem with the service and the rule's explanation.
	 *
	 * @param string $id         Identifier of the service.
	 * @param string $class_name Class the service is declared as.
	 * @param string $problem    What is wrong, and what to do instead.
	 * @return string Complete message.
	 */
	private function problem( string $id, string $class_name, string $problem ): string {
		return sprintf(
			'[%1$s] The service "%2$s" (%3$s) of %4$s %5$s %6$s.',
			RuleManifest::COMPOSITION_ROOT_CONTRACTS,
			$id,
			$class_name,
			$this->plugin_class,
			$problem,
			RuleManifest::get_explanation( RuleManifest::COMPOSITION_ROOT_CONTRACTS )
		);
	}
}
