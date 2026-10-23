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

namespace MWPD\BasicScaffold;

/**
 * The bridge WordPress calls to uninstall the plugin.
 *
 * WordPress stores the uninstall callback in the database and calls it in a
 * later request, after including the main plugin file. It only accepts a
 * function or a static method there, never an object or a closure, so this
 * class is the one way into the object graph for that event: a listed bridge
 * that gets the plugin through the plugin factory and hands over at once.
 * The plugin then builds only the services that take part in the uninstall.
 *
 * It holds no state and no logic of its own. An exception from the uninstall
 * is not caught here: it propagates so that a failed cleanup is visible, and
 * the plugin registers this callback again before it does, so that deleting
 * the plugin again retries.
 */
final class BasicScaffoldUninstallHook {

	/**
	 * Uninstall the plugin.
	 */
	public static function uninstall(): void {
		BasicScaffoldPluginFactory::create()->uninstall();
	}
}
