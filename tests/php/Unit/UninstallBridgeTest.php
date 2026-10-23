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
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Composer\Autoload\ClassLoader;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\BasicScaffoldPluginFactory;
use MWPD\BasicScaffold\BasicScaffoldUninstallHook;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\DelayedLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LifecycleRecorder;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\Multisite;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\UninstallService;

/**
 * The way WordPress uninstalls the plugin: it includes the main plugin file,
 * then calls the callback activation stored. Each test includes the main file
 * in a process of its own, as WordPress does in its own request.
 *
 * @covers \MWPD\BasicScaffold\BasicScaffoldUninstallHook
 */
final class UninstallBridgeTest extends TestCase {

	private const MAIN_FILE = __DIR__ . '/../../../basic-scaffold.php';

	/**
	 * Callbacks the main file registered, by hook.
	 *
	 * @var array<string,callable>
	 */
	private array $callbacks = [];

	private LifecycleRecorder $recorder;

	protected function set_up() {
		parent::set_up();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/' );
		}

		$this->recorder  = new LifecycleRecorder();
		$this->callbacks = [];

		foreach ( [
			'register_activation_hook'   => 'activate',
			'register_deactivation_hook' => 'deactivate',
		] as $function => $hook ) {
			Functions\when( $function )->alias(
				function ( string $file, callable $callback ) use ( $hook ): void {
					$this->callbacks[ $hook ] = $callback;
				}
			);
		}

		Actions\expectAdded( 'plugins_loaded' )->whenHappen(
			function ( callable $callback ): void {
				$this->callbacks['plugins_loaded'] = $callback;
			}
		);

		// A plugin loaded earlier adds an uninstall service and an unrelated
		// one, which is built and registered in a normal request.
		$recorder = $this->recorder;
		Filters\expectApplied( 'mwpd.basic_scaffold.services' )->andReturnUsing(
			static function ( array $services ): array {
				return $services + [
					'acme.delayed'   => DelayedLifecycleService::class,
					'acme.uninstall' => UninstallService::class,
				];
			}
		);
		Filters\expectApplied( 'mwpd.basic_scaffold.delegations' )->andReturnUsing(
			static function ( array $delegations ) use ( $recorder ): array {
				return $delegations + [ LifecycleRecorder::class => static fn(): LifecycleRecorder => $recorder ];
			}
		);
	}

	/**
	 * Include the main plugin file in the method's scope, as WordPress does.
	 */
	private function include_main_file(): void {
		include self::MAIN_FILE;
	}

	/**
	 * Uninstall the way WordPress does it, after including the main file and
	 * without defining WP_UNINSTALL_PLUGIN, and fire plugins_loaded
	 * afterwards: only the uninstall service is built, and nothing registers.
	 */
	private function uninstall_and_fire_plugins_loaded(): void {
		$this->assertFalse( defined( 'WP_UNINSTALL_PLUGIN' ) );

		$this->include_main_file();
		BasicScaffoldUninstallHook::uninstall();
		( $this->callbacks['plugins_loaded'] )();

		$this->assertSame( [ 'construct:uninstall@1', 'uninstall_site:uninstall@1' ], $this->recorder->events() );
		$this->assertFalse(
			BasicScaffoldPluginFactory::create()->get_container()->has( 'mwpd.basic_scaffold.injector' ),
			'The services were registered.'
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstalling_builds_only_the_uninstall_services(): void {
		Multisite::single_site()->install();

		$this->uninstall_and_fire_plugins_loaded();
	}

	/**
	 * The bundled autoloader alone serves the plugin's classes, as it does in
	 * a copy of the plugin without vendor/.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstalling_works_with_the_bundled_autoloader_alone(): void {
		Multisite::single_site()->install();

		// Composer keeps serving everything else, but no longer the plugin's
		// own namespace, so the main file falls back to the bundled autoloader.
		$this->assertFalse( class_exists( BasicScaffoldPlugin::class, false ) );

		foreach ( ClassLoader::getRegisteredLoaders() as $loader ) {
			$loader->setPsr4( 'MWPD\\BasicScaffold\\', [] );
			$this->assertFalse( $loader->findFile( BasicScaffoldUninstallHook::class ) );
		}

		$this->uninstall_and_fire_plugins_loaded();
	}

	/**
	 * Activating the plugin through its main file stores the bridge as the
	 * uninstall callback: a pair of strings that survives serialization and
	 * names a public static method.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_activation_stores_the_bridge_as_the_uninstall_callback(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2 ],
				2 => [ 3 ],
			],
			2
		);
		$installation->install();
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );

		$this->include_main_file();
		( $this->callbacks['activate'] )( true );

		$basename = \plugin_basename( (string) realpath( self::MAIN_FILE ) );
		$expected = [ $basename => [ BasicScaffoldUninstallHook::class, 'uninstall' ] ];

		foreach ( [ 2, 1, 3 ] as $site ) {
			$this->assertSame( $expected, $installation->option( $site, 'uninstall_plugins' ), "Site {$site}" );
		}
	}
}
