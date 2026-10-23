<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 *
 * ------------------------------------------------------------------------------
 * -- 1. Provide the plugin meta information that WordPress needs.             --
 * ------------------------------------------------------------------------------
 *
 * @wordpress-plugin
 * Plugin Name:  MWPD Basic Plugin Scaffold
 * Plugin URI:   https://www.mwpd.io/
 * Description:  Basic plugin scaffold for the "Modern WordPress Plugin Development" book
 * Version:      0.1.0
 * Requires PHP: 7.4
 * Author:       Alain Schlesser <alain.schlesser@gmail.com>
 * Author URI:   https://www.alainschlesser.com/
 * Text Domain:  mwpd-basic
 * Domain Path:  /languages
 * License:      MIT
 * License URI:  https://opensource.org/licenses/MIT
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold;

use MWPD\BasicScaffold\Infrastructure\Lifecycle\LifecycleScope;

/*
 * This is the plugin's bootstrap file. It serves three main purposes:
 *  1. Provide the plugin meta information that WordPress needs;
 *  2. Prepare the environment so that it is ready to execute our OOP code;
 *  3. Instantiate and kick off our "composition root" (our 'Plugin' class).
 *
 * The bootstrap file should not do anything else, so that we have a clean
 * separation between a.) code that needs to be run sequentially and produces
 * side-effects and b.) declarations that can be taken out of contexts for
 * testing and reuse and have no side-effects.
 *
 * Anything past this bootstrap file should be autoloadable classes, interfaces
 * or traits without any side-effects.
 */

/*
 * As this is the only PHP file having side effects, we need to provide a
 * safeguard, so we want to make sure this file is only run from within
 * WordPress and cannot be directly called through a web request.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die();
}



/*
 *------------------------------------------------------------------------------
 *-- 2. Prepare the environment so that it is ready to execute our OOP code.  --
 *----------------------------------------------------------------------------
 */

/*
 * We try to load the Composer if it exists.
 * If it doesn't exist, we fall back to a basic bundled autoloader
 * implementation. This allows us to just use the plugin as-is without requirin
 * the 'composer install' step.
 * Note: If you use Composer not only for autoloading, but also including
 * dependencies needed in production, the 'composer install' becomes mandatory
 * and the fallback autoloader should probably be removed.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

if ( ! class_exists( __NAMESPACE__ . '\\PluginFactory' ) ) {
	// Composer autoloader apparently was not found, so fall back to our bundled
	// autoloader.
	require_once __DIR__ . '/src/Infrastructure/Autoloader.php';

	( new Infrastructure\Autoloader() )
		->add_namespace( __NAMESPACE__, __DIR__ . '/src' )
		->register();
}



/*
 *------------------------------------------------------------------------------
 *-- 3. Instantiate and kick off our "composition root" (our "Plugin" class). --
 *----------------------------------------------------------------------------
 */

/*
 * We use a factory to instantiate the actual plugin.
 *
 * The factory keeps the object as a shared instance, so that the activation
 * and deactivation hooks below reach that same plugin instance through it.
 *
 * This is similar to a Singleton, but without all the drawbacks the Singleton
 * anti-pattern brings along.
 *
 * For more information on why to avoid a Singleton,
 * @see https://www.alainschlesser.com/singletons-shared-instances/
 *
 * Because the factory always returns that same shared instance, we ask it for
 * the plugin wherever we need it, instead of storing it in a variable.
 * WordPress includes this file in the global scope, so any variable defined
 * here would become a global and could clash with WordPress core or with other
 * plugins. This file therefore defines no variables at all.
 */

/*
 * We register activation and deactivation hooks by using closures, as these
 * need static access to work correctly.
 *
 * On multisite, WordPress tells them whether the plugin is activated or
 * deactivated for the whole network. The flag is turned into a LifecycleScope
 * right here, at the boundary, so the plugin never handles the raw value.
 *
 * The uninstall hook is not registered here: WordPress stores it in the
 * database, so the plugin registers it once, when it is activated. The
 * callback it stores is the static BasicScaffoldUninstallHook::uninstall().
 */
\register_activation_hook(
	__FILE__,
	/**
	 * Activate the plugin, for the whole network if WordPress says so.
	 *
	 * @param mixed $network_wide Whether the plugin is network-activated.
	 */
	static function ( $network_wide = false ): void {
		BasicScaffoldPluginFactory::create()->activate_in( LifecycleScope::from_wordpress_flag( $network_wide ) );
	}
);

\register_deactivation_hook(
	__FILE__,
	/**
	 * Deactivate the plugin, for the whole network if WordPress says so.
	 *
	 * @param mixed $network_wide Whether the plugin is network-deactivated.
	 */
	static function ( $network_wide = false ): void {
		BasicScaffoldPluginFactory::create()->deactivate_in( LifecycleScope::from_wordpress_flag( $network_wide ) );
	}
);

/*
 * Finally, we run the plugin's register method to Hook the plugin into the
 * WordPress request lifecycle.
 */
BasicScaffoldPluginFactory::create()->register();
