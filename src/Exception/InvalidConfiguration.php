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
 * Exception thrown when a configuration is invalid.
 */
final class InvalidConfiguration extends InvalidArgumentException implements BasicScaffoldException {

	use Stringify;

	/**
	 * Create a new instance of the exception for an invalid services array.
	 *
	 * @param mixed $services Services that are not an array.
	 */
	public static function from_invalid_services( $services ): self {
		$message = \sprintf(
			'The services configuration "%s" is not an array and cannot be registered.',
			self::stringify( $services )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an invalid bindings array.
	 *
	 * @param mixed $bindings Bindings that are not an array.
	 */
	public static function from_invalid_bindings( $bindings ): self {
		$message = \sprintf(
			'The bindings configuration "%s" is not an array and cannot be registered.',
			self::stringify( $bindings )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a reserved Injector binding.
	 *
	 * @param string $injector Name of the reserved injector interface.
	 */
	public static function from_reserved_injector_binding( string $injector ): self {
		return new self(
			\sprintf(
				'"%s" is reserved: services receive the plugin\'s own injector. '
				. 'Remove the binding and pass a custom implementation through the plugin constructor.',
				$injector
			)
		);
	}

	/**
	 * Create a new instance of the exception for an invalid arguments array.
	 *
	 * @param mixed $arguments Arguments that are not an array.
	 */
	public static function from_invalid_arguments( $arguments ): self {
		$message = \sprintf(
			'The arguments configuration "%s" is not an array and cannot be registered.',
			self::stringify( $arguments )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an invalid shared instances array.
	 *
	 * @param mixed $shared_instances Shared instances that are not an array.
	 */
	public static function from_invalid_shared_instances( $shared_instances ): self {
		$message = \sprintf(
			'The shared instances configuration "%s" is not an array and cannot be registered.',
			self::stringify( $shared_instances )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an invalid delegations array.
	 *
	 * @param mixed $delegations Delegations that are not an array.
	 */
	public static function from_invalid_delegations( $delegations ): self {
		$message = \sprintf(
			'The delegations configuration "%s" is not an array and cannot be registered.',
			self::stringify( $delegations )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a binding to an interface or
	 * class that cannot stand in for the one it is bound to.
	 *
	 * @param string $from Interface or class that is bound.
	 * @param string $to   Interface or class it is bound to, which is not a
	 *                     subtype of $from.
	 */
	public static function from_incompatible_binding( string $from, string $to ): self {
		$message = \sprintf(
			'"%1$s" cannot be bound to "%2$s", as "%2$s" is not a subtype of "%1$s".',
			$from,
			$to
		);

		return new self( $message );
	}
}
