<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\InvalidConfiguration;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;
use MWPD\BasicScaffold\Infrastructure\InjectorFactory;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\LifecycleScope;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkSelection;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteSelection;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SynchronousSiteRunner;
use MWPD\BasicScaffold\Infrastructure\SiteRunner;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\ConditionalScopedService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\DelayedSiteService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\FullLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\IsolatedScopedService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LazyLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LeakingSiteService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LegacyAndNetworkLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LegacyAndSiteLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LifecycleRecorder;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\Multisite;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\NetworkLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\SiteCache;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\SiteLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\ThrowingScopedService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceNeedingInjector;
use MWPD\BasicScaffold\Tests\Fixture\TestLifecyclePlugin;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\SimpleServiceContainer;
use MWPD\BasicScaffold\Exception\FailedToRestoreSite;
use MWPD\BasicScaffold\Exception\InvalidArgument;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationLog;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationReport;
use MWPD\BasicScaffold\Tests\Fixture\Registration\ObservingService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\ReportReadingSiteService;
use RuntimeException;

/**
 * Activation and deactivation through the scoped lifecycle interfaces: which
 * service is called where, and how often, next to the parameterless ones.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin
 */
final class ScopedLifecycleTest extends TestCase {

	/**
	 * One service of each kind, in configuration order.
	 */
	private const ALL_KINDS = [
		'lazy'           => LazyLifecycleService::class,
		'site'           => SiteLifecycleService::class,
		'network'        => NetworkLifecycleService::class,
		'legacy+site'    => LegacyAndSiteLifecycleService::class,
		'legacy+network' => LegacyAndNetworkLifecycleService::class,
		'all'            => FullLifecycleService::class,
	];

	private LifecycleRecorder $recorder;

	protected function set_up() {
		parent::set_up();

		$this->recorder = new LifecycleRecorder();
		Functions\when( 'flush_rewrite_rules' )->alias(
			function (): void {
				$this->recorder->record( 'flush' );
			}
		);
	}

	/**
	 * @param array<string,class-string|callable> $services Services, in configuration order.
	 */
	private function plugin( array $services ): TestLifecyclePlugin {
		return new TestLifecyclePlugin( $services, $this->recorder );
	}

	/**
	 * The calls the site-scoped services of ALL_KINDS receive in one site.
	 *
	 * @return list<string>
	 */
	private static function site_calls( string $event, int $site ): array {
		$calls = [];
		foreach ( [ 'site', 'legacy+site', 'all' ] as $name ) {
			$calls[] = "construct:{$name}@{$site}";
			$calls[] = "{$event}_site:{$name}@{$site}";
		}

		return $calls;
	}

	/**
	 * The calls ALL_KINDS receive on an activation or deactivation of one
	 * site: the legacy-only service and the site-scoped services, in the
	 * order of the event, then the flush.
	 *
	 * @return list<string>
	 */
	private static function current_site_calls( string $event, int $site ): array {
		$legacy = [ 'construct:lazy', "{$event}:lazy" ];
		$scoped = self::site_calls( $event, $site );
		$calls  = 'activate' === $event ? array_merge( $legacy, $scoped ) : array_merge( $scoped, $legacy );

		return array_merge( $calls, [ 'flush' ] );
	}

	/**
	 * The calls the network-scoped services of ALL_KINDS receive.
	 *
	 * @return list<string>
	 */
	private static function network_calls( string $event, int $network, int $main_site ): array {
		$calls = [];
		foreach ( [ 'network', 'legacy+network', 'all' ] as $name ) {
			$calls[] = "construct:{$name}@{$main_site}";
			$calls[] = "{$event}_network:{$name}#{$network}";
		}

		return $calls;
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function data_events(): array {
		return [
			'activation'   => [ 'activate' ],
			'deactivation' => [ 'deactivate' ],
		];
	}

	/**
	 * A scoped service built in a fresh graph receives the plugin's
	 * registration report, as a service of the plugin's own graph does.
	 */
	public function test_a_scoped_service_receives_the_registration_report(): void {
		Multisite::single_site()->install();
		ReportReadingSiteService::$read = null;

		$this->plugin(
			[
				'observer' => ObservingService::class,
				'reader'   => ReportReadingSiteService::class,
			]
		)->activate();

		$this->assertInstanceOf( RegistrationReport::class, ReportReadingSiteService::$read );
		$this->assertNotInstanceOf( RegistrationLog::class, ReportReadingSiteService::$read );
		$this->assertNotSame( [], ReportReadingSiteService::$read->get_records() );
	}

	/**
	 * On a single site, only the legacy-only service gets the parameterless
	 * call. Every service with a site-scoped interface gets the site call
	 * instead, whether or not it also has the parameterless one, and services
	 * with only a network-scoped interface get nothing at all.
	 *
	 * @dataProvider data_events
	 */
	public function test_a_single_site_reaches_each_kind_once( string $event ): void {
		$installation = Multisite::single_site();
		$installation->install();

		$this->plugin( self::ALL_KINDS )->$event();

		$this->assertSame( self::current_site_calls( $event, 1 ), $this->recorder->events() );
		$this->assertSame( [], $installation->switches() );
	}

	/**
	 * On one site of a network, the same holds: no network-scoped call, and
	 * the site call is made in the current site without switching.
	 *
	 * @dataProvider data_events
	 */
	public function test_one_site_of_a_network_reaches_each_kind_once( string $event ): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 2 );
		$installation->install();

		$this->plugin( self::ALL_KINDS )->{ $event . '_in' }( LifecycleScope::current_site() );

		$this->assertSame( self::current_site_calls( $event, 2 ), $this->recorder->events() );
		$this->assertNotContains( 2, $installation->switches() );
	}

	/**
	 * A network activation reaches the network-scoped services once, then the
	 * site-scoped ones in every site of the network, each in that site. The
	 * legacy-only service is activated once, in the initiating site, and the
	 * services that also have the parameterless interface never get it.
	 */
	public function test_a_network_activation_sets_up_the_network_then_each_site(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2, 3 ],
				2 => [ 4 ],
			],
			2
		);
		$installation->install();

		$this->plugin( self::ALL_KINDS )->activate_in( LifecycleScope::network_wide() );

		$this->assertSame(
			array_merge(
				[ 'construct:lazy', 'activate:lazy' ],
				self::network_calls( 'activate', 1, 1 ),
				self::site_calls( 'activate', 1 ),
				self::site_calls( 'activate', 2 ),
				self::site_calls( 'activate', 3 ),
				[ 'flush' ]
			),
			$this->recorder->events()
		);
		$this->assertSame( 2, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	/**
	 * A network deactivation tears the sites down first, then the network,
	 * then the legacy-only service, and skips a site that keeps the plugin
	 * activated on its own.
	 */
	public function test_a_network_deactivation_tears_down_each_site_then_the_network(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2, 3 ],
				2 => [ 4 ],
			],
			2
		);
		$installation->install();
		$installation->activate_on_site( 3 );

		$this->plugin( self::ALL_KINDS )->deactivate_in( LifecycleScope::network_wide() );

		$this->assertSame(
			array_merge(
				self::site_calls( 'deactivate', 1 ),
				self::site_calls( 'deactivate', 2 ),
				self::network_calls( 'deactivate', 1, 1 ),
				[ 'construct:lazy', 'deactivate:lazy', 'flush' ]
			),
			$this->recorder->events()
		);
		$this->assertSame( 2, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	/**
	 * Deactivating the plugin on one site while it stays network-active tears
	 * nothing scoped down there; the legacy-only service keeps its call.
	 */
	public function test_deactivating_one_site_of_a_network_active_plugin_tears_nothing_down(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 2 );
		$installation->install();
		$installation->activate_for_network( 1 );

		$this->plugin( self::ALL_KINDS )->deactivate_in( LifecycleScope::current_site() );

		$this->assertSame( [ 'construct:lazy', 'deactivate:lazy', 'flush' ], $this->recorder->events() );
	}

	/**
	 * Plugin::activate() and deactivate() act on the current site, so an old
	 * main file that calls them keeps working.
	 *
	 * @dataProvider data_events
	 */
	public function test_the_parameterless_plugin_methods_act_on_the_current_site( string $event ): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 2 );
		$installation->install();
		$plugin = $this->plugin( [ 'site' => SiteLifecycleService::class ] );

		$plugin->$event();

		$this->assertSame( [ 'construct:site@2', "{$event}_site:site@2", 'flush' ], $this->recorder->events() );
	}

	/**
	 * Each site and network gets a graph of its own, built after switching to
	 * it: a fresh injector, fresh shared collaborators that read the site
	 * they are in, and no instance carried over from another site or from the
	 * plugin's own graph.
	 */
	public function test_each_site_gets_a_fresh_graph_built_in_that_site(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 1 );
		$installation->install();
		foreach ( [
			1 => 'One',
			2 => 'Two',
			3 => 'Three',
		] as $site => $name ) {
			$installation->set_option( $site, 'blogname', $name );
		}
		$plugin = $this->plugin( [ 'isolated' => IsolatedScopedService::class ] )->sharing( [ SiteCache::class ] );

		$plugin->activate_in( LifecycleScope::network_wide() );

		$this->assertSame( [ 'name@1:One', 'name@2:Two', 'name@3:Three', 'flush' ], $this->recorder->events() );

		$plugin_injector = $plugin->get_container()->get( 'injector' );
		foreach ( [ 1, 2, 3 ] as $site ) {
			// Shared within the graph of a site, but never across sites.
			$this->assertSame( $this->recorder->kept( "cache@{$site}" ), $this->recorder->kept( "made@{$site}" ) );
			$this->assertNotSame( $plugin_injector, $this->recorder->kept( "injector@{$site}" ) );

			foreach ( [ 1, 2, 3 ] as $other ) {
				if ( $other === $site ) {
					continue;
				}

				foreach ( [ 'cache', 'injector' ] as $kept ) {
					$this->assertNotSame(
						$this->recorder->kept( "{$kept}@{$site}" ),
						$this->recorder->kept( "{$kept}@{$other}" )
					);
				}
			}
		}
	}

	/**
	 * A scoped graph neither registers its services nor puts them into the
	 * plugin's container: a delayed service is set up in each site and still
	 * waits for its own action to register.
	 */
	public function test_a_scoped_graph_registers_nothing(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 1 );
		$installation->install();
		$plugin = $this->plugin( [ 'delayed' => DelayedSiteService::class ] );

		$plugin->activate_in( LifecycleScope::network_wide() );

		$this->assertSame(
			[
				'construct:delayed-site@1',
				'activate_site:delayed-site@1',
				'construct:delayed-site@2',
				'activate_site:delayed-site@2',
				'flush',
			],
			$this->recorder->events()
		);
		$this->assertFalse( $plugin->get_container()->has( 'delayed' ) );
	}

	/**
	 * A lazily stored service of the plugin's own graph that is reached while
	 * a scoped graph is built is still made by the plugin's injector, not by
	 * the scoped one.
	 */
	public function test_runtime_services_reached_while_a_graph_is_built_keep_the_plugins_injector(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 1 );
		$installation->install();
		$plugin = $this->plugin(
			[
				'needs-injector' => TestServiceNeedingInjector::class,
				'site'           => static function ( Injector $injector, ServiceContainer $container ): string {
					if ( $container->has( 'needs-injector' ) ) {
						$container->get( 'needs-injector' );
					}

					return SiteLifecycleService::class;
				},
			]
		);

		$plugin->activate_in( LifecycleScope::network_wide() );

		$service = $plugin->get_container()->get( 'needs-injector' );
		$this->assertInstanceOf( TestServiceNeedingInjector::class, $service );
		$this->assertSame( $plugin->get_container()->get( 'injector' ), $service->get_injector() );
	}

	/**
	 * A container passed in already filled can hold, under a configured ID,
	 * an instance that implements a scoped interface. That instance does not
	 * get the parameterless call either.
	 */
	public function test_a_prefilled_scoped_instance_gets_no_parameterless_call(): void {
		Multisite::single_site()->install();
		$container = new SimpleServiceContainer();
		$container->put( 'lazy', new FullLifecycleService( $this->recorder ) );
		$plugin = new TestLifecyclePlugin(
			[ 'lazy' => LazyLifecycleService::class ],
			$this->recorder,
			null,
			null,
			null,
			$container
		);

		$plugin->activate();
		$plugin->deactivate();

		$this->assertSame( [ 'construct:all@1', 'flush', 'flush' ], $this->recorder->events() );
	}

	/**
	 * A Conditional service is asked whether it is needed in each site it
	 * would run in, after switching there, and is not built where it is not.
	 */
	public function test_conditions_are_evaluated_in_each_target_site(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 1 );
		$installation->install();
		$installation->set_option( 2, ConditionalScopedService::NEEDED_OPTION, 'yes' );

		$this->plugin( [ 'conditional' => ConditionalScopedService::class ] )
			->activate_in( LifecycleScope::network_wide() );

		$this->assertSame(
			[ 'construct:conditional@2', 'activate_site:conditional@2', 'flush' ],
			$this->recorder->events()
		);
	}

	/**
	 * Services and injector configuration are resolved again in each site,
	 * through their filters, so a declaration resolver runs in every target.
	 */
	public function test_declaration_resolvers_run_in_each_target_site(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 1 );
		$installation->install();
		$recorder = $this->recorder;
		$plugin   = new TestLifecyclePlugin(
			[
				'site' => static function () use ( $recorder ): string {
					$recorder->record( 'resolve@' . \get_current_blog_id() );

					return SiteLifecycleService::class;
				},
			],
			$this->recorder
		);

		$plugin->activate_in( LifecycleScope::network_wide() );

		$this->assertSame(
			[
				'resolve@1', // Registration, in the initiating site.
				'resolve@1',
				'construct:site@1',
				'activate_site:site@1',
				'resolve@2',
				'construct:site@2',
				'activate_site:site@2',
				'flush',
			],
			$this->recorder->events()
		);
	}

	/**
	 * A failure in one site stops the activation there: the site context is
	 * restored, the exception is the service's own, later sites are not
	 * visited and the rewrite rules are not flushed.
	 */
	public function test_a_failing_site_stops_the_activation(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 3 );
		$installation->install();
		$installation->set_option( 2, ThrowingScopedService::FAIL_OPTION, 'yes' );
		$plugin = $this->plugin( [ 'throwing' => ThrowingScopedService::class ] );

		try {
			$plugin->activate_in( LifecycleScope::network_wide() );
			$this->fail( 'The failure was not propagated.' );
		} catch ( RuntimeException $failure ) {
			$this->assertSame( 'Failed in site 2.', $failure->getMessage() );
		}

		$this->assertSame(
			[ 'construct:throwing@1', 'activate_site:throwing@1', 'construct:throwing@2', 'activate_site:throwing@2' ],
			$this->recorder->events()
		);
		$this->assertSame( 3, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	/**
	 * A service that switches sites in its activation without switching back
	 * stops the activation, and the original site is current again.
	 */
	public function test_a_service_leaving_another_site_switched_stops_the_activation(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 2 );
		$installation->install();

		$this->expectException( FailedToRestoreSite::class );

		try {
			$this->plugin( [ 'leaking' => LeakingSiteService::class ] )->activate_in( LifecycleScope::network_wide() );
		} finally {
			$this->assertSame( 2, $installation->current_site() );
		}
	}

	/**
	 * A runner passed to the plugin visits the sites instead of the default.
	 */
	public function test_a_custom_runner_is_used(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 1 );
		$installation->install();
		$runner = new class( $this->recorder ) implements SiteRunner {

			private LifecycleRecorder $recorder;

			private SynchronousSiteRunner $runner;

			public function __construct( LifecycleRecorder $recorder ) {
				$this->recorder = $recorder;
				$this->runner   = new SynchronousSiteRunner();
			}

			public function run_on_sites( SiteSelection $sites, callable $task ): void {
				$this->recorder->record( 'custom runner: sites' );
				$this->runner->run_on_sites( $sites, $task );
			}

			public function run_on_networks( NetworkSelection $networks, callable $task ): void {
				$this->recorder->record( 'custom runner: networks' );
				$this->runner->run_on_networks( $networks, $task );
			}
		};
		$plugin = new TestLifecyclePlugin( [ 'site' => SiteLifecycleService::class ], $this->recorder, $runner );

		$plugin->activate_in( LifecycleScope::network_wide() );

		$this->assertSame(
			array_merge(
				[ 'custom runner: networks', 'custom runner: sites' ],
				[ 'construct:site@1', 'activate_site:site@1' ],
				[ 'construct:site@2', 'activate_site:site@2' ],
				[ 'construct:site@3', 'activate_site:site@3' ],
				[ 'flush' ]
			),
			$this->recorder->events()
		);
	}

	/**
	 * A plugin given a custom injector cannot know how to create a fresh one
	 * of the same kind, so a scoped service needs a factory too.
	 */
	public function test_a_custom_injector_without_a_factory_is_rejected_for_scoped_services(): void {
		Multisite::single_site()->install();
		$plugin = new TestLifecyclePlugin(
			[ 'site' => SiteLifecycleService::class ],
			$this->recorder,
			null,
			new SimpleInjector()
		);

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage(
			'The plugin was given the injector "' . SimpleInjector::class . '" but no injector factory'
		);

		$plugin->register_services();
	}

	/**
	 * Legacy-only services keep working with a custom injector alone.
	 */
	public function test_a_custom_injector_without_a_factory_still_serves_legacy_services(): void {
		Multisite::single_site()->install();
		$plugin = new TestLifecyclePlugin(
			[ 'lazy' => LazyLifecycleService::class ],
			$this->recorder,
			null,
			new SimpleInjector()
		);

		$plugin->activate();

		$this->assertSame( [ 'construct:lazy', 'activate:lazy', 'flush' ], $this->recorder->events() );
	}

	/**
	 * A custom injector with its factory: each graph starts from an injector
	 * the factory creates.
	 */
	public function test_a_custom_injector_factory_creates_each_graph_injector(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 1 );
		$installation->install();
		$recorder = $this->recorder;
		$factory  = new class( $recorder ) implements InjectorFactory {

			private LifecycleRecorder $recorder;

			public function __construct( LifecycleRecorder $recorder ) {
				$this->recorder = $recorder;
			}

			public function create(): Injector {
				$this->recorder->record( 'create injector@' . \get_current_blog_id() );

				return new SimpleInjector();
			}
		};
		$plugin   = new TestLifecyclePlugin(
			[ 'site' => SiteLifecycleService::class ],
			$this->recorder,
			null,
			new SimpleInjector(),
			$factory
		);

		$plugin->activate_in( LifecycleScope::network_wide() );

		$this->assertSame(
			[
				'create injector@1',
				'construct:site@1',
				'activate_site:site@1',
				'create injector@2',
				'construct:site@2',
				'activate_site:site@2',
				'flush',
			],
			$this->recorder->events()
		);
	}

	public function test_the_flag_wordpress_passes_is_normalized(): void {
		Functions\when( 'is_multisite' )->justReturn( true );

		$this->assertTrue( LifecycleScope::from_wordpress_flag( true )->is_network_wide() );
		$this->assertFalse( LifecycleScope::from_wordpress_flag( false )->is_network_wide() );
		$this->assertFalse( LifecycleScope::from_wordpress_flag( 1 )->is_network_wide() );
		$this->assertFalse( LifecycleScope::from_wordpress_flag( null )->is_network_wide() );
	}

	public function test_a_network_flag_on_a_single_site_means_the_current_site(): void {
		Functions\when( 'is_multisite' )->justReturn( false );

		$this->assertFalse( LifecycleScope::from_wordpress_flag( true )->is_network_wide() );
	}

	/**
	 * Check that the scoped lifecycle adds no hooks for services that do not
	 * need them.
	 */
	public function test_activation_adds_no_site_hooks_without_site_services(): void {
		Multisite::with_networks( [ 1 => [ 1 ] ], 1 )->install();
		Actions\expectAdded( 'wp_initialize_site' )->never();
		Actions\expectAdded( 'wp_uninitialize_site' )->never();

		$this->plugin( self::ALL_KINDS )->register_services();
	}

	/**
	 * @return array<string,array{class-string}>
	 */
	public static function data_site_ids(): array {
		return [
			'site'    => [ SiteId::class ],
			'network' => [ NetworkId::class ],
		];
	}

	/**
	 * @dataProvider data_site_ids
	 *
	 * @param class-string<SiteId|NetworkId> $type ID type.
	 */
	public function test_ids_accept_positive_integers_only( string $type ): void {
		$this->assertSame( 7, $type::from_wordpress( '7' )->to_int() );
		$this->assertSame( 7, $type::from_int( 7 )->to_int() );
		$this->assertTrue( $type::from_int( 7 )->equals( $type::from_wordpress( 7 ) ) );

		foreach ( [ 0, -1, '07', '7a', '', null, 7.0 ] as $invalid ) {
			try {
				$type::from_wordpress( $invalid );
				$this->fail( 'Accepted an invalid ID.' );
			} catch ( InvalidArgument $exception ) {
				$this->assertStringContainsString( 'is not a positive integer', $exception->getMessage() );
			}
		}
	}
}
