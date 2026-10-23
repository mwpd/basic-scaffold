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

	/**
	 * Create a new instance of the exception for a custom injector without an
	 * injector factory, while a scoped lifecycle service needs one.
	 *
	 * @param string $injector Class of the custom injector.
	 */
	public static function from_missing_injector_factory( string $injector ): self {
		return new self(
			\sprintf(
				'The plugin was given the injector "%1$s" but no injector factory, and a scoped lifecycle service'
				. ' needs a fresh injector for each site. Pass an InjectorFactory that creates "%1$s" to the'
				. ' plugin constructor as well.',
				$injector
			)
		);
	}

	/**
	 * Create a new instance of the exception for a scoped lifecycle service
	 * that needs the plugin's main file, while none is configured.
	 *
	 * @param string $needed_by Lifecycle interface or setting that needs the
	 *                          main file.
	 */
	public static function from_missing_main_file( string $needed_by ): self {
		return new self(
			\sprintf(
				'%s needs to know which plugin it belongs to, but the composition root names no main plugin'
				. ' file. Return the path of the main plugin file from get_main_file().',
				$needed_by
			)
		);
	}

	/**
	 * Create a new instance of the exception for an uninstall-capable service
	 * while no uninstall callback is configured.
	 *
	 * @param string $capability Uninstall interface the service implements.
	 */
	public static function from_missing_uninstall_callback( string $capability ): self {
		return new self(
			\sprintf(
				'A service implements "%s", but the composition root names no uninstall callback, so WordPress would'
				. ' never call it. Return the static uninstall bridge from get_uninstall_callback().',
				$capability
			)
		);
	}

	/**
	 * Create a new instance of the exception for an uninstall callback that is
	 * not a public static method named by two strings.
	 *
	 * @param mixed $callback Uninstall callback that was configured.
	 */
	public static function from_invalid_uninstall_callback( $callback ): self {
		return new self(
			\sprintf(
				'The uninstall callback "%s" is not a public static method given as [ class name, method name ].'
				. ' WordPress stores the callback in the database, so it cannot be an object or a closure.',
				self::stringify( $callback )
			)
		);
	}

	/**
	 * Create a new instance of the exception for a registration report that
	 * is asked for while no service observes the registration.
	 *
	 * @param string $report    Name of the report interface.
	 * @param string $observing Name of the interface that observes the
	 *                          registration.
	 */
	public static function from_unobserved_registration( string $report, string $observing ): self {
		return new self(
			\sprintf(
				'"%1$s" is only available while a configured service implementing "%2$s" observes the'
				. ' registration in this request, because the plugin records nothing otherwise. Implement "%2$s"'
				. ' on the service that needs the report.',
				$report,
				$observing
			)
		);
	}
}
