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

use MWPD\BasicScaffold\Infrastructure\Lifecycle\LifecycleScope;

/**
 * The lifecycle events of a plugin that can reach a whole network, and its
 * uninstallation.
 *
 * Plugin::activate() and Plugin::deactivate() act on the current site. The
 * main plugin file and the uninstall bridge call these methods instead, so
 * that the services implementing the site- and network-scoped lifecycle
 * interfaces are reached where they belong.
 */
interface PluginLifecycle {

	/**
	 * Activate the plugin in the given scope.
	 *
	 * @param LifecycleScope $scope The current site, or the current network.
	 */
	public function activate_in( LifecycleScope $scope ): void;

	/**
	 * Deactivate the plugin in the given scope.
	 *
	 * @param LifecycleScope $scope The current site, or the current network.
	 */
	public function deactivate_in( LifecycleScope $scope ): void;

	/**
	 * Remove the plugin's data: from the current site on a single site, from
	 * every site and network on multisite.
	 */
	public function uninstall(): void;
}
