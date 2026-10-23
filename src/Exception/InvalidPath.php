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

namespace MWPD\BasicScaffold\Exception;

use InvalidArgumentException;

/**
 * Exception for invalid paths.
 */
final class InvalidPath extends InvalidArgumentException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for a file that is not accessible
	 * or not readable.
	 *
	 * @param string $path Path of the file that is not accessible or not
	 *                     readable.
	 */
	public static function from_path( $path ): self {
		$message = \sprintf(
			'The view path "%s" is not accessible or readable.',
			$path
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a view path that resolves to
	 * a file outside of the locations views may be loaded from.
	 *
	 * @param string        $path      Path of the view as it was requested.
	 * @param array<string> $locations Locations the view is confined to.
	 */
	public static function for_path_outside_locations( string $path, array $locations ): self {
		$message = \sprintf(
			'The view path "%s" resolves to a file outside of the allowed view locations: "%s".',
			$path,
			\implode( '", "', $locations )
		);

		return new self( $message );
	}
}
