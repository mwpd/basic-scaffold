<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;
use MWPD\BasicScaffold\Infrastructure\QueryMonitor\CollectorFactory;
use MWPD\BasicScaffold\Infrastructure\QueryMonitor\OutputFactory;
use MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationCollectorFactory;
use MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationOutputFactory;
use MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationPanel;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationLog;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationRecord;
use MWPD\BasicScaffold\Infrastructure\Registration\SimpleRegistrationLog;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\Fixture\QueryMonitorInstallation;
use MWPD\BasicScaffold\Tests\Fixture\Registration\CountingUnneededService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\HostileActionService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\HostileDependentService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\RegisteredService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService;
use MWPD\BasicScaffold\Tests\Fixture\TestServicesPlugin;
use MWPD\BasicScaffold\Tests\ViewHelper;
use QM_Collector;
use QM_Collectors;
use QM_Output_Html;
use ReflectionClass;

/**
 * The optional Query Monitor panel: off, at the cost of a cheap check, in
 * every request where it is not declared or Query Monitor does not run, and
 * showing the registration history where both hold.
 *
 * Every test runs in a process of its own, because a class that a test loads,
 * such as a Query Monitor stand-in, stays loaded for the rest of the process.
 * The panel renders views/query-monitor-registration.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationPanel
 * @covers \MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationCollectorFactory
 * @covers \MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationOutputFactory
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationPanelTest extends TestCase {

	private const PANEL = 'panel';

	private const PANEL_ID = 'test-registration';

	private const PANEL_TITLE = 'Test <em>services</em>';

	private const HOSTILE_ID = '<script>alert("id")</script>';

	/**
	 * Query Monitor classes anything asked an autoloader for, when no Query
	 * Monitor is installed.
	 *
	 * @var list<string>
	 */
	private array $requested = [];

	/**
	 * Callbacks added to the hooks the panel uses, by hook.
	 *
	 * @var array<string,list<callable>>
	 */
	private array $callbacks = [];

	/**
	 * Priorities the callbacks were added at, by hook.
	 *
	 * @var array<string,list<int>>
	 */
	private array $priorities = [];

	/**
	 * How often the resolver of the delayed service ran.
	 */
	private int $resolver_calls = 0;

	protected function set_up() {
		parent::set_up();

		Functions\stubEscapeFunctions();
		Functions\when( 'get_stylesheet_directory' )->justReturn( '/nonexistent/child-theme' );
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/parent-theme' );

		CountingUnneededService::$asked = 0;

		foreach ( [ 'shutdown', 'qm/outputter/html', 'qm/output/menus' ] as $hook ) {
			$expectation = 'shutdown' === $hook ? Actions\expectAdded( $hook ) : Filters\expectAdded( $hook );
			$expectation->zeroOrMoreTimes()->whenHappen(
				function ( callable $callback, int $priority = 10 ) use ( $hook ): void {
					$this->callbacks[ $hook ][]  = $callback;
					$this->priorities[ $hook ][] = $priority;
				}
			);
		}
	}

	public function test_without_query_monitor_the_panel_is_off_and_asks_for_nothing(): void {
		$this->spy_on_autoloading();

		$this->assertFalse( RegistrationPanel::is_needed() );
		$this->assertFalse( RegistrationPanel::observes_registration() );
		$this->assertSame( [], $this->requested );
	}

	public function test_an_installed_query_monitor_that_does_not_run_leaves_the_panel_off(): void {
		$installation = ( new QueryMonitorInstallation() )->install();

		$this->assertFalse( RegistrationPanel::is_needed() );
		$this->assertFalse( RegistrationPanel::observes_registration() );
		$this->assertSame( [], $installation->get_requested(), 'The check must not autoload Query Monitor.' );
	}

	public function test_a_running_query_monitor_turns_the_panel_on(): void {
		( new QueryMonitorInstallation() )->install()->run();

		$this->assertTrue( RegistrationPanel::is_needed() );
		$this->assertTrue( RegistrationPanel::observes_registration() );
	}

	public function test_a_request_query_monitor_does_not_support_leaves_the_panel_off(): void {
		( new QueryMonitorInstallation() )->install()->run();
		$_GET['interim-login'] = '1';

		$this->assertFalse( RegistrationPanel::is_needed() );
		$this->assertFalse( RegistrationPanel::observes_registration() );
	}

	/**
	 * Query Monitor only finds out from setup_theme on that a request is a
	 * Customizer preview, after the plugin decided whether to record. The
	 * Customizer's class, which WordPress loads on plugins_loaded, tells
	 * earlier.
	 */
	public function test_a_customizer_request_leaves_the_panel_off(): void {
		( new QueryMonitorInstallation() )->install()->run();
		$this->assertTrue( RegistrationPanel::observes_registration() );

		$this->assertTrue( class_exists( 'WP_Customize_Manager' ) );

		$this->assertFalse( RegistrationPanel::is_needed() );
		$this->assertFalse( RegistrationPanel::observes_registration() );
	}

	public function test_an_iframed_request_leaves_the_panel_off(): void {
		( new QueryMonitorInstallation() )->install()->run();
		define( 'IFRAME_REQUEST', true );

		$this->assertFalse( RegistrationPanel::is_needed() );
	}

	/**
	 * @dataProvider data_unavailable_query_monitor
	 *
	 * @param string $query_monitor How Query Monitor is unavailable.
	 */
	public function test_a_declared_panel_without_query_monitor_takes_the_quiet_path( string $query_monitor ): void {
		$installation = null;

		if ( 'absent' === $query_monitor ) {
			$this->spy_on_autoloading();
		} else {
			$installation = ( new QueryMonitorInstallation() )->install();
		}

		if ( 'unsupported request' === $query_monitor && null !== $installation ) {
			$installation->run();
			$_GET['interim-login'] = '1';
		}

		$plugin = $this->register_with_panel( $this->never_written_log() );

		$this->assertFalse( $plugin->get_container()->has( self::PANEL ) );
		$this->assertTrue( $plugin->get_container()->has( 'registered' ) );
		$this->assertSame( 1, CountingUnneededService::$asked );
		$this->assertSame( 1, $this->resolver_calls );
		$this->assert_nothing_of_the_panel_happened();

		if ( 'absent' === $query_monitor ) {
			$this->assertSame( [], $this->requested );
		}

		if ( 'installed, not running' === $query_monitor && null !== $installation ) {
			$this->assertSame( [], $installation->get_requested() );
		}
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function data_unavailable_query_monitor(): array {
		return [
			'absent'                 => [ 'absent' ],
			'installed, not running' => [ 'installed, not running' ],
			'unsupported request'    => [ 'unsupported request' ],
		];
	}

	public function test_a_running_query_monitor_without_the_declared_panel_changes_nothing(): void {
		( new QueryMonitorInstallation() )->install()->run();

		$plugin = new TestServicesPlugin(
			[
				'registered' => RegisteredService::class,
				'unneeded'   => CountingUnneededService::class,
			],
			$this->injector(),
			null,
			$this->never_written_log()
		);
		$plugin->register_services();

		$this->assertTrue( $plugin->get_container()->has( 'registered' ) );
		$this->assert_nothing_of_the_panel_happened();
		$this->assertFalse( class_exists( RegistrationPanel::class, false ) );
		$this->assertFalse( class_exists( RegistrationCollectorFactory::class, false ) );
		$this->assertFalse( class_exists( RegistrationOutputFactory::class, false ) );
		$this->assertNull( QM_Collectors::get( self::PANEL_ID ) );
	}

	/**
	 * Query Monitor applies its qm/collectors filter once, on plugins_loaded,
	 * and whether that runs before or after the plugin registers its services
	 * depends on the order the plugins load in. The panel shows either way.
	 *
	 * @dataProvider data_load_orders
	 *
	 * @param bool $query_monitor_first Whether Query Monitor's plugins_loaded
	 *                                  callback runs before the plugin's.
	 */
	public function test_the_panel_shows_the_history_whichever_plugin_loads_first( bool $query_monitor_first ): void {
		$query_monitor = ( new QueryMonitorInstallation() )->install()->run();

		if ( $query_monitor_first ) {
			$query_monitor->action_plugins_loaded();
		}

		$log    = new SimpleRegistrationLog();
		$plugin = $this->register_with_panel( $log );

		if ( ! $query_monitor_first ) {
			$query_monitor->action_plugins_loaded();
		}

		$this->assertInstanceOf( RegistrationPanel::class, $plugin->get_container()->get( self::PANEL ) );

		// Nothing of Query Monitor is created before it asks.
		$this->assertNull( QM_Collectors::get( self::PANEL_ID ) );

		// Query Monitor's HTML dispatcher prints at shutdown priority 9; the
		// collector joins before that.
		$this->assertSame( [ 0 ], $this->priorities['shutdown'] );
		$this->run_callbacks( 'shutdown' );
		$collector = QM_Collectors::get( self::PANEL_ID );
		$this->assertInstanceOf( QM_Collector::class, $collector );

		$outputters = $this->apply_callbacks( 'qm/outputter/html', [ 'overview' => 'kept' ] );
		$this->assertIsArray( $outputters );
		$this->assertSame( 'kept', $outputters['overview'] );
		$output = $outputters[ self::PANEL_ID ];
		$this->assertInstanceOf( QM_Output_Html::class, $output );
		$this->assertSame( $collector, $output->get_collector() );
		$this->assertFalse( $output::$client_side_rendered );

		$this->assertSame(
			[
				'qm-overview'          => [ 'id' => 'overview' ],
				'qm-' . self::PANEL_ID => [
					'id'    => self::PANEL_ID,
					'panel' => self::PANEL_ID,
					'title' => self::PANEL_TITLE,
				],
			],
			$this->apply_callbacks( 'qm/output/menus', [ 'qm-overview' => [ 'id' => 'overview' ] ] )
		);

		$this->assert_panel_shows( (string) $output->get_output(), $log );
	}

	/**
	 * @return array<string,array{bool}>
	 */
	public static function data_load_orders(): array {
		return [
			'Query Monitor first' => [ true ],
			'plugin first'        => [ false ],
		];
	}

	public function test_registering_the_panel_twice_changes_nothing(): void {
		( new QueryMonitorInstallation() )->install()->run();

		$plugin = $this->register_with_panel( new SimpleRegistrationLog() );
		$panel  = $plugin->get_container()->get( self::PANEL );
		$this->assertInstanceOf( RegistrationPanel::class, $panel );

		$panel->register();

		$this->assertSame( [ [ $panel, 'add_collector' ] ], $this->unique_callbacks( 'shutdown' ) );
		$this->assertSame( [ [ $panel, 'add_output' ] ], $this->unique_callbacks( 'qm/outputter/html' ) );
		$this->assertSame( [ [ $panel, 'add_menu' ] ], $this->unique_callbacks( 'qm/output/menus' ) );
	}

	public function test_the_panel_leaves_what_query_monitor_hands_over_alone_when_it_is_not_a_list(): void {
		( new QueryMonitorInstallation() )->install()->run();
		$plugin = $this->register_with_panel( new SimpleRegistrationLog() );
		$panel  = $plugin->get_container()->get( self::PANEL );
		$this->assertInstanceOf( RegistrationPanel::class, $panel );

		$this->assertSame( 'not a list', $panel->add_output( 'not a list' ) );
		$this->assertSame( 'not a list', $panel->add_menu( 'not a list' ) );
		$this->assertSame( [ 3 => [] ], $panel->add_menu( [ 3 => [] ] ) );
	}

	/**
	 * Factories the panel is given are only asked once Query Monitor asks for
	 * the panel, and the registration is the same whatever they are.
	 */
	public function test_factory_doubles_change_no_registration_decision(): void {
		( new QueryMonitorInstallation() )->install()->run();

		$real_log = new SimpleRegistrationLog();
		$this->register_with_panel( $real_log );

		$collector_factory = $this->createMock( CollectorFactory::class );
		$collector_factory->expects( $this->never() )->method( 'create' );
		$output_factory = $this->createMock( OutputFactory::class );
		$output_factory->expects( $this->never() )->method( 'create' );

		$this->resolver_calls           = 0;
		CountingUnneededService::$asked = 0;
		$double_log                     = new SimpleRegistrationLog();
		$injector                       = $this->injector()
			->delegate( CollectorFactory::class, static fn(): CollectorFactory => $collector_factory )
			->delegate( OutputFactory::class, static fn(): OutputFactory => $output_factory );
		$this->register_with_panel( $double_log, $injector );

		$this->assertEquals( $real_log->get_records(), $double_log->get_records() );
		$this->assertSame( 1, CountingUnneededService::$asked );
		$this->assertSame( 1, $this->resolver_calls );
	}

	/**
	 * Assert the panel lists every service with its class and current state,
	 * then the history in order, with the evidence, and escapes every value.
	 *
	 * @param string             $html Output of the panel.
	 * @param RegistrationLog    $log  Log the plugin recorded into.
	 */
	private function assert_panel_shows( string $html, RegistrationLog $log ): void {
		$escaped_id     = htmlspecialchars( self::HOSTILE_ID, ENT_QUOTES );
		$escaped_action = htmlspecialchars( HostileActionService::ACTION, ENT_QUOTES );

		$this->assertStringContainsString( 'id="qm-' . self::PANEL_ID . '"', $html );
		$this->assertStringContainsString( 'Test &lt;em&gt;services&lt;/em&gt;', $html );
		$this->assertStringContainsString( '<code>' . RegisteredService::class . '</code>', $html );
		$this->assertStringContainsString( '<code>' . $escaped_id . '</code>', $html );
		$this->assertStringContainsString( 'Awaited actions: ' . $escaped_action, $html );
		$this->assertStringContainsString(
			'Missing dependencies: ' . htmlspecialchars( HostileDependentService::DEPENDENCY, ENT_QUOTES ),
			$html
		);
		$this->assertStringContainsString( 'Waiting for an action', $html );
		$this->assertStringContainsString( 'Waiting for dependencies', $html );
		$this->assertStringContainsString( 'Not needed in this request', $html );
		$this->assertStringContainsString( 'Registered', $html );

		$markup = [ self::HOSTILE_ID, HostileActionService::ACTION, HostileDependentService::DEPENDENCY, '<em>' ];
		foreach ( $markup as $unescaped ) {
			$this->assertStringNotContainsString( $unescaped, $html );
		}

		// The current state lists each service once, then the history lists
		// every record, numbered in the order the decisions were made.
		$history = (string) strstr( $html, ': history' );
		$current = substr( $html, 0, strlen( $html ) - strlen( $history ) );
		$ids     = array_map(
			static fn( RegistrationRecord $record ): string
				=> htmlspecialchars( $record->get_service_id(), ENT_QUOTES ),
			$log->get_records()
		);

		preg_match_all( '~<tr class="[^"]*">\s*<td class="qm-ltr"><code>(.*?)</code>~s', $current, $current_rows );
		$this->assertSame( array_values( array_unique( $ids ) ), $current_rows[1] );

		// The current state is the latest record of each service: the service
		// that waits for a dependency was first recorded without the action
		// it waits for, then with it.
		$this->assertMatchesRegularExpression(
			'~<tr class="qm-warn">\s*<td class="qm-ltr"><code>hostile_needs</code></td>\s*'
				. '<td class="qm-ltr"><code>' . preg_quote( HostileDependentService::class, '~' ) . '</code></td>\s*'
				. '<td>Waiting for dependencies</td>\s*'
				. '<td class="qm-ltr">Missing dependencies: '
				. preg_quote( htmlspecialchars( HostileDependentService::DEPENDENCY, ENT_QUOTES ), '~' )
				. '; Awaited actions: init</td>\s*<td class="qm-num">2</td>~',
			$current
		);
		$this->assertMatchesRegularExpression(
			'~<tr class="">\s*<td class="qm-ltr"><code>registered</code></td>\s*'
				. '<td class="qm-ltr"><code>' . preg_quote( RegisteredService::class, '~' ) . '</code></td>\s*'
				. '<td>Registered</td>\s*<td class="qm-ltr"></td>\s*<td class="qm-num">1</td>~',
			$current
		);

		preg_match_all(
			'~<td class="qm-num">(\d+)</td>\s*<td class="qm-ltr"><code>(.*?)</code>~s',
			$history,
			$history_rows
		);
		$this->assertSame( array_map( 'strval', range( 1, count( $ids ) ) ), $history_rows[1] );
		$this->assertSame( $ids, $history_rows[2] );
		$this->assertGreaterThan( count( array_unique( $ids ) ), count( $ids ), 'Some service changed state.' );
	}

	/**
	 * Assert that the panel neither hooked into Query Monitor nor made
	 * anything, and that the plugin recorded nothing.
	 */
	private function assert_nothing_of_the_panel_happened(): void {
		$this->assertSame( [], $this->callbacks );
		$this->assertFalse( class_exists( SimpleRegistrationLog::class, false ) );
		$this->assertFalse( class_exists( RegistrationRecord::class, false ) );

		foreach ( [ 'QM_Collector', 'QM_Output' ] as $parent ) {
			if ( ! class_exists( $parent, false ) ) {
				continue;
			}

			foreach ( get_declared_classes() as $class_name ) {
				$this->assertFalse(
					is_subclass_of( $class_name, $parent ) && ( new ReflectionClass( $class_name ) )->isAnonymous(),
					'The panel must not create a collector or an output.'
				);
			}
		}
	}

	/**
	 * Register the panel among other services, as a composition root that
	 * declares it would.
	 *
	 * @param RegistrationLog $log      Log to record into.
	 * @param Injector|null   $injector Injector to use, with the panel's wiring.
	 */
	private function register_with_panel( RegistrationLog $log, ?Injector $injector = null ): TestServicesPlugin {
		$plugin = new TestServicesPlugin(
			[
				self::PANEL                         => RegistrationPanel::class,
				'registered'                        => RegisteredService::class,
				'unneeded'                          => CountingUnneededService::class,
				self::HOSTILE_ID                    => HostileActionService::class,
				'hostile_needs'                     => HostileDependentService::class,
				HostileDependentService::DEPENDENCY => TestDelayedService::class,
				'delayed_service'                   => function (): string {
					++$this->resolver_calls;

					return TestDelayedService::class;
				},
			],
			$injector ?? $this->injector(),
			null,
			$log
		);
		$plugin->register_services();

		return $plugin;
	}

	/**
	 * An injector configured the way a composition root that declares the
	 * panel configures it.
	 */
	private function injector(): Injector {
		$debug_mode = $this->debug_mode( true );

		return ( new SimpleInjector() )
			->bind( CollectorFactory::class, RegistrationCollectorFactory::class )
			->bind( OutputFactory::class, RegistrationOutputFactory::class )
			->bind( ViewFactory::class, TemplatedViewFactory::class )
			->bind_argument( RegistrationCollectorFactory::class, 'panel_id', self::PANEL_ID )
			->bind_argument( RegistrationOutputFactory::class, 'panel_title', self::PANEL_TITLE )
			->delegate( DebugMode::class, static fn(): DebugMode => $debug_mode )
			->delegate(
				ViewConfiguration::class,
				static fn(): ViewConfiguration => new ViewConfiguration(
					ViewHelper::PLUGIN_ROOT,
					ViewHelper::OVERRIDE_DIRECTORY
				)
			);
	}

	/**
	 * A registration log that fails the test if anything is recorded.
	 */
	private function never_written_log(): RegistrationLog {
		$log = $this->createMock( RegistrationLog::class );
		$log->expects( $this->never() )->method( 'record' );

		return $log;
	}

	/**
	 * Record every Query Monitor class anything asks an autoloader for,
	 * without loading it, as if Query Monitor were not installed.
	 */
	private function spy_on_autoloading(): void {
		spl_autoload_register(
			function ( string $class_name ): void {
				if ( 0 === strpos( $class_name, 'QM_' ) || 'QueryMonitor' === $class_name ) {
					$this->requested[] = $class_name;
				}
			}
		);
	}

	/**
	 * Run the callbacks added to an action.
	 */
	private function run_callbacks( string $action ): void {
		foreach ( $this->callbacks[ $action ] ?? [] as $callback ) {
			$callback();
		}
	}

	/**
	 * Apply the callbacks added to a filter to a value.
	 *
	 * @param mixed $value Value to filter.
	 * @return mixed Filtered value.
	 */
	private function apply_callbacks( string $filter, $value ) {
		foreach ( $this->callbacks[ $filter ] ?? [] as $callback ) {
			$value = $callback( $value );
		}

		return $value;
	}

	/**
	 * Get the distinct callbacks added to a hook. Adding the same callback
	 * again at the same priority replaces it in WordPress.
	 *
	 * @return list<callable>
	 */
	private function unique_callbacks( string $hook ): array {
		$unique = [];

		foreach ( $this->callbacks[ $hook ] ?? [] as $callback ) {
			if ( ! in_array( $callback, $unique, true ) ) {
				$unique[] = $callback;
			}
		}

		return $unique;
	}
}
