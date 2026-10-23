<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Exception\FailedToFindPost;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\SampleSubsystem\SampleLoopService;
use MWPD\BasicScaffold\Tests\ViewHelper;
use stdClass;
use Throwable;
use WP_Post;

/**
 * The sample loop service on a singular view, built from the real
 * composition root, exercising the views/test-loop-service template.
 */
final class SampleLoopServiceTest extends TestCase {

	protected function set_up() {
		parent::set_up();

		Functions\stubEscapeFunctions();
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'did_action' )->alias( static fn( string $action ): bool => $action === 'wp' );
		Functions\when( 'get_stylesheet_directory' )->justReturn( '/nonexistent/child-theme' );
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/parent-theme' );
	}

	public function test_it_prepends_an_escaped_header_to_the_content_of_the_current_post(): void {
		$fields             = new stdClass();
		$fields->post_title = 'A <em>title</em>';
		$fields->post_date  = '2026-10-22 12:00:00';
		Functions\when( 'get_post' )->justReturn( new WP_Post( $fields ) );

		$plugin = new BasicScaffoldPlugin();
		$plugin->register_services();

		$container = $plugin->get_container();
		$this->assertTrue( $container->has( BasicScaffoldPlugin::SAMPLE_LOOP_SERVICE_ID ) );

		$service = $container->get( BasicScaffoldPlugin::SAMPLE_LOOP_SERVICE_ID );
		$this->assertInstanceOf( SampleLoopService::class, $service );
		$this->assertNotFalse( has_filter( 'the_content', [ $service, 'prepend_post_header' ] ) );

		$output = $service->prepend_post_header( '<p>Content.</p>' );

		$this->assertStringContainsString( 'Post title: A &lt;em&gt;title&lt;/em&gt;', $output );
		$this->assertStringContainsString( 'Post date:  2026-10-22 12:00:00', $output );
		$this->assertStringEndsWith( '<p>Content.</p>', $output );
	}

	public function test_it_fails_with_a_dedicated_exception_when_there_is_no_current_post(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$this->expectException( FailedToFindPost::class );

		( new BasicScaffoldPlugin() )->register_services();
	}

	/**
	 * @dataProvider failing_templates
	 */
	public function test_a_failing_header_leaves_the_content_unchanged_in_production( string $location ): void {
		$service = new SampleLoopService(
			new TemplatedViewFactory( [ $location ], $this->debug_mode( false ) ),
			$this->post()
		);

		$this->assertSame( '<p>Content.</p>', $service->prepend_post_header( '<p>Content.</p>' ) );
	}

	/**
	 * @dataProvider failing_templates
	 *
	 * @param class-string<Throwable> $expected Exception the failure surfaces as.
	 */
	public function test_a_failing_header_surfaces_with_its_cause_in_debug_mode(
		string $location,
		string $expected
	): void {
		$service = new SampleLoopService(
			new TemplatedViewFactory( [ $location ], $this->debug_mode( true ) ),
			$this->post()
		);

		try {
			$service->prepend_post_header( '<p>Content.</p>' );
			$this->fail( 'A failing header should throw in debug mode.' );
		} catch ( Throwable $exception ) {
			$this->assertInstanceOf( $expected, $exception );
			$this->assert_sample_template_cause( $exception );
		}
	}

	/**
	 * The two ways the header's view can fail: its template is missing from
	 * every location, or it throws while rendering.
	 *
	 * @return array<string, array{string, class-string<Throwable>}>
	 */
	public static function failing_templates(): array {
		return [
			'missing template'  => [ ViewHelper::CHILD_THEME_FOLDER, InvalidPath::class ],
			'throwing template' => [ ViewHelper::VIEWS_FOLDER . 'failing', FailedToLoadView::class ],
		];
	}

	/**
	 * Create a post for the sample to render a header for.
	 *
	 * @return WP_Post Post with a title and a date.
	 */
	private function post(): WP_Post {
		$fields             = new stdClass();
		$fields->post_title = 'A title';
		$fields->post_date  = '2026-10-22 12:00:00';

		return new WP_Post( $fields );
	}
}
