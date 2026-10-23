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
 * Something that removes its data from a site when the plugin is uninstalled.
 *
 * The plugin calls it from its uninstall bridge for the current site on a
 * single site, and for every site of every network on multisite, with each
 * site switched to. A Conditional service is asked whether it is needed in
 * each site; cleanup that has to run whatever the request looks like belongs
 * in a service without a condition.
 */
interface SiteUninstallable {

	/**
	 * Remove the plugin-owned data of the current site.
	 *
	 * Must tolerate data that is already gone, and only remove data the plugin
	 * owns.
	 *
	 * @param SiteContext $context Where the call happens.
	 */
	public function uninstall_site( SiteContext $context ): void;
}
