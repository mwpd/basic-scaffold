<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\FailedToEscapeValue;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidContextProperty;
use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Exception\InvalidViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\View\SimpleView;
use MWPD\BasicScaffold\Infrastructure\View\SimpleViewFactory;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;
use Error;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use stdClass;

final class SimpleViewTest extends TestCase {

	/** @var MockObject&ViewFactory */
	private MockObject $view_factory;

	/** @var MockObject&DebugMode */
	private MockObject $debug_mode;

	/**
	 * View file created outside the plugin root by a test, if any.
	 */
	private ?string $outside_view = null;

	/**
	 * Configuration naming the repository as the plugin root, as the
	 * composition root does for the scaffold itself.
	 */
	private ViewConfiguration $configuration;

	protected function setUp(): void {
		$this->view_factory  = $this->createMock( ViewFactory::class );
		$this->debug_mode    = $this->createMock( DebugMode::class );
		$this->configuration = new ViewConfiguration( ViewHelper::PLUGIN_ROOT, ViewHelper::OVERRIDE_DIRECTORY );
	}

	protected function tearDown(): void {
		if ( null !== $this->outside_view && \file_exists( $this->outside_view ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit tests run without WordPress.
			\unlink( $this->outside_view );
		}
	}

	public function test_it_can_render_static_view(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/static-view.php' );

		$this->assertEquals( '<p>Rendering works.</p>', $this->normalize( $view->render() ) );
	}

	public function test_it_can_render_with_context(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/dynamic-view.php' );

		$result = $view->render( [ 'some_value' => 'perfectly' ] );

		$this->assertEquals(
			'<p>Rendering works with context: perfectly.</p>',
			$this->normalize( $result )
		);
	}

	public function test_it_escapes_context_values_by_default(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/dynamic-view.php' );

		$result = $view->render( [ 'some_value' => '<script>alert("XSS")</script>' ] );

		$this->assertStringContainsString(
			'&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
			$result
		);
	}

	public function test_it_can_access_raw_context_values(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/dynamic-view.php' );
		$view->render( [ 'some_value' => '<strong>raw</strong>' ] );

		$this->assertEquals( '<strong>raw</strong>', $view->raw( 'some_value' ) );
	}

	public function test_it_supports_isset_and_empty_on_context_values(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/isset-view.php' );

		$cases = [
			'isset: yes, empty: no'  => 'present',
			'isset: yes, empty: yes' => '',
			'isset: no, empty: yes'  => null,
		];

		foreach ( $cases as $expected => $value ) {
			$this->assertEquals( $expected, $this->normalize( $view->render( [ 'some_value' => $value ] ) ) );
		}
	}

	public function test_isset_on_a_missing_context_value_does_not_throw_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/isset-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		$this->assertEquals( 'isset: no, empty: yes', $this->normalize( $view->render() ) );
	}

	public function test_it_can_render_partial_views(): void {
		$partial_view = $this->make_view( 'tests/php/Fixture/views/plugin/partial.php' );
		$this->view_factory->method( 'create' )
			->willReturn( $partial_view );

		$view = $this->make_view( 'tests/php/Fixture/views/plugin/view-with-partial.php' );

		$result = $view->render( [ 'some_value' => 'nicely' ] );

		$this->assertStringContainsString( 'nicely', $result );
	}

	public function test_it_throws_on_invalid_path(): void {
		$this->expectException( InvalidPath::class );

		$this->make_view( 'non/existent/path' );
	}

	public function test_it_throws_a_named_exception_without_a_configuration(): void {
		$this->expectException( InvalidViewConfiguration::class );
		$this->expectExceptionMessage( '"tests/php/Fixture/views/plugin/static-view" is resolved relative to' );

		new SimpleView( 'tests/php/Fixture/views/plugin/static-view', $this->view_factory );
	}

	public function test_it_resolves_relative_to_the_configured_plugin_root(): void {
		$view = new SimpleView(
			'views/probe',
			$this->view_factory,
			null,
			new ViewConfiguration( ViewHelper::SCOPED_PLUGIN_FOLDER, ViewHelper::OVERRIDE_DIRECTORY )
		);

		$this->assertStringStartsWith( '<p>Probe comes from plugin.</p>', $view->render() );
	}

	public function test_it_escapes_single_quotes_in_context_values(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/dynamic-view.php' );

		$result = $view->render( [ 'some_value' => "It's a 'quote'" ] );

		$this->assertStringContainsString( 'It&#039;s a &#039;quote&#039;', $result );
		$this->assertStringNotContainsString( "'", $result );
	}

	public function test_it_substitutes_invalid_code_units_in_context_values(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/dynamic-view.php' );

		$result = $view->render( [ 'some_value' => "broken \xC3\x28 byte" ] );

		$this->assertStringContainsString( "broken \u{FFFD}( byte", $result );
	}

	public function test_it_rejects_a_path_that_leaves_the_plugin_root(): void {
		$plugin_root = (string) \realpath( \dirname( __DIR__, 3 ) );
		$outside     = $this->create_outside_view();

		$this->expectException( InvalidPath::class );

		new SimpleView(
			\str_repeat( '../', \substr_count( $plugin_root, '/' ) ) . \ltrim( $outside, '/' ),
			$this->view_factory,
			null,
			$this->configuration
		);
	}

	public function test_it_accepts_parent_segments_that_stay_inside_the_plugin_root(): void {
		$view = new SimpleView(
			'tests/php/Fixture/views/child_theme/../plugin/static-view',
			$this->view_factory,
			null,
			$this->configuration
		);

		$this->assertEquals( '<p>Rendering works.</p>', $this->normalize( $view->render() ) );
	}

	public function test_a_failing_template_throws_with_its_cause_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/throwing-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		$level = \ob_get_level();

		try {
			$view->render();
			$this->fail( 'A failing template should throw in debug mode.' );
		} catch ( FailedToLoadView $exception ) {
			$previous = $exception->getPrevious();
			$this->assertInstanceOf( RuntimeException::class, $previous );
			$this->assertSame( 'The template failed.', $previous->getMessage() );
		} finally {
			$after = \ob_get_level();
			$this->restore_buffer_level( $level );
		}

		$this->assertSame( $level, $after, 'The view leaked an output buffer.' );
	}

	public function test_a_template_with_a_php_error_throws_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = new SimpleView(
			'tests/php/Fixture/views/broken-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		try {
			$view->render();
			$this->fail( 'A template with a PHP error should throw in debug mode.' );
		} catch ( FailedToLoadView $exception ) {
			$this->assertInstanceOf( Error::class, $exception->getPrevious() );
		}
	}

	public function test_a_failing_template_is_the_cause_even_when_cleaning_up_after_it_fails_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/throwing-cleanup-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		$level = \ob_get_level();

		try {
			$view->render();
			$this->fail( 'A failing template should throw in debug mode.' );
		} catch ( FailedToLoadView $exception ) {
			$previous = $exception->getPrevious();
			$this->assertInstanceOf( LogicException::class, $previous );
			$this->assertSame( 'The template failed.', $previous->getMessage() );
		} finally {
			$after = \ob_get_level();
			$this->restore_buffer_level( $level );
		}

		$this->assertSame( $level, $after, 'The view leaked an output buffer.' );
	}

	/**
	 * @dataProvider data_failing_templates
	 */
	public function test_a_failing_template_renders_nothing_in_production( string $path ): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( false );

		$view = new SimpleView( $path, $this->view_factory, $this->debug_mode, $this->configuration );

		// The caller's own buffer, which the failing view must leave alone.
		$level = \ob_get_level();
		\ob_start();
		echo 'Output of the caller.';

		try {
			$result = $view->render();
			$after  = \ob_get_level();
			$caller = \ob_get_contents();
		} finally {
			$this->restore_buffer_level( $level );
		}

		$this->assertSame( '', $result );
		$this->assertSame( $level + 1, $after, 'The view changed the caller\'s buffer level.' );
		$this->assertSame( 'Output of the caller.', $caller );
	}

	/**
	 * Templates that fail while rendering, each in its own way.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_failing_templates(): array {
		return [
			'template that throws'                 => [ 'tests/php/Fixture/views/plugin/throwing-view.php' ],
			'template with a PHP error'            => [ 'tests/php/Fixture/views/broken-view.php' ],
			'output handler that throws'           => [ 'tests/php/Fixture/views/plugin/throwing-buffer-view.php' ],
			'template and cleanup that both throw' => [ 'tests/php/Fixture/views/plugin/throwing-cleanup-view.php' ],
		];
	}

	public function test_a_failing_partial_renders_nothing_inside_its_parent_in_production(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( false );

		$view = ( new SimpleViewFactory( $this->debug_mode, $this->configuration ) )
			->create( 'tests/php/Fixture/views/plugin/view-with-failing-partial' );

		$level  = \ob_get_level();
		$result = $view->render();

		$this->assertSame( $level, \ob_get_level(), 'The view leaked an output buffer.' );
		$this->assertStringContainsString( '<p>Before the partial.</p>', $result );
		$this->assertStringContainsString( '<p>After the partial.</p>', $result );
		$this->assertStringNotContainsString( 'Partial output of a failing view.', $result );
	}

	public function test_a_failing_partial_fails_its_parent_with_the_cause_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = ( new SimpleViewFactory( $this->debug_mode, $this->configuration ) )
			->create( 'tests/php/Fixture/views/plugin/view-with-failing-partial' );

		$level = \ob_get_level();

		try {
			$view->render();
			$this->fail( 'A failing partial should throw in debug mode.' );
		} catch ( FailedToLoadView $exception ) {
			$partial = $exception->getPrevious();
			$this->assertInstanceOf( FailedToLoadView::class, $partial );
			$this->assertInstanceOf( RuntimeException::class, $partial->getPrevious() );
			$this->assertSame( 'The template failed.', $partial->getPrevious()->getMessage() );
		} finally {
			$after = \ob_get_level();
			$this->restore_buffer_level( $level );
		}

		$this->assertSame( $level, $after, 'The view leaked an output buffer.' );
	}

	public function test_a_missing_partial_renders_nothing_inside_its_parent_in_production(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( false );

		$view = ( new SimpleViewFactory( $this->debug_mode, $this->configuration ) )
			->create( 'tests/php/Fixture/views/plugin/view-with-missing-partial' );

		$level  = \ob_get_level();
		$result = $view->render();

		$this->assertSame( $level, \ob_get_level(), 'The view leaked an output buffer.' );
		$this->assertStringContainsString( '<p>Before the partial.</p>', $result );
		$this->assertStringContainsString( '<p>After the partial.</p>', $result );
	}

	public function test_a_missing_partial_fails_its_parent_with_the_cause_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = ( new SimpleViewFactory( $this->debug_mode, $this->configuration ) )
			->create( 'tests/php/Fixture/views/plugin/view-with-missing-partial' );

		$level = \ob_get_level();

		try {
			$view->render();
			$this->fail( 'A missing partial should throw in debug mode.' );
		} catch ( FailedToLoadView $exception ) {
			$this->assertInstanceOf( InvalidPath::class, $exception->getPrevious() );
		} finally {
			$after = \ob_get_level();
			$this->restore_buffer_level( $level );
		}

		$this->assertSame( $level, $after, 'The view leaked an output buffer.' );
	}

	public function test_it_throws_on_invalid_context_property_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$this->expectException( InvalidContextProperty::class );

		$view = new SimpleView(
			'tests/php/Fixture/views/dynamic-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
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
			$this->debug_mode,
			$this->configuration
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
			$this->debug_mode,
			$this->configuration
		);
		$view->render();

		$this->assertNull( $view->raw( 'nonexistent_property' ) );
	}

	public function test_it_handles_non_string_context_values(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/dynamic-view.php' );

		$object = new class() {
			public function __toString(): string {
				return '42';
			}
		};

		$result = $view->render( [ 'some_value' => $object ] );

		$this->assertStringContainsString( 'Rendering works with context: 42', $result );
	}

	public function test_it_adds_php_extension_if_missing(): void {
		$view = $this->make_view( 'tests/php/Fixture/views/plugin/static-view' );

		$this->assertEquals( '<p>Rendering works.</p>', $this->normalize( $view->render() ) );
	}

	public function test_it_preserves_parent_context_in_partial_views(): void {
		$partial_view = $this->make_view( 'tests/php/Fixture/views/plugin/partial.php' );
		$this->view_factory->method( 'create' )
			->willReturn( $partial_view );

		$view = $this->make_view( 'tests/php/Fixture/views/plugin/view-with-partial.php' );

		$context = [ 'some_value' => 'shared context' ];
		$result  = $view->render( $context );

		$this->assertStringContainsString( 'shared context', $result );
	}

	public function test_it_throws_on_a_non_stringable_context_value_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/static-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);
		$view->render( [ 'some_value' => [ 'not', 'stringable' ] ] );

		$this->expectException( FailedToEscapeValue::class );

		$_ = $view->some_value;
	}

	public function test_a_non_stringable_context_value_is_the_cause_of_a_failed_render_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/dynamic-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		try {
			$view->render( [ 'some_value' => [ 'not', 'stringable' ] ] );
			$this->fail( 'Rendering a non-stringable value in debug mode should throw.' );
		} catch ( FailedToLoadView $exception ) {
			$this->assertInstanceOf( FailedToEscapeValue::class, $exception->getPrevious() );
		}
	}

	public function test_it_renders_nothing_for_a_non_stringable_context_value_in_production(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( false );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/dynamic-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		$this->assertEquals(
			'<p>Rendering works with context: .</p>',
			$this->normalize( $view->render( [ 'some_value' => [ 'not', 'stringable' ] ] ) )
		);
	}

	public function test_escaping_a_non_stringable_value_throws_in_debug_mode(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( true );

		$this->expectException( FailedToEscapeValue::class );

		$this->escape( new stdClass() );
	}

	public function test_escaping_a_non_stringable_value_returns_an_empty_string_in_production(): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( false );

		$this->assertSame( '', $this->escape( new stdClass() ) );
	}

	/**
	 * @dataProvider data_debug_modes
	 */
	public function test_it_keeps_the_output_of_a_buffer_the_template_leaves_open( bool $debug_mode ): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( $debug_mode );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/unclosed-buffer-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		$level = \ob_get_level();

		try {
			$result = $view->render();
			$after  = \ob_get_level();
		} finally {
			$this->restore_buffer_level( $level );
		}

		$this->assertSame( $level, $after, 'The view leaked an output buffer.' );
		$this->assertEquals( '<p>Left open.</p>', $this->normalize( $result ) );
	}

	/**
	 * @dataProvider data_debug_modes
	 */
	public function test_it_does_not_close_a_buffer_it_does_not_own( bool $debug_mode ): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( $debug_mode );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/closing-buffer-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		// The caller's own buffer, which the view must leave alone.
		$level = \ob_get_level();
		\ob_start();
		echo 'Output of the caller.';

		try {
			$result = $view->render();
			$after  = \ob_get_level();
			$caller = \ob_get_contents();
		} finally {
			$this->restore_buffer_level( $level );
		}

		$this->assertSame( $level + 1, $after, 'The view closed a buffer it does not own.' );
		$this->assertSame( 'Output of the caller.', $caller );
		$this->assertSame( '', $result );
	}

	/**
	 * @dataProvider data_debug_modes
	 */
	public function test_it_restores_the_buffer_level_when_flushing_a_template_buffer_fails( bool $debug_mode ): void {
		$this->debug_mode->method( 'is_debug_mode' )
			->willReturn( $debug_mode );

		$view = new SimpleView(
			'tests/php/Fixture/views/plugin/throwing-buffer-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		);

		$level     = \ob_get_level();
		$exception = null;

		try {
			$view->render();
		} catch ( FailedToLoadView $failed_to_load_view ) {
			$exception = $failed_to_load_view;
		} finally {
			$after = \ob_get_level();
			$this->restore_buffer_level( $level );
		}

		// Debug mode reports the failure; production renders nothing instead.
		if ( $debug_mode ) {
			$this->assertInstanceOf( FailedToLoadView::class, $exception );
		} else {
			$this->assertNull( $exception );
		}

		$this->assertSame( $level, $after, 'The view leaked an output buffer.' );
	}

	/**
	 * Both debug modes, for behaviour that must not depend on them.
	 *
	 * @return array<string, array{bool}>
	 */
	public static function data_debug_modes(): array {
		return [
			'debug mode on'  => [ true ],
			'debug mode off' => [ false ],
		];
	}

	/**
	 * Create a view relative to the repository root, with the default debug mode.
	 *
	 * @param string $path Path of the view, relative to the repository root.
	 */
	private function make_view( string $path ): SimpleView {
		return new SimpleView( $path, $this->view_factory, null, $this->configuration );
	}

	/**
	 * Escape a value through SimpleView::escape(), which templates and
	 * subclasses call.
	 *
	 * @param mixed $value Value to escape.
	 * @return string Escaped value.
	 */
	private function escape( $value ): string {
		$view = new class(
			'tests/php/Fixture/views/plugin/static-view.php',
			$this->view_factory,
			$this->debug_mode,
			$this->configuration
		) extends SimpleView {

			/**
			 * Escape a value through the view's escape().
			 *
			 * @param mixed $value Value to escape.
			 * @return string Escaped value.
			 */
			public function escape_value( $value ): string {
				return $this->escape( $value );
			}
		};

		return $view->escape_value( $value );
	}

	/**
	 * Close output buffers down to a level, discarding their content.
	 *
	 * A test that fails halfway must not leave buffers behind for the next.
	 *
	 * @param int $level Buffer level to return to.
	 */
	private function restore_buffer_level( int $level ): void {
		while ( \ob_get_level() > $level ) {
			\ob_end_clean();
		}
	}

	/**
	 * Create a readable view file outside the plugin root.
	 *
	 * Every fixture lives inside the plugin root, so the file that must stay
	 * out of reach is created in the system's temporary directory.
	 *
	 * @return string Canonical path of the created file.
	 */
	private function create_outside_view(): string {
		$file = \tempnam( \sys_get_temp_dir(), 'outside-view-' );
		$this->assertIsString( $file );

		$view = $file . '.php';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Unit tests run without WordPress.
		\rename( $file, $view );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Unit tests run without WordPress.
		\file_put_contents( $view, '<p>Outside the plugin.</p>' );

		$this->outside_view = (string) \realpath( $view );

		return $this->outside_view;
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
