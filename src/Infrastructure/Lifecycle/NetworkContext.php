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

/**
 * The network a scoped lifecycle call happens for.
 *
 * The network's main site is switched to when the service is called.
 * Switching sites does not switch networks, so network options are addressed
 * with the ID this context names.
 */
final class NetworkContext {

	private NetworkId $network_id;

	/**
	 * Instantiate a NetworkContext object.
	 *
	 * @param NetworkId $network_id Network the call happens for.
	 */
	private function __construct( NetworkId $network_id ) {
		$this->network_id = $network_id;
	}

	/**
	 * Create the context of a call for one network.
	 *
	 * @param NetworkId $network_id Network the call happens for.
	 */
	public static function for_network( NetworkId $network_id ): self {
		return new self( $network_id );
	}

	/**
	 * Get the network the call happens for.
	 */
	public function network_id(): NetworkId {
		return $this->network_id;
	}
}
