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
final class InvalidService extends InvalidArgumentException implements BasicScaffoldException {

	use Stringify;

	/**
	 * Create a new instance of the exception for a service class name that is
	 * not recognized.
	 *
	 * @param string|object $service Class name of the service that was not
	 *                               recognized.
	 *
	 * @return static
	 */
	public static function from_service( $service ) {
		$message = \sprintf(
			'The service "%s" is not recognized and cannot be registered.',
			\is_object( $service )
				? \get_class( $service )
				: (string) $service
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a service identifier that is
	 * not recognized.
	 *
	 * @param string $service_id Identifier of the service that is not being
	 *                           recognized.
	 *
	 * @return static
	 */
	public static function from_service_id( string $service_id ) {
		$message = \sprintf(
			'The service ID "%s" is not recognized and cannot be retrieved.',
			$service_id
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an invalid class name.
	 *
	 * @param mixed $class_name Class name that is not a string.
	 * @return static
	 */
	public static function from_invalid_class_name( $class_name ) {
		$message = \sprintf(
			'The class name "%s" is not a string and cannot be registered as a service.',
			self::stringify( $class_name )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an invalid identifier.
	 *
	 * @param mixed $identifier Identifier that is not a string.
	 * @return static
	 */
	public static function from_invalid_identifier( $identifier ) {
		$message = \sprintf(
			'The identifier "%s" is not a string and cannot be registered as a service.',
			self::stringify( $identifier )
		);

		return new self( $message );
	}
}
