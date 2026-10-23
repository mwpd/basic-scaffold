<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Exception\InvalidViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\View\EmptyView;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class TemplatedViewFactoryTest extends TestCase {

	public function test_it_can_be_instantiated(): void {
		$factory = new TemplatedViewFactory();

		$this->assertInstanceOf( TemplatedViewFactory::class, $factory );
	}

	public function test_it_implements_the_interface(): void {
		$factory = new TemplatedViewFactory();

		$this->assertInstanceOf( ViewFactory::class, $factory );
	}

	public function test_each_factory_uses_the_theme_active_on_first_use(): void {
		Functions\when( 'get_template_directory' )->justReturn( ViewHelper::SCOPED_PARENT_THEME_FOLDER );

		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::SCOPED_CHILD_THEME_FOLDER );
		$child_theme_factory  = $this->scoped_factory();
		$parent_theme_factory = $this->scoped_factory();
		$child_view           = $child_theme_factory->create( 'views/precedence' );

		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::SCOPED_PARENT_THEME_FOLDER );

		$this->assertStringStartsWith( '<p>Precedence comes from child theme.</p>', $child_view->render() );
		$this->assertStringStartsWith(
			'<p>Precedence comes from child theme.</p>',
			$child_theme_factory->create( 'views/precedence' )->render()
		);
		$this->assertStringStartsWith(
			'<p>Precedence comes from parent theme.</p>',
			$parent_theme_factory->create( 'views/precedence' )->render()
		);
	}

	public function test_it_reads_theme_directories_once_on_first_create(): void {
		$reads = [];
		Functions\expect( 'get_stylesheet_directory' )->once()->andReturnUsing(
			static function () use ( &$reads ): string {
				$reads[] = 'child';

				return ViewHelper::SCOPED_CHILD_THEME_FOLDER;
			}
		);
		Functions\expect( 'get_template_directory' )->once()->andReturnUsing(
			static function () use ( &$reads ): string {
				$reads[] = 'parent';

				return ViewHelper::SCOPED_PARENT_THEME_FOLDER;
			}
		);

		$factory = $this->scoped_factory();
		$this->assertSame( [], $reads, 'Construction must not read theme directories.' );

		$view = $factory->create( 'views/precedence' );
		$this->assertSame( [ 'child', 'parent' ], $reads );
		$this->assertStringStartsWith( '<p>Precedence comes from child theme.</p>', $view->render() );
		$this->assertStringStartsWith(
			'<p>Precedence comes from child theme.</p>',
			$factory->create( 'views/precedence' )->render()
		);
		$this->assertSame( [ 'child', 'parent' ], $reads );
	}

	public function test_default_locations_do_not_select_a_bare_theme_views_folder(): void {
		$this->use_scoped_themes();

		// Both themes have a views/probe.php of their own, outside the
		// plugin's override directory. Neither is an override for it.
		$this->assertStringStartsWith(
			'<p>Probe comes from plugin.</p>',
			$this->scoped_factory( $this->debug_mode( true ) )->create( 'views/probe' )->render()
		);
	}

	/**
	 * @dataProvider override_precedence
	 */
	public function test_default_locations_search_child_theme_then_parent_theme_then_plugin(
		string $view,
		string $expected
	): void {
		$this->use_scoped_themes();

		$this->assertStringStartsWith(
			$expected,
			$this->scoped_factory( $this->debug_mode( true ) )->create( $view )->render()
		);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function override_precedence(): array {
		return [
			'child theme overrides both'    => [ 'views/precedence', '<p>Precedence comes from child theme.</p>' ],
			'parent theme overrides plugin' => [ 'views/parent-wins', '<p>Parent wins comes from parent theme.</p>' ],
			'plugin without overrides'      => [ 'views/plugin-only', '<p>Plugin only comes from plugin.</p>' ],
		];
	}

	public function test_plugins_with_different_override_directories_do_not_select_each_others_overrides(): void {
		$this->use_scoped_themes();

		$this_plugin  = $this->scoped_factory( $this->debug_mode( true ) );
		$other_plugin = $this->scoped_factory( $this->debug_mode( true ), 'other-plugin' );

		$this->assertStringStartsWith(
			'<p>Collision comes from the basic-scaffold override.</p>',
			$this_plugin->create( 'views/collision' )->render()
		);
		$this->assertStringStartsWith(
			'<p>Collision comes from the other-plugin override.</p>',
			$other_plugin->create( 'views/collision' )->render()
		);
		$this->assertStringStartsWith(
			'<p>Plugin only comes from plugin.</p>',
			$this_plugin->create( 'views/plugin-only' )->render()
		);
	}

	public function test_default_locations_without_configuration_throw_in_debug_mode(): void {
		$this->use_scoped_themes();
		$factory = new TemplatedViewFactory( [], $this->debug_mode( true ) );

		$this->expectException( InvalidViewConfiguration::class );
		$this->expectExceptionMessage( 'no view configuration says where that is' );

		$factory->create( 'views/precedence' );
	}

	public function test_default_locations_without_configuration_render_nothing_in_production(): void {
		$this->use_scoped_themes();
		$factory = new TemplatedViewFactory( [], $this->debug_mode( false ) );

		$view = $factory->create( 'views/precedence' );

		$this->assertInstanceOf( EmptyView::class, $view );
		$this->assertSame( '', $view->render() );
	}

	public function test_it_keeps_explicit_locations_in_the_given_order(): void {
		Functions\expect( 'get_stylesheet_directory' )->never();
		Functions\expect( 'get_template_directory' )->never();

		// Explicit locations are used as they are, so they need no view
		// configuration.
		$factory = new TemplatedViewFactory( [ ViewHelper::PLUGIN_FOLDER, ViewHelper::CHILD_THEME_FOLDER ] );

		$this->assertStringStartsWith(
			'<p>View C comes from plugin.</p>',
			$factory->create( 'view-c' )->render()
		);
		$this->assertStringStartsWith(
			'<p>View C comes from plugin.</p>',
			$factory->create( 'view-c' )->render()
		);
	}

	public function test_it_creates_a_view_that_renders_nothing_for_a_missing_view_in_production(): void {
		$factory = new TemplatedViewFactory( ViewHelper::LOCATIONS, $this->debug_mode( false ) );
		$view    = $factory->create( 'non-existent-view' );

		$this->assertInstanceOf( View::class, $view );
		$this->assertSame( '', $view->render() );
	}

	public function test_it_throws_for_a_missing_view_in_debug_mode(): void {
		$factory = new TemplatedViewFactory( ViewHelper::LOCATIONS, $this->debug_mode( true ) );

		$this->expectException( InvalidPath::class );

		$factory->create( 'non-existent-view' );
	}

	public function test_its_views_render_nothing_for_a_failing_template_in_production(): void {
		$factory = new TemplatedViewFactory( ViewHelper::LOCATIONS, $this->debug_mode( false ) );

		$this->assertSame( '', $factory->create( 'throwing-view' )->render() );
	}

	public function test_its_views_throw_for_a_failing_template_in_debug_mode(): void {
		$factory = new TemplatedViewFactory( ViewHelper::LOCATIONS, $this->debug_mode( true ) );
		$view    = $factory->create( 'throwing-view' );

		$this->expectException( FailedToLoadView::class );

		$view->render();
	}

	/**
	 * Stub the active child and parent theme with the scoped fixtures.
	 */
	private function use_scoped_themes(): void {
		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::SCOPED_CHILD_THEME_FOLDER );
		Functions\when( 'get_template_directory' )->justReturn( ViewHelper::SCOPED_PARENT_THEME_FOLDER );
	}

	/**
	 * Create a factory with default locations for the scoped fixture plugin.
	 *
	 * @param ?DebugMode $debug_mode         Debug mode to use.
	 * @param string     $override_directory Theme override directory of the plugin.
	 */
	private function scoped_factory(
		?DebugMode $debug_mode = null,
		string $override_directory = ViewHelper::OVERRIDE_DIRECTORY
	): TemplatedViewFactory {
		return new TemplatedViewFactory(
			[],
			$debug_mode,
			new ViewConfiguration( ViewHelper::SCOPED_PLUGIN_FOLDER, $override_directory )
		);
	}
}
