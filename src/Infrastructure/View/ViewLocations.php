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

/**
 * The directories views may be loaded from.
 *
 * Rendering a view includes its file as PHP. A view path that could climb out
 * of these directories with `..` segments or a symbolic link would turn into a
 * local file inclusion as soon as any part of a view name came from input. So
 * every path is resolved to its canonical form and only accepted when it lies
 * inside one of the locations.
 */
final class ViewLocations {

	/**
	 * Locations as they were configured.
	 *
	 * @var array<string>
	 */
	private array $locations;

	/**
	 * Instantiate a ViewLocations object.
	 *
	 * @param array<string> $locations Directories views may be loaded from.
	 */
	public function __construct( array $locations ) {
		$this->locations = $locations;
	}

	/**
	 * Confine a readable path to the locations.
	 *
	 * @param string $path Readable path to confine.
	 *
	 * @return string Canonical form of the path, without `.` or `..` segments
	 *                or symbolic links.
	 * @throws InvalidPath If the path cannot be resolved, or resolves to a file
	 *                     outside of all locations.
	 */
	public function confine( string $path ): string {
		$canonical_path = \realpath( $path );

		if ( false === $canonical_path ) {
			throw InvalidPath::from_path( $path );
		}

		foreach ( $this->locations as $location ) {
			if ( $this->is_inside( $canonical_path, $location ) ) {
				return $canonical_path;
			}
		}

		throw InvalidPath::for_path_outside_locations( $path, $this->locations );
	}

	/**
	 * Check whether a canonical path lies inside a location.
	 *
	 * @param string $canonical_path Canonical path to check.
	 * @param string $location       Location the path should be inside of.
	 *
	 * @return bool Whether the path lies inside the location.
	 */
	private function is_inside( string $canonical_path, string $location ): bool {
		$canonical_location = \realpath( $location );

		// A location that does not exist cannot contain anything.
		if ( false === $canonical_location ) {
			return false;
		}

		// Compare against the location with a trailing separator, so that a
		// sibling such as `views-other` does not pass as being inside `views`.
		$prefix = \rtrim( $canonical_location, '/\\' ) . DIRECTORY_SEPARATOR;

		return 0 === \strpos( $canonical_path, $prefix );
	}
}
