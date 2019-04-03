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

class InvalidContextProperty
	extends InvalidArgumentException
	implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for a context property that is
	 * not recognized.
	 *
	 * @param string $property Name of the context property that was not
	 *                         recognized.
	 *
	 * @return static
	 */
	public static function from_property( string $property ) {
		$message = \sprintf(
			'The property "%s" could not be found within the context of the currently rendered view.',
			$property
		);

		return new static( $message );
	}
}
