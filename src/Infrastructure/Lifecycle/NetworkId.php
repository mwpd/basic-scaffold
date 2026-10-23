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

namespace MWPD\BasicScaffold\Infrastructure\Lifecycle;

use MWPD\BasicScaffold\Exception\InvalidArgument;

/**
 * The ID of a network.
 *
 * WordPress hands network IDs over as integers or numeric strings. This value
 * object only exists for a positive ID, so code that receives one does not
 * check it again (principle 9), and a network ID cannot be passed where
 * a site ID is expected (principle 8).
 */
final class NetworkId {

	/**
	 * The ID.
	 *
	 * @var positive-int
	 */
	private int $id;

	/**
	 * Instantiate a NetworkId object.
	 *
	 * @param positive-int $id The ID.
	 */
	private function __construct( int $id ) {
		$this->id = $id;
	}

	/**
	 * Create the ID of a network from an integer.
	 *
	 * @param int $id The ID.
	 *
	 * @throws InvalidArgument If the ID is not positive.
	 */
	public static function from_int( int $id ): self {
		if ( $id < 1 ) {
			throw InvalidArgument::from_network_id( $id );
		}

		return new self( $id );
	}

	/**
	 * Create the ID of a network from a value WordPress handed over.
	 *
	 * @param mixed $id An integer, or an integer in a string.
	 *
	 * @throws InvalidArgument If the value is not a positive integer.
	 */
	public static function from_wordpress( $id ): self {
		if ( is_string( $id ) && (string) (int) $id === $id ) {
			$id = (int) $id;
		}

		if ( ! is_int( $id ) ) {
			throw InvalidArgument::from_network_id( $id );
		}

		return self::from_int( $id );
	}

	/**
	 * Get the ID as an integer, to pass on to WordPress.
	 *
	 * @return positive-int The ID.
	 */
	public function to_int(): int {
		return $this->id;
	}

	/**
	 * Check whether this is the same network as another one.
	 *
	 * @param NetworkId $other ID to compare with.
	 */
	public function equals( NetworkId $other ): bool {
		return $this->id === $other->id;
	}
}
