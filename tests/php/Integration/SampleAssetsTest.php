<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Infrastructure\Asset\Asset;
use MWPD\BasicScaffold\Infrastructure\Asset\Script;
use MWPD\BasicScaffold\Infrastructure\Asset\Style;
use MWPD\BasicScaffold\Tests\Fixture\Asset\RecordingAssets;

/**
 * The sample services declare their assets, and the real composition root
 * loads them only where the services are needed.
 */
final class SampleAssetsTest extends TestCase {

	private const PLUGIN_ROOT = __DIR__ . '/../../..';

	private const PLUGIN_URL = 'https://example.com/wp-content/plugins/basic-scaffold';

	private RecordingAssets $assets;

	/**
	 * Callbacks added to the enqueue actions, by action.
	 *
	 * @var array<string,list<callable>>
	 */
	private array $callbacks = [];

	protected function set_up() {
		parent::set_up();

		$this->assets    = new RecordingAssets();
		$this->callbacks = [];

		Functions\stubEscapeFunctions();
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'did_action' )->alias( static fn( string $action ): bool => $action === 'wp' );
		Functions\when( 'plugins_url' )->justReturn( self::PLUGIN_URL );

		// Read the header from the real main file, as WordPress would.
		Functions\when( 'get_file_data' )->alias(
			static function ( string $file, array $headers ): array {
				$contents = (string) file_get_contents( $file );
				$values   = [];

				foreach ( $headers as $key => $name ) {
					$values[ $key ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $contents, $match )
						? trim( $match[1] )
						: '';
				}

				return $values;
			}
		);

		foreach ( [ 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'enqueue_block_editor_assets', 'login_enqueue_scripts' ] as $action ) {
			Actions\expectAdded( $action )
				->zeroOrMoreTimes()
				->whenHappen(
					function ( callable $callback ) use ( $action ): void {
						$this->callbacks[ $action ][] = $callback;
					}
				);
		}
	}

	public function test_the_post_header_style_loads_on_the_front_end_of_singular_views(): void {
		$this->request( false, true );

		$this->run_action( 'wp_enqueue_scripts' );

		$this->assertSame( [ 'mwpd-basic-scaffold-post-header' ], $this->assets->handles() );
		$this->assert_ships( $this->assets->enqueued[0] );
		$this->assertSame(
			self::PLUGIN_URL . '/assets/css/sample-loop-service.css',
			$this->url_of( $this->assets->enqueued[0] )
		);
		$this->assertSame( '0.1.0', $this->assets->locations[0]->get_version( null ), 'From the main file\'s header.' );
	}

	public function test_no_style_loads_on_an_archive_where_the_loop_service_is_not_needed(): void {
		$this->request( false, false );

		$this->run_action( 'wp_enqueue_scripts' );

		$this->assertSame( [], $this->assets->handles() );
	}

	public function test_the_copy_script_loads_deferred_on_admin_screens_only(): void {
		$this->request( true, false );

		$this->run_action( 'enqueue_block_editor_assets' );
		$this->assertSame( [], $this->assets->handles(), 'Not in the block editor\'s own enqueue point.' );

		$this->run_action( 'admin_enqueue_scripts', 'index.php' );

		$this->assertSame( [ 'mwpd-basic-scaffold-copy-button' ], $this->assets->handles() );
		$script = $this->assets->enqueued[0];
		$this->assertInstanceOf( Script::class, $script );
		$this->assertSame( Script::STRATEGY_DEFER, $script->get_strategy() );
		$this->assert_ships( $script );
	}

	public function test_no_script_loads_on_the_front_end(): void {
		$this->request( false, true );

		$this->run_action( 'wp_enqueue_scripts' );

		$this->assertNotContains( 'mwpd-basic-scaffold-copy-button', $this->assets->handles() );
	}

	/**
	 * Register the composition root's services for a request.
	 *
	 * @param bool $is_admin    Whether this is an admin request.
	 * @param bool $is_singular Whether the request is for a singular view.
	 */
	private function request( bool $is_admin, bool $is_singular ): void {
		Functions\when( 'is_admin' )->justReturn( $is_admin );
		Functions\when( 'is_singular' )->justReturn( $is_singular );

		( new BasicScaffoldPlugin( true, null, null, null, null, null, $this->assets ) )->register_services();
	}

	/**
	 * Run the callbacks added to an action, as do_action() would.
	 *
	 * @param string $action  Action to run.
	 * @param mixed  ...$args Arguments of the action.
	 */
	private function run_action( string $action, ...$args ): void {
		foreach ( $this->callbacks[ $action ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}

	/**
	 * Assert that an asset's file ships with the plugin.
	 *
	 * @param Asset $asset Enqueued asset.
	 */
	private function assert_ships( Asset $asset ): void {
		$path = $asset instanceof Style || $asset instanceof Script ? $asset->get_path() : null;
		$this->assertNotNull( $path, 'The samples declare files.' );
		$this->assertFileExists( self::PLUGIN_ROOT . '/' . $path->to_string() );
	}

	/**
	 * Get the URL an enqueued asset is served from.
	 *
	 * @param Asset $asset Enqueued file asset.
	 */
	private function url_of( Asset $asset ): string {
		$this->assertInstanceOf( Style::class, $asset );
		$path = $asset->get_path();
		$this->assertNotNull( $path );

		return $this->assets->locations[0]->get_url( $path );
	}
}
