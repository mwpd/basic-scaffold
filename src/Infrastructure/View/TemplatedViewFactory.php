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

namespace MWPD\BasicScaffold\Infrastructure\View;

use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\Service\WordPressDebugMode;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use Throwable;

/**
 * A factory to create templated views.
 *
 * When no locations are provided, the first create() call resolves and keeps
 * the defaults in this exact order:
 *  1. child theme folder
 *  2. parent theme folder
 *  3. plugin folder
 */
final class TemplatedViewFactory implements Service, ViewFactory {

	/**
	 * Array of locations to use; empty until first use when no locations were passed.
	 *
	 * @var array<string>
	 */
	private array $locations;

	/**
	 * Debug mode instance to use.
	 */
	private DebugMode $debug_mode;

	/**
	 * Instantiate a TemplatedViewFactory object.
	 *
	 * @param array<string> $locations  Locations to use unchanged, or empty for defaults on first use.
	 * @param ?DebugMode    $debug_mode Debug mode instance to use, for the factory and the views it
	 *                                  creates. Optional, defaults to WordPressDebugMode.
	 */
	public function __construct( array $locations = [], ?DebugMode $debug_mode = null ) {
		$this->locations  = $locations;
		$this->debug_mode = $debug_mode ?? new WordPressDebugMode();
	}

	/**
	 * Create a new view object for a given relative path.
	 *
	 * Resolve default locations on the first call only, once theme state is needed.
	 *
	 * @param string $relative_path Relative path to create the view for.
	 *
	 * @return View Instantiated view object, or a view that renders nothing if
	 *              the view could not be created outside of debug mode.
	 * @throws Throwable If the view could not be created (in debug mode).
	 */
	public function create( string $relative_path ): View {
		try {
			if ( $this->locations === [] ) {
				$this->locations = $this->get_default_locations();
			}

			return new TemplatedView( $relative_path, $this, $this->locations, $this->debug_mode );
		} catch ( Throwable $throwable ) {
			/*
			 * A view that cannot be created, for example because no location
			 * has its template, fails like one that cannot be rendered: loudly
			 * in debug mode, with the original exception, and with an empty
			 * place on the page in production. The factory returns a View
			 * either way, so the code that renders it is the same in both.
			 */
			if ( $this->debug_mode->is_debug_mode() ) {
				throw $throwable;
			}

			return new EmptyView();
		}
	}

	/**
	 * Get the default locations for the templated view.
	 *
	 * The factory asks for them once, on the first create() call, and keeps them on
	 * the instance. They are not cached across instances, so every factory
	 * reflects the theme that is active when it first creates a view.
	 *
	 * @return array<string> Array of default locations.
	 */
	private function get_default_locations(): array {
		// We wrap the WP functions here to not make the code directly rely
		// on WordPress being loaded here.
		// This makes the code more flexible and testing easier.
		return ( function_exists( 'get_stylesheet_directory' )
				&& function_exists( 'get_template_directory' ) )
			? [
				\get_stylesheet_directory(),
				\get_template_directory(),
				\dirname( __DIR__, 3 ),
			]
			: [ \dirname( __DIR__, 3 ) ];
	}
}
