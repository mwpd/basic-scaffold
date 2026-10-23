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
 * Something that is cleaned up once for a network when the plugin is network-deactivated.
 *
 * By tagging a service with this interface, the plugin calls it once for a
 * network deactivation, with the network's main site switched to, after the
 * sites of the network are cleaned up. A deactivation on a single site does
 * not call it.
 *
 * A service that implements this interface or SiteDeactivateable no longer
 * receives the parameterless Deactivateable::deactivate() for any
 * deactivation.
 */
interface NetworkDeactivateable {

	/**
	 * Clean up what the service keeps for a whole network.
	 *
	 * Switching sites does not switch networks, so network options are read and
	 * written with the network ID of the context.
	 *
	 * @param NetworkContext $context Where the call happens.
	 */
	public function deactivate_network( NetworkContext $context ): void;
}
