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
	private $locations;

	/**
	 * Instantiate a TemplatedView object.
	 *
	 * @param string        $path         Path to the view file to render.
	 * @param ViewFactory   $view_factory View factory instance to use.
	 * @param array<string> $locations    Optional. Array of locations to use.
	 */
	public function __construct(
		string $path,
		ViewFactory $view_factory,
		array $locations = []
	) {
		$this->locations = array_map( [ $this, 'ensure_trailing_slash' ], $locations );
		parent::__construct( $path, $view_factory );
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
	 * @param string $path Path to validate.
	 *
	 * @return string Validated Path.
	 * @throws InvalidPath If an invalid path was passed into the View.
	 */
	protected function validate( string $path ): string {
		$path = $this->check_extension( $path, self::VIEW_EXTENSION );

		foreach ( $this->get_locations( $path ) as $location ) {
			if ( \is_readable( $location ) ) {
				return $location;
			}
		}

		if ( ! \is_readable( $path ) ) {
			throw InvalidPath::from_path( $path );
		}

		return $path;
	}

	/**
	 * Get the possible locations for the view.
	 *
	 * @param string $path Path of the view to get the locations for.
	 *
	 * @return array<string> Array of possible locations.
	 */
	private function get_locations( string $path ): array {
		return array_map(
			fn( string $location ): string => $location . $path,
			$this->locations
		);
	}
}
