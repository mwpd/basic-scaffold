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

use MWPD\BasicScaffold\Infrastructure\Plugin;

/**
 * The plugin factory is responsible for instantiating the plugin and returning
 * that instance.
 *
 * It can decide whether to return a shared or a fresh instance as needed.
 *
 * To read more about why this is preferable to a Singleton,
 *
 * @see https://www.alainschlesser.com/singletons-shared-instances/
 */
final class BasicScaffoldPluginFactory {

	/**
	 * Create and return an instance of the plugin.
	 *
	 * This always returns a shared instance. This way, outside code can always
	 * get access to the object instance of the plugin.
	 *
	 * @return Plugin Plugin instance.
	 */
	public static function create(): Plugin {
		/**
		 * We use a static variable to ensure that the plugin is only instantiated
		 * once. This is important for performance reasons and to ensure that the
		 * plugin is properly initialized.
		 *
		 * This serves the same purpose as a Singleton, but it is implemented as
		 * a factory to stick to SOLID principles.
		 *
		 * @var Plugin|null $plugin
		 */
		static $plugin = null;

		if ( null === $plugin ) {
			$plugin = new BasicScaffoldPlugin();
		}

		return $plugin;
	}
}
