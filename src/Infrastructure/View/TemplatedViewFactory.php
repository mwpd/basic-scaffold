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
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;

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
	 * Instantiate a TemplatedViewFactory object.
	 *
	 * @param array<string> $locations Locations to use unchanged, or empty for defaults on first use.
	 */
	public function __construct( array $locations = [] ) {
		$this->locations = $locations;
	}

	/**
	 * Create a new view object for a given relative path.
	 *
	 * Resolve default locations on the first call only, once theme state is needed.
	 *
	 * @param string $relative_path Relative path to create the view for.
	 *
	 * @return TemplatedView Instantiated view object.
	 */
	public function create( string $relative_path ): View {
		if ( $this->locations === [] ) {
			$this->locations = $this->get_default_locations();
		}

		return new TemplatedView( $relative_path, $this, $this->locations );
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
