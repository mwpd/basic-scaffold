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

namespace MWPD\BasicScaffold\SampleSubsystem;

use MWPD\BasicScaffold\Infrastructure\{
	Conditional,
	Registerable,
	Service,
	ViewFactory
};

/**
 * This sample service only renders a silly "Hello World" notice in the admin
 * backend.
 *
 * It is meant to illustrate how to hook services into the plugin flow
 * and how to have their dependencies by injected.
 *
 * Note that the dependency here is actually an interface, not a class. We can
 * still just transparently use it though.
 */
final class SampleBackendService implements Service, Registerable, Conditional {

	/**
	 * View factory.
	 */
	private ViewFactory $view_factory;

	/**
	 * Check whether the conditional service is currently needed.
	 *
	 * @return bool Whether the conditional service is needed.
	 */
	public static function is_needed(): bool {
		/*
		 * We only load this sample service on the admin backend.
		 * If this conditional returns false, the service is never even
		 * instantiated.
		 */
		return \is_admin() && ! \wp_doing_ajax();
	}

	/**
	 * Instantiate a SampleBackendService object.
	 *
	 * @param ViewFactory $view_factory View factory to use for instantiating
	 *                                  the views.
	 */
	public function __construct( ViewFactory $view_factory ) {
		/*
		 * We request a view factory from the injector so that we can create a
		 * new view to be rendered when we want to show our sample notice.
		 */
		$this->view_factory = $view_factory;
	}

	/**
	 * Register the service.
	 */
	public function register(): void {
		/*
		 * The register method now hooks our actual sample functionality into
		 * the WordPress execution flow.
		 */
		\add_action( 'admin_notices', [ $this, 'render_notice' ], 10, 0 );
	}

	/**
	 * Render the admin notice.
	 */
	public function render_notice(): void {
		/*
		 * As we already have an instance of the view factory available, it is
		 * now easy to create a new view and render it.
		 */
		echo $this->view_factory->create( 'views/test-backend-service' ) // phpcs:ignore WordPress.Security.EscapeOutput,Generic.Files.LineLength
								->render( [ 'plugin' => 'MWPD Boilerplate' ] );
	}
}
