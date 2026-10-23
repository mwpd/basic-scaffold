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
 * Exception for invalid context properties.
 */
final class InvalidContextProperty extends InvalidArgumentException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for a context property that is
	 * not recognized.
	 *
	 * @param string $property Name of the context property that was not
	 *                         recognized.
	 */
	public static function from_property( string $property ): self {
		$message = \sprintf(
			'The property "%s" could not be found within the context of the currently rendered view.',
			$property
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a template that assigns a
	 * property to the scope it renders in.
	 *
	 * @param string $property Name of the property the template assigned.
	 */
	public static function for_assignment( string $property ): self {
		$message = \sprintf(
			'The template assigned the property "%s". Templates cannot set properties;'
			. ' pass the value in the context of the view instead.',
			$property
		);

		return new self( $message );
	}
}
