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

/**
 * Trait to add stringification functionality to exceptions.
 */
trait Stringify {

	/**
	 * Stringify a value.
	 *
	 * @param mixed $value Value to stringify.
	 * @return string Stringified value.
	 */
	private static function stringify( $value ): string {
		if ( \is_object( $value ) && \method_exists( $value, '__toString' ) ) {
			return (string) $value;
		}
		if ( \is_scalar( $value ) ) {
			return (string) $value;
		}
		return '{' . \gettype( $value ) . '}';
	}
}
