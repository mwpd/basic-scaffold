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
	DefaultRegistrationPriority,
	Delayed,
	Registerable,
	Service,
	ViewFactory
};

/**
 * Sample loop service.
 */
final class SampleLoopService implements Service, Registerable, Conditional, Delayed {

	// Nothing else on the action needs to run before or after this service, so
	// it registers at the default priority.
	use DefaultRegistrationPriority;

	/**
	 * We only want to register this service once WordPress has run the main
	 * query, as is_needed() asks whether the request is for a singular view,
	 * which only has an answer from then on.
	 *
	 * @var non-empty-string
	 */
	private const REGISTRATION_HOOK = 'wp';

	/**
	 * View factory.
	 */
	private ViewFactory $view_factory;

	/**
	 * Main loop, asked which post is being rendered.
	 */
	private MainLoop $main_loop;

	/**
	 * Check whether the conditional service is currently needed.
	 *
	 * @return bool Whether the conditional service is needed.
	 */
	public static function is_needed(): bool {
		/*
		 * We only load this sample service on the frontend when a singular post
		 * is shown.
		 * If this conditional returns false, the service is never even
		 * instantiated.
		 */
		return \is_singular();
	}

	/**
	 * Get the action to use for registering the service.
	 *
	 * @return non-empty-string Registration action to use.
	 */
	public static function get_registration_action(): string {
		return self::REGISTRATION_HOOK;
	}

	/**
	 * Instantiate a SampleLoopService object.
	 *
	 * @param ViewFactory $view_factory View factory to use for instantiating
	 *                                  the views.
	 * @param MainLoop    $main_loop    Main loop to ask which post is being
	 *                                  rendered.
	 */
	public function __construct( ViewFactory $view_factory, MainLoop $main_loop ) {
		/*
		 * We request a view factory from the injector so that we can create a
		 * new view to be rendered when we want to show our sample notice.
		 */
		$this->view_factory = $view_factory;

		/*
		 * We do not ask for the current post here. The service is built once,
		 * at the wp action, but the_content runs many times after that, and
		 * not only for the queried post. So the service receives the main loop
		 * instead, and asks it each time the filter runs.
		 */
		$this->main_loop = $main_loop;
	}

	/**
	 * Register the service.
	 */
	public function register(): void {
		/*
		 * The register method now hooks our actual sample functionality into
		 * the WordPress execution flow.
		 */
		\add_filter( 'the_content', [ $this, 'prepend_post_header' ] );
	}

	/**
	 * Prepend a post header to the content of the queried post.
	 *
	 * WordPress also runs the_content for other posts on the page, such as
	 * those of a related-posts block or a secondary loop, for the posts of a
	 * feed, and to build an excerpt. Their content is returned unchanged.
	 *
	 * @param string $content The content to be filtered.
	 * @return string Filtered content, prepended with a post header when it is
	 *                the content of the queried post.
	 */
	public function prepend_post_header( string $content ): string {
		if ( ! $this->main_loop->is_rendering_queried_post() ) {
			return $content;
		}

		/*
		 * As we already have an instance of the view factory available, it is
		 * now easy to create a new view and render it.
		 *
		 * If the view fails, debug mode (WP_DEBUG) surfaces the exception. In
		 * production, the header renders as an empty string, so the filter
		 * still returns the post content, just without the header. As in
		 * SampleBackendService, this covers failures of the view only.
		 */
		$post_header = $this->view_factory->create( 'views/test-loop-service' )
											->render( [ 'post' => $this->main_loop->queried_post() ] );

		return $post_header . $content;
	}
}
