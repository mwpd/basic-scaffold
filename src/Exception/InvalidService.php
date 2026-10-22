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
	 */
	public static function from_service( $service ): self {
		$message = \sprintf(
			'The service "%s" is not recognized and cannot be registered.',
			self::stringify( $service )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a service identifier that is
	 * not recognized.
	 *
	 * @param string $service_id Identifier of the service that is not being
	 *                           recognized.
	 */
	public static function from_service_id( string $service_id ): self {
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
	 */
	public static function from_invalid_class_name( $class_name ): self {
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
	 */
	public static function from_invalid_identifier( $identifier ): self {
		$message = \sprintf(
			'The identifier "%s" is not a string and cannot be registered as a service.',
			self::stringify( $identifier )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an invalid delegation.
	 *
	 * @param string $class_name Class name that is not callable.
	 * @param mixed  $delegation Delegation that is not callable.
	 */
	public static function from_invalid_delegation( string $class_name, $delegation ): self {
		$message = \sprintf(
			'The delegation for "%s" is not a callable: %s',
			self::stringify( $class_name ),
			self::stringify( $delegation )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an invalid argument map.
	 *
	 * @param string $class_name Class name that is not an array.
	 * @param mixed  $argument_map Argument map that is not an array.
	 */
	public static function from_invalid_argument_map( string $class_name, $argument_map ): self {
		$message = \sprintf(
			'The argument map for "%s" is not an array: %s',
			self::stringify( $class_name ),
			self::stringify( $argument_map )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a lazy service.
	 *
	 * @param mixed $service Service that is not an object of type Service.
	 */
	public static function from_lazy_service( $service ): self {
		$message = \sprintf(
			'The lazy service "%s" cannot be instantiated into an object of type Service.',
			self::stringify( $service )
		);

		return new self( $message );
	}
}
