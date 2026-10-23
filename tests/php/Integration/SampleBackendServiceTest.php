<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\SampleSubsystem\SampleBackendService;

/**
 * The backend sample renders its real template with explicit raw escaping.
 */
final class SampleBackendServiceTest extends TestCase {

	protected function set_up() {
		parent::set_up();

		Functions\stubEscapeFunctions();
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'did_action' )->justReturn( 0 );
		Functions\when( 'get_stylesheet_directory' )->justReturn( '/nonexistent/child-theme' );
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/parent-theme' );
	}

	/**
	 * @dataProvider request_contexts
	 */
	public function test_it_is_needed_only_in_admin_requests_outside_ajax(
		bool $is_admin,
		bool $doing_ajax,
		bool $expected
	): void {
		Functions\when( 'is_admin' )->justReturn( $is_admin );
		Functions\when( 'wp_doing_ajax' )->justReturn( $doing_ajax );

		$this->assertSame( $expected, SampleBackendService::is_needed() );
	}

	/**
	 * @return array<string, array{bool, bool, bool}>
	 */
	public static function request_contexts(): array {
		return [
			'admin'         => [ true, false, true ],
			'frontend'      => [ false, false, false ],
			'admin ajax'    => [ true, true, false ],
			'frontend ajax' => [ false, true, false ],
		];
	}

	public function test_it_renders_the_backend_notice_from_the_composition_root(): void {
		$plugin = new BasicScaffoldPlugin();
		$plugin->register_services();

		$service = $plugin->get_container()->get( BasicScaffoldPlugin::SAMPLE_BACKEND_SERVICE_ID );
		$this->assertInstanceOf( SampleBackendService::class, $service );
		$this->assertNotFalse( has_action( 'admin_notices', [ $service, 'render_notice' ] ) );

		$this->expectOutputRegex( '~Hello World!.*<b>MWPD Boilerplate</b>.*Raw value: <b>MWPD Boilerplate</b>~s' );
		$service->render_notice();
	}

	public function test_the_backend_template_escapes_the_raw_plugin_name_for_html_text(): void {
		$factory = new TemplatedViewFactory();
		$output  = $factory->create( 'views/test-backend-service' )->render( [ 'plugin' => '<em>Plugin</em>' ] );

		$this->assertStringContainsString( 'Raw value: <b>&lt;em&gt;Plugin&lt;/em&gt;</b>', $output );
		$this->assertStringNotContainsString( '<em>Plugin</em>', $output );
	}
}
