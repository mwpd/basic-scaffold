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

use RuntimeException;

/**
 * Exception thrown when a class instance cannot be made.
 */
final class FailedToMakeInstance extends RuntimeException implements BasicScaffoldException {

	// These constants are public so you can use them to find out what exactly
	// happened when you catch a "FailedToMakeInstance" exception.
	public const CIRCULAR_REFERENCE             = 100;
	public const UNRESOLVED_INTERFACE           = 200;
	public const UNREFLECTABLE_CLASS            = 300;
	public const UNRESOLVED_ARGUMENT            = 400;
	public const UNINSTANTIATED_SHARED_INSTANCE = 500;
	public const INVALID_DELEGATE               = 600;
	public const INVALID_REFLECTION             = 700;
	/**
	 * Create a new instance of the exception for an interface or class that
	 * created a circular reference.
	 *
	 * @param string $interface_or_class Interface or class name that generated
	 *                                   the circular reference.
	 *
	 * @return static
	 */
	public static function for_circular_reference( string $interface_or_class ) {
		$message = \sprintf(
			'Circular reference detected while trying to resolve the interface or class "%s".',
			$interface_or_class
		);

		return new self( $message, self::CIRCULAR_REFERENCE );
	}

	/**
	 * Create a new instance of the exception for an interface that could not
	 * be resolved to an instantiable class.
	 *
	 * @param string $interface_name Interface that was left unresolved.
	 *
	 * @return static
	 */
	public static function for_unresolved_interface( string $interface_name ) {
		$message = \sprintf(
			'Could not resolve the interface "%s" to an instantiable class, probably forgot to bind an implementation.',
			$interface_name
		);

		return new self( $message, self::UNRESOLVED_INTERFACE );
	}

	/**
	 * Create a new instance of the exception for an interface or class that
	 * could not be reflected upon.
	 *
	 * @param string $interface_or_class Interface or class that could not be
	 *                                   reflected upon.
	 *
	 * @return static
	 */
	public static function for_unreflectable_class( string $interface_or_class ) {
		$message = \sprintf(
			'Could not reflect on the interface or class "%s", probably not a valid FQCN.',
			$interface_or_class
		);

		return new self( $message, self::UNREFLECTABLE_CLASS );
	}

	/**
	 * Create a new instance of the exception for an argument that could not be
	 * resolved.
	 *
	 * @param string $argument_name Name of the argument that could not be
	 *                              resolved.
	 * @param string $class_name         Class that had the argument in its
	 *                              constructor.
	 * @return static
	 */
	public static function for_unresolved_argument( string $argument_name, string $class_name ) {
		$message = \sprintf(
			'Could not resolve the argument "%s" while trying to instantiate the class "%s".',
			$argument_name,
			$class_name
		);

		return new self( $message, self::UNRESOLVED_ARGUMENT );
	}

	/**
	 * Create a new instance of the exception for a class that was meant to be
	 * reused but was not yet instantiated.
	 *
	 * @param string $class_name Class that was not yet instantiated.
	 *
	 * @return static
	 */
	public static function for_uninstantiated_shared_instance( string $class_name ) {
		$message = \sprintf(
			'Could not retrieve the shared instance for "%s" as it was not instantiated yet.',
			$class_name
		);

		return new self( $message, self::UNINSTANTIATED_SHARED_INSTANCE );
	}

	/**
	 * Create a new instance of the exception for a delegate that was requested
	 * for a class that doesn't have one.
	 *
	 * @param string $class_name Class for which there is no delegate.
	 *
	 * @return static
	 */
	public static function for_invalid_delegate( string $class_name ) {
		$message = \sprintf(
			'Could not retrieve a delegate for "%s", none was defined.',
			$class_name
		);

		return new self( $message, self::INVALID_DELEGATE );
	}

	/**
	 * Create a new instance of the exception for a reflection that is not valid.
	 *
	 * @param string $class_name Class that was reflected upon.
	 *
	 * @return static
	 */
	public static function for_invalid_reflection( string $class_name ) {
		$message = \sprintf(
			'Could not create a reflection for the class "%s".',
			$class_name
		);

		return new self( $message, self::INVALID_REFLECTION );
	}
}
