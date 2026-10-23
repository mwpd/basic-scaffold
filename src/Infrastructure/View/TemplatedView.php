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

use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;

/**
 * A templated variation of the simplified view object.
 *
 * It has an ordered list of locations and traverses these until it finds a
 * matching view.
 */
final class TemplatedView extends SimpleView {

	/**
	 * Array of locations to use.
	 *
	 * @var array<string>
	 */
	private array $locations;

	/**
	 * Instantiate a TemplatedView object.
	 *
	 * @param string        $path         Path to the view file to render.
	 * @param ViewFactory   $view_factory View factory instance to use.
	 * @param array<string> $locations    Optional. Array of locations to use.
	 * @param ?DebugMode    $debug_mode   Debug mode instance to use. Optional, defaults to WordPressDebugMode.
	 * @throws InvalidPath If an invalid Path was passed into the View.
	 */
	public function __construct(
		string $path,
		ViewFactory $view_factory,
		array $locations = [],
		?DebugMode $debug_mode = null
	) {
		$this->locations = array_map( [ $this, 'ensure_trailing_slash' ], $locations );
		parent::__construct( $path, $view_factory, $debug_mode );
	}

	/**
	 * Add a location to the templated view.
	 *
	 * @param string $location Location to add.
	 * @return self Modified templated view.
	 */
	public function add_location( string $location ): self {
		$this->locations[] = $this->ensure_trailing_slash( $location );

		return $this;
	}

	/**
	 * Validate a path.
	 *
	 * The path is looked up in each location in turn, and the first readable
	 * match wins. A path that matches no location is accepted as it is. Either
	 * way, the file must lie inside one of the locations (see ViewLocations),
	 * so `..` segments cannot reach a file the locations do not cover.
	 *
	 * @param string $path Path to validate.
	 *
	 * @return string Validated path, in its canonical form.
	 * @throws InvalidPath If an invalid path was passed into the View.
	 */
	protected function validate( string $path ): string {
		$path      = $this->check_extension( $path, self::VIEW_EXTENSION );
		$locations = new ViewLocations( $this->locations );

		foreach ( $this->locations as $location ) {
			if ( \is_readable( $location . $path ) ) {
				return $locations->confine( $location . $path );
			}
		}

		if ( ! \is_readable( $path ) ) {
			throw InvalidPath::from_path( $path );
		}

		return $locations->confine( $path );
	}
}
