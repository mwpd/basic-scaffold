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
 * Exception thrown when a service is invalid.
 */
final class InvalidArgument extends InvalidArgumentException implements BasicScaffoldException {

	use Stringify;

	/**
	 * Create a new instance of the exception for a service class name that is
	 * not recognized.
	 *
	 * @param mixed $name Name of the argument that was not recognized.
	 *
	 * @return static
	 */
	public static function from_name( $name ): self {
		$message = \sprintf(
			'The argument "%s" is not recognized and cannot be registered.',
			self::stringify( $name )
		);

		return new self( $message );
	}
}
