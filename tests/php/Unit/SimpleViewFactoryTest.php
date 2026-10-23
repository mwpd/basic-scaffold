<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Exception\InvalidViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\View\SimpleViewFactory;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class SimpleViewFactoryTest extends TestCase {

	public function test_it_can_be_instantiated(): void {
		$factory = new SimpleViewFactory();

		$this->assertInstanceOf( SimpleViewFactory::class, $factory );
	}

	public function test_it_implements_the_interface(): void {
		$factory = new SimpleViewFactory();

		$this->assertInstanceOf( ViewFactory::class, $factory );
	}

	public function test_it_creates_a_view_that_renders_nothing_for_a_missing_view_in_production(): void {
		$view = ( new SimpleViewFactory( $this->debug_mode( false ), $this->configuration() ) )
			->create( 'non/existent/view' );

		$this->assertInstanceOf( View::class, $view );
		$this->assertSame( '', $view->render( [ 'some_value' => 'ignored' ] ) );
		$this->assertSame( '', $view->render_partial( 'non/existent/partial' ) );
		$this->assertNull( $view->raw( 'some_value' ) );
		$this->assertSame( '', $view->some_value );
	}

	public function test_it_throws_for_a_missing_view_in_debug_mode(): void {
		$this->expectException( InvalidPath::class );

		( new SimpleViewFactory( $this->debug_mode( true ), $this->configuration() ) )->create( 'non/existent/view' );
	}

	public function test_its_views_render_nothing_for_a_failing_template_in_production(): void {
		$view = ( new SimpleViewFactory( $this->debug_mode( false ), $this->configuration() ) )
			->create( 'tests/php/Fixture/views/plugin/throwing-view' );

		$this->assertSame( '', $view->render() );
	}

	public function test_its_views_throw_for_a_failing_template_in_debug_mode(): void {
		$view = ( new SimpleViewFactory( $this->debug_mode( true ), $this->configuration() ) )
			->create( 'tests/php/Fixture/views/plugin/throwing-view' );

		$this->expectException( FailedToLoadView::class );

		$view->render();
	}

	public function test_it_throws_a_named_exception_without_a_configuration_in_debug_mode(): void {
		$this->expectException( InvalidViewConfiguration::class );

		( new SimpleViewFactory( $this->debug_mode( true ) ) )->create( 'tests/php/Fixture/views/plugin/static-view' );
	}

	public function test_it_creates_a_view_that_renders_nothing_without_a_configuration_in_production(): void {
		$view = ( new SimpleViewFactory( $this->debug_mode( false ) ) )
			->create( 'tests/php/Fixture/views/plugin/static-view' );

		$this->assertSame( '', $view->render() );
	}

	public function test_its_views_use_the_configuration_it_was_given(): void {
		$factory = new SimpleViewFactory(
			$this->debug_mode( true ),
			new ViewConfiguration( ViewHelper::SCOPED_PLUGIN_FOLDER, ViewHelper::OVERRIDE_DIRECTORY )
		);

		$this->assertStringStartsWith( '<p>Probe comes from plugin.</p>', $factory->create( 'views/probe' )->render() );
	}

	/**
	 * Configuration naming the repository as the plugin root.
	 */
	private function configuration(): ViewConfiguration {
		return new ViewConfiguration( ViewHelper::PLUGIN_ROOT, ViewHelper::OVERRIDE_DIRECTORY );
	}
}
