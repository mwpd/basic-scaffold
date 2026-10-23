<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\SampleSubsystem\MainLoop;
use MWPD\BasicScaffold\SampleSubsystem\QueriedPostHeader;
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

	/**
	 * ID of the post the main query asked for.
	 */
	private const QUERIED_POST_ID = 42;

	protected function set_up() {
		parent::set_up();

		Functions\stubEscapeFunctions();
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'did_action' )->alias( static fn( string $action ): bool => $action === 'wp' );
		Functions\when( 'get_stylesheet_directory' )->justReturn( '/nonexistent/child-theme' );
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/parent-theme' );

		// The main loop of a singular view, on the post the query asked for.
		Functions\when( 'in_the_loop' )->justReturn( true );
		Functions\when( 'is_main_query' )->justReturn( true );
		Functions\when( 'is_feed' )->justReturn( false );
		// Inside the_content, WordPress reports that filter as running.
		Functions\when( 'doing_filter' )->alias( static fn( string $hook ): bool => $hook === 'the_content' );
		Functions\when( 'get_the_ID' )->justReturn( self::QUERIED_POST_ID );
		Functions\when( 'get_queried_object_id' )->justReturn( self::QUERIED_POST_ID );
		Functions\when( 'get_queried_object' )->justReturn( $this->post( 'A <em>title</em>' ) );
	}

	public function test_it_prepends_an_escaped_header_when_the_main_loop_renders_the_queried_post(): void {
		$service = $this->registered_service();

		$this->assertNotFalse( has_filter( 'the_content', [ $service, 'prepend_post_header' ] ) );

		$output = $service->prepend_post_header( '<p>Content.</p>' );

		$this->assertStringContainsString( 'Post title: A &lt;em&gt;title&lt;/em&gt;', $output );
		$this->assertStringContainsString( 'Post date:  2026-10-22 12:00:00', $output );
		$this->assertStringEndsWith( '<p>Content.</p>', $output );
	}

	/**
	 * The service is built once, at wp, but the_content runs for more than the
	 * queried post: for other posts in a related-posts block or a secondary
	 * loop, in feeds, and when WordPress builds an excerpt from the content.
	 * Only asking at call time tells those apart from the queried post.
	 *
	 * @dataProvider other_renderings
	 *
	 * @param string   $telltale WordPress function that tells this rendering apart.
	 * @param callable $answer   What it answers during this rendering.
	 */
	public function test_it_leaves_other_renderings_unchanged( string $telltale, callable $answer ): void {
		$service = $this->registered_service();

		Functions\when( $telltale )->alias( $answer );

		$this->assertSame( '<p>Other content.</p>', $service->prepend_post_header( '<p>Other content.</p>' ) );
	}

	/**
	 * The renderings of the_content that are not the queried post in the main
	 * loop, and the WordPress function each one shows in.
	 *
	 * @return array<string, array{string, callable}>
	 */
	public static function other_renderings(): array {
		return [
			'another post on the page' => [ 'get_the_ID', static fn(): int => 7 ],
			'a feed'                   => [ 'is_feed', static fn(): bool => true ],
			'an excerpt being built'   => [
				'doing_filter',
				static fn( string $hook ): bool => \in_array( $hook, [ 'get_the_excerpt', 'the_content' ], true ),
			],
		];
	}

	public function test_it_leaves_the_content_unchanged_when_the_main_loop_renders_something_else(): void {
		$main_loop = $this->createMock( MainLoop::class );
		$main_loop->method( 'is_rendering_queried_post' )->willReturn( false );
		$main_loop->expects( $this->never() )->method( 'queried_post' );

		$service = new SampleLoopService(
			new TemplatedViewFactory( [ ViewHelper::VIEWS_FOLDER . 'failing' ], $this->debug_mode( true ) ),
			$main_loop,
			new QueriedPostHeader( $main_loop )
		);

		// The failing template would throw in debug mode, so the content
		// coming back unchanged also proves no header was rendered.
		$this->assertSame( '<p>Content.</p>', $service->prepend_post_header( '<p>Content.</p>' ) );
	}

	/**
	 * @dataProvider failing_templates
	 */
	public function test_a_failing_header_leaves_the_content_unchanged_in_production( string $location ): void {
		$main_loop = $this->main_loop_rendering( $this->post( 'A title' ) );
		$service   = new SampleLoopService(
			new TemplatedViewFactory( [ $location ], $this->debug_mode( false ) ),
			$main_loop,
			new QueriedPostHeader( $main_loop )
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
		$main_loop = $this->main_loop_rendering( $this->post( 'A title' ) );
		$service   = new SampleLoopService(
			new TemplatedViewFactory( [ $location ], $this->debug_mode( true ) ),
			$main_loop,
			new QueriedPostHeader( $main_loop )
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
	 * Register the plugin's services and get the sample loop service.
	 *
	 * @return SampleLoopService Service built from the composition root.
	 */
	private function registered_service(): SampleLoopService {
		$plugin = new BasicScaffoldPlugin();
		$plugin->register_services();

		$container = $plugin->get_container();
		$this->assertTrue( $container->has( BasicScaffoldPlugin::SAMPLE_LOOP_SERVICE_ID ) );

		$service = $container->get( BasicScaffoldPlugin::SAMPLE_LOOP_SERVICE_ID );
		$this->assertInstanceOf( SampleLoopService::class, $service );

		return $service;
	}

	/**
	 * Create a main loop that is rendering the given queried post.
	 *
	 * @param WP_Post $post Queried post.
	 * @return MainLoop Main loop double.
	 */
	private function main_loop_rendering( WP_Post $post ): MainLoop {
		$main_loop = $this->createMock( MainLoop::class );
		$main_loop->method( 'is_rendering_queried_post' )->willReturn( true );
		$main_loop->method( 'queried_post' )->willReturn( $post );

		return $main_loop;
	}

	/**
	 * Create a post for the sample to render a header for.
	 *
	 * @param string $title Title of the post.
	 * @return WP_Post Post with a title and a date.
	 */
	private function post( string $title ): WP_Post {
		$fields             = new stdClass();
		$fields->post_title = $title;
		$fields->post_date  = '2026-10-22 12:00:00';

		return new WP_Post( $fields );
	}
}
