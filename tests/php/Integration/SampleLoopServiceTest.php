<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Exception\FailedToFindPost;
use MWPD\BasicScaffold\SampleSubsystem\SampleLoopService;
use stdClass;
use WP_Post;

/**
 * The sample loop service on a singular view, built from the real
 * composition root.
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
}
