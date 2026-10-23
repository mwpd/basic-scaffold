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

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\InvalidAsset;
use MWPD\BasicScaffold\Exception\InvalidConfiguration;
use MWPD\BasicScaffold\Infrastructure\Asset\AssetLocation;
use MWPD\BasicScaffold\Infrastructure\Asset\Script;
use MWPD\BasicScaffold\Infrastructure\Asset\Style;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\SimpleServiceContainer;
use MWPD\BasicScaffold\Tests\Fixture\Asset\AdminScreenScriptService;
use MWPD\BasicScaffold\Tests\Fixture\Asset\AssetRequests;
use MWPD\BasicScaffold\Tests\Fixture\Asset\FailingAssetService;
use MWPD\BasicScaffold\Tests\Fixture\Asset\FrontEndStyleService;
use MWPD\BasicScaffold\Tests\Fixture\Asset\LateFooterStyleService;
use MWPD\BasicScaffold\Tests\Fixture\Asset\NonAssetService;
use MWPD\BasicScaffold\Tests\Fixture\Asset\RecordingAssets;
use MWPD\BasicScaffold\Tests\Fixture\Asset\TestAssetPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceA;
use RuntimeException;

/**
 * The plugin asks its HasAssets services for their assets at the enqueue
 * actions, and hands them to the asset loader.
 */
final class AssetLoadingTest extends TestCase {

	private const ENQUEUE_ACTIONS = [
		'wp_enqueue_scripts',
		'admin_enqueue_scripts',
		'enqueue_block_editor_assets',
		'login_enqueue_scripts',
	];

	private AssetRequests $requests;

	private RecordingAssets $assets;

	/**
	 * Callbacks added to WordPress actions, by action.
	 *
	 * @var array<string,list<callable>>
	 */
	private array $callbacks = [];

	/**
	 * Actions that have run.
	 *
	 * @var array<string,true>
	 */
	private array $ran = [];

	protected function set_up() {
		parent::set_up();

		$this->requests  = new AssetRequests();
		$this->assets    = new RecordingAssets();
		$this->callbacks = [];
		$this->ran       = [];

		Functions\when( 'did_action' )->alias( fn( string $action ): int => isset( $this->ran[ $action ] ) ? 1 : 0 );
		Functions\when( 'plugins_url' )->justReturn( 'https://example.com/wp-content/plugins/acme' );
		Functions\when( 'get_file_data' )->justReturn( [ 'Version' => '1.2.3' ] );

		foreach ( array_merge( self::ENQUEUE_ACTIONS, [ 'wp_footer' ] ) as $action ) {
			Actions\expectAdded( $action )
				->zeroOrMoreTimes()
				->whenHappen(
					function ( callable $callback ) use ( $action ): void {
						$this->callbacks[ $action ][] = $callback;
					}
				);
		}
	}

	public function test_a_plugin_without_asset_services_hooks_into_no_enqueue_action(): void {
		$plugin = new TestAssetPlugin( [ 'a' => TestServiceA::class ], $this->requests, $this->assets );
		$plugin->register_services();

		foreach ( self::ENQUEUE_ACTIONS as $action ) {
			$this->assertArrayNotHasKey( $action, $this->callbacks, "No asset service, so nothing hooks into $action." );
		}
	}

	public function test_a_plugin_with_an_asset_service_hooks_into_each_enqueue_action_once(): void {
		$plugin = new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets );
		$plugin->register_services();
		$plugin->register_services();

		foreach ( self::ENQUEUE_ACTIONS as $action ) {
			$this->assertCount( 1, $this->callbacks[ $action ] ?? [], "The plugin hooks into $action once." );
		}
	}

	public function test_a_service_gets_its_assets_only_at_the_enqueue_point_it_declares_them_for(): void {
		$plugin = new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets );
		$plugin->register_services();

		$this->run_action( 'admin_enqueue_scripts', 'index.php' );
		$this->run_action( 'login_enqueue_scripts' );
		$this->assertSame( [], $this->assets->handles() );

		$this->run_action( 'wp_enqueue_scripts' );
		$this->assertSame( [ FrontEndStyleService::HANDLE ], $this->assets->handles() );
		$this->assertSame(
			[
				'built front end style',
				'asked front end style at admin index.php',
				'asked front end style at login',
				'asked front end style at front end',
			],
			$this->requests->events
		);
	}

	public function test_the_location_comes_from_the_main_file_and_its_version_header(): void {
		$reads = 0;
		Functions\when( 'get_file_data' )->alias(
			function ( string $file, array $headers ) use ( &$reads ): array {
				$this->assertSame( TestAssetPlugin::MAIN_FILE, $file );
				$this->assertSame( [ 'Version' => 'Version' ], $headers );
				++$reads;

				return [ 'Version' => '1.2.3' ];
			}
		);
		Functions\when( 'plugins_url' )->alias(
			function ( string $path, string $plugin ): string {
				$this->assertSame( [ '', TestAssetPlugin::MAIN_FILE ], [ $path, $plugin ] );

				return 'https://example.com/wp-content/plugins/acme/';
			}
		);

		// Debug mode lets the assertions made while loading surface.
		$plugin = ( new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets ) )
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->run_action( 'wp_enqueue_scripts' );
		$this->run_action( 'wp_enqueue_scripts' );

		$location = $this->assets->locations[0];
		$style    = $this->assets->enqueued[0];
		$this->assertInstanceOf( Style::class, $style );
		$path = $style->get_path();
		$this->assertNotNull( $path );
		$this->assertSame( 'https://example.com/wp-content/plugins/acme/assets/front-end.css', $location->get_url( $path ) );
		$this->assertSame( '1.2.3', $location->get_version( null ) );
		$this->assertSame( $location, $this->assets->locations[1], 'The location is made once.' );
		$this->assertSame( 1, $reads, 'The main file is read once.' );
	}

	public function test_the_main_file_is_not_read_while_there_is_nothing_to_enqueue(): void {
		Functions\when( 'get_file_data' )->alias(
			function (): void {
				$this->fail( 'The main file is read with nothing to enqueue.' );
			}
		);

		// Debug mode lets the assertions made while loading surface.
		$plugin = ( new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets ) )
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->run_action( 'admin_enqueue_scripts', 'index.php' );
		$this->assertSame( [], $this->assets->handles() );
	}

	public function test_a_main_file_without_a_version_header_fails_in_debug_mode(): void {
		Functions\when( 'get_file_data' )->justReturn( [ 'Version' => '' ] );

		$plugin = ( new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets ) )
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->expectException( InvalidAsset::class );
		$this->expectExceptionMessage( 'The main plugin file "' . TestAssetPlugin::MAIN_FILE . '" has no "Version" header' );

		$this->run_action( 'wp_enqueue_scripts' );
	}

	public function test_a_plugin_without_a_main_file_fails_to_locate_its_assets_in_debug_mode(): void {
		$plugin = ( new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets ) )
			->without_main_file()
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->expectException( InvalidConfiguration::class );

		$this->run_action( 'wp_enqueue_scripts' );
	}

	public function test_a_composition_root_can_serve_the_assets_from_elsewhere(): void {
		Functions\when( 'get_file_data' )->alias(
			function (): void {
				$this->fail( 'The main file is read although the composition root locates the assets.' );
			}
		);

		$location = new AssetLocation( 'https://cdn.example.com/acme', '9' );
		$plugin   = ( new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets ) )
			->with_location( $location )
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->run_action( 'wp_enqueue_scripts' );

		$this->assertSame( $location, $this->assets->locations[0] );
	}

	public function test_an_asset_only_service_is_built_the_first_time_it_is_asked(): void {
		$plugin = new TestAssetPlugin( [ 'script' => AdminScreenScriptService::class ], $this->requests, $this->assets );
		$plugin->register_services();

		$this->assertSame( [], $this->requests->events, 'Registering does not build a service that only has assets.' );

		$this->run_action( 'admin_enqueue_scripts', 'index.php' );
		$this->run_action( 'admin_enqueue_scripts', AdminScreenScriptService::SCREEN );

		$this->assertSame(
			[
				'built admin script',
				'asked admin script at admin index.php',
				'asked admin script at admin ' . AdminScreenScriptService::SCREEN,
			],
			$this->requests->events
		);
		$this->assertSame( [ AdminScreenScriptService::HANDLE ], $this->assets->handles() );

		$script = $this->assets->enqueued[0];
		$this->assertInstanceOf( Script::class, $script );
		$this->assertSame( Script::STRATEGY_DEFER, $script->get_strategy() );
	}

	public function test_an_asset_only_service_is_not_built_in_a_request_without_an_enqueue_point(): void {
		$plugin = new TestAssetPlugin( [ 'script' => AdminScreenScriptService::class ], $this->requests, $this->assets );
		$plugin->register_services();

		$this->assertSame( [], $this->requests->events );
		$this->assertSame( [], $this->assets->handles() );
	}

	public function test_a_service_registered_after_its_enqueue_points_gets_its_assets_at_once(): void {
		$plugin = new TestAssetPlugin( [ 'late' => LateFooterStyleService::class ], $this->requests, $this->assets );
		$plugin->register_services();

		$this->run_action( 'admin_enqueue_scripts', 'edit.php' );
		$this->run_action( 'enqueue_block_editor_assets' );
		$this->assertSame( [], $this->requests->events, 'The service is not registered yet.' );

		$this->run_action( 'wp_footer' );

		$this->assertSame(
			[ 'asked late style at admin edit.php', 'asked late style at block editor' ],
			$this->requests->events,
			'The service is asked for each enqueue point that has run, with its hook suffix.'
		);
		$this->assertSame(
			[ LateFooterStyleService::HANDLE . '-edit.php', LateFooterStyleService::HANDLE . '-page' ],
			$this->assets->handles()
		);
	}

	public function test_a_service_registered_before_an_enqueue_point_is_asked_there_once(): void {
		$plugin = new TestAssetPlugin( [ 'late' => LateFooterStyleService::class ], $this->requests, $this->assets );
		$plugin->register_services();

		$this->run_action( 'wp_footer' );
		$this->run_action( 'login_enqueue_scripts' );

		$this->assertSame( [ 'asked late style at login' ], $this->requests->events );
	}

	public function test_a_service_from_a_container_passed_in_takes_part(): void {
		$container = new SimpleServiceContainer();
		$container->put( 'style', new FrontEndStyleService( $this->requests ) );

		$plugin = new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets, $container );
		$plugin->register_services();

		$this->run_action( 'wp_enqueue_scripts' );

		$this->assertSame( [ FrontEndStyleService::HANDLE ], $this->assets->handles() );
	}

	public function test_a_failing_service_surfaces_in_debug_mode(): void {
		$plugin = ( new TestAssetPlugin( [ 'failing' => FailingAssetService::class ], $this->requests, $this->assets ) )
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The declarations failed.' );

		$this->run_action( 'wp_enqueue_scripts' );
	}

	public function test_a_failing_service_loads_none_of_its_assets_in_production_and_the_others_still_load(): void {
		$plugin = ( new TestAssetPlugin(
			[
				'failing' => FailingAssetService::class,
				'style'   => FrontEndStyleService::class,
			],
			$this->requests,
			$this->assets
		) )->with_debug_mode( $this->debug_mode( false ) );
		$plugin->register_services();

		$this->run_action( 'wp_enqueue_scripts' );

		$this->assertSame(
			[ FrontEndStyleService::HANDLE ],
			$this->assets->handles(),
			'The declarations are collected before any is enqueued, so the failing service loads nothing.'
		);
	}

	public function test_a_conflicting_asset_surfaces_in_debug_mode(): void {
		$this->assets->conflicting( FrontEndStyleService::HANDLE );
		$plugin = ( new TestAssetPlugin( [ 'style' => FrontEndStyleService::class ], $this->requests, $this->assets ) )
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->expectException( InvalidAsset::class );
		$this->expectExceptionMessage( 'already registered with a different source or options' );

		$this->run_action( 'wp_enqueue_scripts' );
	}

	public function test_a_conflicting_asset_is_skipped_in_production_and_the_others_still_load(): void {
		$this->assets->conflicting( FrontEndStyleService::HANDLE );
		$plugin = ( new TestAssetPlugin(
			[
				'style' => FrontEndStyleService::class,
				'late'  => LateFooterStyleService::class,
			],
			$this->requests,
			$this->assets
		) )->with_debug_mode( $this->debug_mode( false ) );
		$plugin->register_services();
		$this->run_action( 'wp_footer' );

		$this->run_action( 'wp_enqueue_scripts' );

		$this->assertSame( [ LateFooterStyleService::HANDLE . '-page' ], $this->assets->handles() );
	}

	public function test_a_declaration_that_is_not_an_asset_surfaces_in_debug_mode(): void {
		$plugin = ( new TestAssetPlugin( [ 'broken' => NonAssetService::class ], $this->requests, $this->assets ) )
			->with_debug_mode( $this->debug_mode( true ) );
		$plugin->register_services();

		$this->expectException( InvalidAsset::class );
		$this->expectExceptionMessage( 'A service declared an asset of type "string". Declare a Style or a Script.' );

		$this->run_action( 'wp_enqueue_scripts' );
	}

	public function test_the_admin_hook_suffix_is_normalized(): void {
		$plugin = new TestAssetPlugin( [ 'script' => AdminScreenScriptService::class ], $this->requests, $this->assets );
		$plugin->register_services();

		$this->run_action( 'admin_enqueue_scripts', [ 'not a hook suffix' ] );

		$this->assertSame( [ 'built admin script', 'asked admin script at admin ' ], $this->requests->events );
	}

	/**
	 * Run the callbacks added to an action, as do_action() would, then mark
	 * it as run.
	 *
	 * @param string $action  Action to run.
	 * @param mixed  ...$args Arguments of the action.
	 */
	private function run_action( string $action, ...$args ): void {
		foreach ( $this->callbacks[ $action ] ?? [] as $callback ) {
			$callback( ...$args );
		}

		$this->ran[ $action ] = true;
	}
}
