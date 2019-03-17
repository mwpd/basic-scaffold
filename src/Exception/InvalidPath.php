<?php declare( strict_types=1 );

/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

namespace MWPD\BasicScaffold\Exception;

use InvalidArgumentException;

class InvalidPath
	extends InvalidArgumentException
	implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for a file that is not accessible
	 * or not readable.
	 *
	 * @param string $path Path of the file that is not accessible or not
	 *                     readable.
	 *
	 * @return static
	 */
	public static function from_path( $path ) {
		$message = \sprintf(
			'The view path "%s" is not accessible or readable.',
			$path
		);

		return new static( $message );
	}
}
