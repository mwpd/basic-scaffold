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
 * Exception thrown when the dependencies of a service can never be met, so the
 * service can never be registered.
 *
 * Registration waits for a service's dependencies. Without this exception, a
 * service whose dependencies will never arrive would just quietly be missing.
 */
final class FailedToResolveDependencies extends RuntimeException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for services that depend on each
	 * other, directly or through other services.
	 *
	 * @param array<int,string> $cycle Service IDs along the cycle, starting
	 *                                 and ending with the same service.
	 */
	public static function for_dependency_cycle( array $cycle ): self {
		$message = \sprintf(
			'The services %s depend on each other in a cycle, so none of them can be registered.'
			. ' Remove one of these dependencies, or move what the services share into a new service'
			. ' that they both depend on.',
			'"' . \implode( '" -> "', $cycle ) . '"'
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a service that depends on a
	 * service that was not registered in this request.
	 *
	 * @param string $service_id    ID of the service that cannot be registered.
	 * @param string $dependency_id ID of the dependency that was not registered.
	 */
	public static function for_unregistered_dependency( string $service_id, string $dependency_id ): self {
		$message = \sprintf(
			'The service "%1$s" depends on the service "%2$s", which was not registered in this request,'
			. ' so "%1$s" cannot be registered either. A Conditional service is only registered when it'
			. ' is needed: make "%1$s" Conditional as well, so it is not needed when "%2$s" is not,'
			. ' or remove the dependency.',
			$service_id,
			$dependency_id
		);

		return new self( $message );
	}
}
