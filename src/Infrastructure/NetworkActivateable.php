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

namespace MWPD\BasicScaffold\Infrastructure;

use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkContext;

/**
 * Something that is set up once for a network when the plugin is network-activated.
 *
 * By tagging a service with this interface, the plugin calls it once for a
 * network activation, with the network's main site switched to, before any
 * site of the network is set up. An activation on a single site does not
 * call it.
 *
 * A service that implements this interface or SiteActivateable no longer
 * receives the parameterless Activateable::activate() for any activation.
 */
interface NetworkActivateable {

	/**
	 * Set up what the service keeps for a whole network.
	 *
	 * Switching sites does not switch networks, so network options are read and
	 * written with the network ID of the context.
	 *
	 * @param NetworkContext $context Where the call happens.
	 */
	public function activate_network( NetworkContext $context ): void;
}
