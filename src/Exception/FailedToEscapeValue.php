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
 * Exception thrown when a value cannot be escaped.
 */
final class FailedToEscapeValue extends RuntimeException implements BasicScaffoldException {

	use Stringify;

	/**
	 * Create a new instance of the exception if the value itself created
	 * an exception.
	 *
	 * @param mixed $value Value that could not be escaped.
	 */
	public static function from_value( $value ): self {
		$message = \sprintf(
			'Could not escape the value "%1$s".',
			self::stringify( $value )
		);

		return new self( $message );
	}
}
