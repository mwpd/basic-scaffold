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
 * Something that is set up in each site the plugin is activated on.
 *
 * By tagging a service with this interface, the plugin calls it once for each
 * site an activation reaches, with that site switched to: the current site
 * for an activation on one site, every site of the network for a network
 * activation.
 *
 * A service that implements this interface or NetworkActivateable no longer
 * receives the parameterless Activateable::activate() for any activation.
 * Setup that the scoped methods share goes into a private method that each
 * of them calls.
 */
interface SiteActivateable {

	/**
	 * Set the service up in the current site.
	 *
	 * Called once for each target site, with that site switched to. Must be safe
	 * to run again on a site where it already ran.
	 *
	 * @param SiteContext $context Where the call happens.
	 */
	public function activate_site( SiteContext $context ): void;
}
