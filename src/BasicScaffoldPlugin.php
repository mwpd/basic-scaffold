<?php declare( strict_types=1 );

/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

namespace MWPD\BasicScaffold;

use MWPD\BasicScaffold\Infrastructure\{
	ServiceBasedPlugin,
	View\TemplatedViewFactory,
	ViewFactory
};

/**
 * The BasicScaffoldPlugin class is the composition root of the plugin.
 *
 * In here we assemble our infrastructure, configure it for the specific use
 * case the plugin is meant to solve and then kick off the services so that they
 * can hook themselves into the WordPress lifecycle.
 */
final class BasicScaffoldPlugin extends ServiceBasedPlugin {

	/*--------------------------------------------------------------------------
	 *-- 1. Define the services that make up this plugin.                     --
	 *------------------------------------------------------------------------*/

	/*
	 * The "plugin" is only a tool to hook arbitrary code up to the WordPress
	 * execution flow.
	 *
	 * The main structure we use to modularize our code is "services". These are
	 * what makes up the actual plugin, and they provide self-contained pieces
	 * of code that can work independently.
	 */

	/**
	 * The SERVICES array contains a map of <identifier> => <service class name>
	 * associations.
	 *
	 * @var array<string>
	 */
	protected const SERVICES = [
		self::VIEW_FACTORY_ID   => ViewFactory::class,
		self::SAMPLE_SERVICE_ID => SampleSubsystem\SampleService::class,
	];

	/*--------------------------------------------------------------------------
	 *-- 2. Configure the injector so it knows how to assemble them.          --
	 *------------------------------------------------------------------------*/

	/**
	 * The BINDINGS array contains a map of <interface> => <implementation>
	 * mappings, both of which should be fully qualified class names (FQCNs).
	 *
	 * The <interface> does not need to be the actual PHP `interface` language
	 * construct, it can be a `class` as well.
	 *
	 * Whenever you ask the injector to "make()" an <interface>, it will resolve
	 * these mappings and return an instance of the final <class> it found.
	 *
	 * @var array<string>
	 */
	protected const BINDINGS = [
		ViewFactory::class => TemplatedViewFactory::class,
	];

	/**
	 * The SHARED_INSTANCES array contains a list of FQCNs that are meant to be
	 * reused. For multiple "make()" requests, the injector will return the same
	 * instance reference for these, instead of always returning a new one.
	 *
	 * This effectively turns these FQCNs into a "singleton", without incurring
	 * all the drawbacks of the Singleton design anti-pattern.
	 *
	 * @var array<string>
	 */
	protected const SHARED_INSTANCES = [];

	/*--------------------------------------------------------------------------
	 *-- 3. Define prefixes and identifiers for outside access.               --
	 *------------------------------------------------------------------------*/

	/*
	 * Prefixes to use.
	 *
	 * These are provided so that if multiple plugins use the same boilerplate
	 * code, there hooks and service identifiers are scoped and don't clash.
	 */
	protected const HOOK_PREFIX    = 'mwpd.basic_scaffold.';
	protected const SERVICE_PREFIX = 'mwpd.basic_scaffold.';

	/*
	 * Service identifiers we know about.
	 *
	 * These can be used from outside code as well to directly refer to a
	 * service when talking to the service container.
	 */
	public const VIEW_FACTORY_ID   = self::SERVICE_PREFIX . 'view-factory';
	public const SAMPLE_SERVICE_ID = self::SERVICE_PREFIX . 'sample-subsystem.sample-service';
}
