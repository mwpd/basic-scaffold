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

use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;

/**
 * Something that is cleaned up in each site the plugin is deactivated on.
 *
 * By tagging a service with this interface, the plugin calls it once for each
 * site a deactivation reaches, with that site switched to. A network
 * deactivation skips a site that still has the plugin activated on its own,
 * and a deactivation on one site skips that site while the plugin stays
 * network-active.
 *
 * A service that implements this interface or NetworkDeactivateable no
 * longer receives the parameterless Deactivateable::deactivate() for any
 * deactivation.
 */
interface SiteDeactivateable {

	/**
	 * Clean the service up in the current site.
	 *
	 * Called once for each target site, with that site switched to. Must be safe
	 * to run again on a site where it already ran.
	 *
	 * @param SiteContext $context Where the call happens.
	 */
	public function deactivate_site( SiteContext $context ): void;
}
