<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidContextProperty;
use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\View\SimpleView;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use PHPUnit\Framework\MockObject\MockObject;

final class SimpleViewTest extends TestCase {

	/** @var MockObject&ViewFactory */
	private MockObject $view_factory;

	/** @var MockObject&DebugMode */
	private MockObject $debug_mode;

	protected function setUp(): void {
		$this->view_factory = $this->createMock( ViewFactory::class );
		$this->debug_mode   = $this->createMock( DebugMode::class );
	}

	public function test_it_can_render_static_view(): void {
		$view = new SimpleView( 'tests/php/Fixture/views/plugin/static-view.php', $this->view_factory );

		$this->assertEquals( '<p>Rendering works.</p>', $this->normalize( $view->render() ) );
	}

	public function test_it_can_render_with_context(): void {
		$view = new SimpleView( 'tests/php/Fixture/views/plugin/dynamic-view.php', $this->view_factory );

		$result = $view->render( [ 'some_value' => 'perfectly' ] );

		$this->assertEquals(
			'<p>Rendering works with context: perfectly.</p>',
			$this->normalize( $result )
		);
	}

	public function test_it_escapes_context_values_by_default(): void {
		$view = new SimpleView( 'tests/php/Fixture/views/plugin/dynamic-view.php', $this->view_factory );

		$result = $view->render( [ 'some_value' => '<script>alert("XSS")</script>' ] );

		$this->assertStringContainsString(
			'&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
			$result
		);
	}

	public function test_it_can_access_raw_context_values(): void {
		$view = new SimpleView( 'tests/php/Fixture/views/plugin/dynamic-view.php', $this->view_factory );
		$view->render( [ 'some_value' => '<strong>raw</strong>' ] );

		$this->assertEquals( '<strong>raw</strong>', $view->raw( 'some_value' ) );
	}

	public function test_it_can_render_partial_views(): void {
		$partial_view = new SimpleView( 'tests/php/Fixture/views/plugin/partial.php', $this->view_factory );
		$this->view_factory->method( 'create' )
			->willReturn( $partial_view );

		$view = new SimpleView( 'tests/php/Fixture/views/plugin/view-with-partial.php', $this->view_factory );

		$result = $view->render( [ 'some_value' => 'nicely' ] );

		$this->assertStringContainsString( 'nicely', $result );
	}

	public function test_it_throws_on_invalid_path(): void {
		$this->expectException( InvalidPath::class );

		new SimpleView( 'non/existent/path', $this->view_factory );
	}

	public function test_it_throws_on_failed_view_load(): void {
		$this->expectException( FailedToLoadView::class );

		$view = new SimpleView( 'tests/php/Fixture/views/broken-view.php', $this->view_factory );
		$view->render();
	}

	public function test_it_throws_on_invalid_context_property_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$this->expectException( InvalidContextProperty::class );

		$view = new SimpleView(
			'tests/php/Fixture/views/dynamic-view.php',
			$this->view_factory,
			$this->debug_mode
		);
		$view->render( [ 'some_value' => '42' ] );

		$_ = $view->nonexistent_property;
	}

	public function test_it_returns_empty_string_for_missing_property_in_production(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( false );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/dynamic-view.php',
			$this->view_factory,
			$this->debug_mode
		);
		$view->render();

		$this->assertEquals( '', $view->nonexistent_property );
	}

	public function test_it_returns_null_for_raw_missing_property_in_production(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( false );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/dynamic-view.php',
			$this->view_factory,
			$this->debug_mode
		);
		$view->render();

		$this->assertNull( $view->raw( 'nonexistent_property' ) );
	}

	public function test_it_handles_non_string_context_values(): void {
		$view = new SimpleView( 'tests/php/Fixture/views/plugin/dynamic-view.php', $this->view_factory );

		$object = new class() {
			public function __toString(): string {
				return '42';
			}
		};

		$result = $view->render( [ 'some_value' => $object ] );

		$this->assertStringContainsString( 'Rendering works with context: 42', $result );
	}

	public function test_it_adds_php_extension_if_missing(): void {
		$view = new SimpleView( 'tests/php/Fixture/views/plugin/static-view', $this->view_factory );

		$this->assertEquals( '<p>Rendering works.</p>', $this->normalize( $view->render() ) );
	}

	public function test_it_preserves_parent_context_in_partial_views(): void {
		$partial_view = new SimpleView( 'tests/php/Fixture/views/plugin/partial.php', $this->view_factory );
		$this->view_factory->method( 'create' )
			->willReturn( $partial_view );

		$view = new SimpleView( 'tests/php/Fixture/views/plugin/view-with-partial.php', $this->view_factory );

		$context = [ 'some_value' => 'shared context' ];
		$result  = $view->render( $context );

		$this->assertStringContainsString( 'shared context', $result );
	}

	/**
	 * Helper function to normalize output so tests can avoid flaky behavior.
	 *
	 * @param string $output The string to normalize.
	 * @return string The normalized string.
	 */
	private function normalize( string $output ): string {
		// Right now, Patchwork seems to have a bug and injects code in some of the stream wrappers.
		// See https://github.com/antecedent/patchwork/issues/151.
		// This piece of logic can be removed once the above bug was fixed.
		$output = str_replace( ';\Patchwork\CodeManipulation\Stream::reinstateWrapper();', '', $output );

		return trim( $output );
	}
}
