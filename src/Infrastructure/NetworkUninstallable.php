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
 * Something that removes its data from a network when the plugin is uninstalled.
 *
 * On multisite, the plugin calls it once for each network, with the network's
 * main site switched to, after the network's sites are cleaned up. It is not
 * called on a single site.
 */
interface NetworkUninstallable {

	/**
	 * Remove the plugin-owned data of a network.
	 *
	 * Must tolerate data that is already gone, and only remove data the plugin
	 * owns. Switching sites does not switch networks, so network options are
	 * addressed with the network ID of the context.
	 *
	 * @param NetworkContext $context Where the call happens.
	 */
	public function uninstall_network( NetworkContext $context ): void;
}
