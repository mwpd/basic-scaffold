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
final class InvalidArgument extends InvalidArgumentException implements BasicScaffoldException {

	use Stringify;

	/**
	 * Create a new instance of the exception for a service class name that is
	 * not recognized.
	 *
	 * @param mixed $name Name of the argument that was not recognized.
	 */
	public static function from_name( $name ): self {
		$message = \sprintf(
			'The argument "%s" is not recognized and cannot be registered.',
			self::stringify( $name )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a site ID that is not a
	 * positive integer.
	 *
	 * @param mixed $id Site ID as it was handed over.
	 */
	public static function from_site_id( $id ): self {
		return new self(
			\sprintf( 'The site ID "%s" is not a positive integer.', self::stringify( $id ) )
		);
	}

	/**
	 * Create a new instance of the exception for a network ID that is not a
	 * positive integer.
	 *
	 * @param mixed $id Network ID as it was handed over.
	 */
	public static function from_network_id( $id ): self {
		return new self(
			\sprintf( 'The network ID "%s" is not a positive integer.', self::stringify( $id ) )
		);
	}

	/**
	 * Create a new instance of the exception for a site that WordPress handed
	 * over as something other than a WP_Site object.
	 *
	 * @param mixed $site Site as it was handed over.
	 */
	public static function from_site( $site ): self {
		return new self(
			\sprintf( 'The site "%s" is not a WP_Site object.', self::stringify( $site ) )
		);
	}

	/**
	 * Create a new instance of the exception for a page size that is not
	 * positive.
	 *
	 * @param int $page_size Page size that was asked for.
	 */
	public static function from_page_size( int $page_size ): self {
		return new self(
			\sprintf( 'The page size %d is not a positive integer.', $page_size )
		);
	}
}
