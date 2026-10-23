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

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;
use ReflectionClass;

/**
 * The composition root configures the view layer, without basic-scaffold.php.
 */
final class CompositionRootViewConfigurationTest extends TestCase {

	protected function set_up() {
		parent::set_up();

		Functions\stubEscapeFunctions();
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'did_action' )->justReturn( 0 );
	}

	public function test_it_configures_the_plugin_root_next_to_the_composition_root(): void {
		$configuration = $this->make_injector()->make( ViewConfiguration::class );
		$this->assertInstanceOf( ViewConfiguration::class, $configuration );

		// The plugin root is the parent of the directory that holds the
		// composition root's file, src/.
		$composition_root_file = ( new ReflectionClass( BasicScaffoldPlugin::class ) )->getFileName();
		$this->assertIsString( $composition_root_file );

		$this->assertSame(
			\realpath( \dirname( $composition_root_file, 2 ) ),
			\realpath( $configuration->get_plugin_root() )
		);
		$this->assertSame( 'basic-scaffold', $configuration->get_override_directory() );
	}

	public function test_the_bound_view_factory_uses_the_plugin_override_directory(): void {
		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::SCOPED_CHILD_THEME_FOLDER );
		Functions\when( 'get_template_directory' )->justReturn( ViewHelper::SCOPED_PARENT_THEME_FOLDER );

		$factory = $this->make_injector()->make( ViewFactory::class );
		$this->assertInstanceOf( ViewFactory::class, $factory );

		$this->assertStringStartsWith(
			'<p>Backend notice overridden by the child theme for this plugin.</p>',
			$factory->create( 'views/test-backend-service' )->render( [ 'plugin' => 'Plugin' ] )
		);
	}

	public function test_the_bound_view_factory_falls_back_to_the_plugin_root(): void {
		Functions\when( 'get_stylesheet_directory' )->justReturn( '/nonexistent/child-theme' );
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/parent-theme' );

		$factory = $this->make_injector()->make( ViewFactory::class );
		$this->assertInstanceOf( ViewFactory::class, $factory );

		$this->assertStringContainsString(
			'Raw value: <b>Plugin</b>',
			$factory->create( 'views/test-backend-service' )->render( [ 'plugin' => 'Plugin' ] )
		);
	}

	/**
	 * Register the composition root's services and return its injector.
	 */
	private function make_injector(): Injector {
		$plugin = new BasicScaffoldPlugin();
		$plugin->register_services();

		$injector = $plugin->get_container()->get( 'mwpd.basic_scaffold.' . ServiceBasedPlugin::INJECTOR_ID );
		$this->assertInstanceOf( Injector::class, $injector );

		return $injector;
	}
}
