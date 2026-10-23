<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationPanel;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationRecord;
use MWPD\BasicScaffold\Infrastructure\Registration\SimpleRegistrationLog;
use MWPD\BasicScaffold\SampleSubsystem\SampleBackendService;
use MWPD\BasicScaffold\SampleSubsystem\SampleLoopService;
use MWPD\BasicScaffold\Tests\Fixture\QueryMonitorInstallation;
use QM_Collectors;
use QM_Output_Html;

/**
 * The composition root as it ships, with the optional Query Monitor panel
 * declared through one service entry: the panel renders the real template,
 * views/query-monitor-registration, from the root's own wiring. Without the
 * entry, nothing of it happens, with or without Query Monitor.
 *
 * Every test runs in a process of its own, so that the Query Monitor
 * stand-ins one test loads do not leak into another.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class QueryMonitorPanelTest extends TestCase {

	private const PANEL_ID = 'mwpd-basic-scaffold-registration';

	protected function set_up() {
		parent::set_up();

		Functions\stubEscapeFunctions();
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'get_stylesheet_directory' )->justReturn( '/nonexistent/child-theme' );
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/parent-theme' );
	}

	public function test_the_declared_panel_shows_the_samples_from_the_composition_root(): void {
		( new QueryMonitorInstallation() )->install()->run();

		Filters\expectApplied( 'mwpd.basic_scaffold.services' )->once()->andReturnUsing(
			static fn( array $services ): array => $services
				+ [ BasicScaffoldPlugin::REGISTRATION_PANEL_ID => RegistrationPanel::class ]
		);

		$plugin = new BasicScaffoldPlugin();
		$plugin->register_services();

		$panel = $plugin->get_container()->get( BasicScaffoldPlugin::REGISTRATION_PANEL_ID );
		$this->assertInstanceOf( RegistrationPanel::class, $panel );

		$panel->add_collector();
		$outputters = $panel->add_output( [] );
		$this->assertIsArray( $outputters );
		$output = $outputters[ self::PANEL_ID ];
		$this->assertInstanceOf( QM_Output_Html::class, $output );
		$this->assertNotNull( QM_Collectors::get( self::PANEL_ID ) );

		$html = (string) $output->get_output();

		$this->assertStringContainsString( 'id="qm-' . self::PANEL_ID . '"', $html );
		$this->assertStringContainsString( 'Basic Scaffold services', $html );
		$this->assertMatchesRegularExpression(
			'~<code>' . preg_quote( BasicScaffoldPlugin::SAMPLE_BACKEND_SERVICE_ID, '~' ) . '</code>.*?<code>'
				. preg_quote( SampleBackendService::class, '~' ) . '</code>.*?Skipped.*?Not needed in this request~s',
			$html
		);
		$this->assertMatchesRegularExpression(
			'~<code>' . preg_quote( BasicScaffoldPlugin::SAMPLE_LOOP_SERVICE_ID, '~' ) . '</code>.*?<code>'
				. preg_quote( SampleLoopService::class, '~' ) . '</code>'
				. '.*?Waiting for an action.*?Awaited actions: wp~s',
			$html
		);
	}

	/**
	 * @dataProvider data_query_monitor
	 *
	 * @param bool $query_monitor_runs Whether Query Monitor runs.
	 */
	public function test_without_the_entry_nothing_of_the_panel_happens( bool $query_monitor_runs ): void {
		if ( $query_monitor_runs ) {
			( new QueryMonitorInstallation() )->install()->run();
		}

		$plugin = new BasicScaffoldPlugin();
		$plugin->register_services();

		$this->assertFalse( $plugin->get_container()->has( BasicScaffoldPlugin::REGISTRATION_PANEL_ID ) );
		$this->assertFalse( class_exists( RegistrationPanel::class, false ) );
		$this->assertFalse( class_exists( SimpleRegistrationLog::class, false ) );
		$this->assertFalse( class_exists( RegistrationRecord::class, false ) );
		$this->assertFalse( has_filter( 'qm/outputter/html' ) );
		$this->assertFalse( has_filter( 'qm/output/menus' ) );
	}

	/**
	 * @return array<string,array{bool}>
	 */
	public static function data_query_monitor(): array {
		return [
			'Query Monitor runs'          => [ true ],
			'Query Monitor is not loaded' => [ false ],
		];
	}
}
