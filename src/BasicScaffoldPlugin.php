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

use MWPD\BasicScaffold\SampleSubsystem\SampleBackendService;
use MWPD\BasicScaffold\SampleSubsystem\SampleLoopService;
use MWPD\BasicScaffold\Infrastructure\{Provider, ServiceBasedPlugin, View\TemplatedViewFactory, ViewFactory};

/**
 * The BasicScaffoldPlugin class is the composition root of the plugin.
 *
 * In here we assemble our infrastructure, configure it for the specific use
 * case the plugin is meant to solve and then kick off the services so that they
 * can hook themselves into the WordPress lifecycle.
 */
final class BasicScaffoldPlugin extends ServiceBasedPlugin {

	protected $providers = [];



	/*
	 * --------------------------------------------------------------------------
	 * -- 1. Define the services that make up this plugin.                     --
	 * --------------------------------------------------------------------------
	 */

	/*
	 * The "plugin" is only a tool to hook arbitrary code up to the WordPress
	 * execution flow.
	 *
	 * The main structure we use to modularize our code is "services". These are
	 * what makes up the actual plugin, and they provide self-contained pieces
	 * of code that can work independently.
	 */
	/**
	 * @param array $providers
	 */
	public function __construct(array $providers ) {
		$this->providers   = $providers;
	}

	/**
	 * Get the list of services to register.
	 *
	 * The services array contains a map of <identifier> => <service class name>
	 * associations.
	 *
	 * @return array<string,class-string|callable> Associative array of identifiers
	 *                                             mapped to fully qualified class
	 *                                             names or callables.
	 */
	protected function get_service_classes(): array {

		$services = [];

		foreach ($this->providers as $provider) {
			/**
			 * @var Provider $provider
			 */
			$provider = new $provider();
			$services = array_merge(
				$services,
				$provider->get_service_classes(),
			);
		}

		return $services;
	}

	/*
	 * --------------------------------------------------------------------------
	 * -- 2. Configure the injector so it knows how to assemble them.          --
	 * --------------------------------------------------------------------------
	 */

	/**
	 * Get the bindings for the dependency injector.
	 *
	 * The bindings array contains a map of <interface> => <implementation>
	 * mappings, both of which should be fully qualified class names (FQCNs).
	 *
	 * The <interface> does not need to be the actual PHP `interface` language
	 * construct, it can be a `class` as well.
	 *
	 * Whenever you ask the injector to "make()" an <interface>, it will resolve
	 * these mappings and return an instance of the final <class> it found.
	 *
	 * @return array<class-string,class-string|callable> Associative array of
	 *                                                   fully qualified class names
	 *                                                   mapped to fully
	 *                                                   qualified class names or
	 *                                                   callables.
	 */
	protected function get_bindings(): array {
		$bindings = [];

		foreach ($this->providers as $provider) {
			/**
			 * @var Provider $provider
			 */
			$provider = new $provider();
			$bindings = array_merge(
				$bindings,
				$provider->get_bindings(),
			);
		}

		return $bindings;
	}

	/**
	 * Get the argument bindings for the dependency injector.
	 *
	 * The arguments array contains a map of <class> => <associative array of
	 * arguments> mappings.
	 *
	 * The array is provided in the form <argument name> => <argument value>.
	 *
	 * @return array<array<string, mixed>> Associative array of arrays mapping
	 *                                     argument names to argument values.
	 */
	protected function get_arguments(): array {
		$arguments = [];

		foreach ($this->providers as $provider) {
			/**
			 * @var Provider $provider
			 */
			$provider = new $provider();
			$arguments = array_merge(
				$arguments,
				$provider->get_arguments(),
			);
		}

		return $arguments;
	}

	/**
	 * Get the shared instances for the dependency injector.
	 *
	 * The shared instances array contains a list of FQCNs that are meant to be
	 * reused. For multiple "make()" requests, the injector will return the same
	 * instance reference for these, instead of always returning a new one.
	 *
	 * This effectively turns these FQCNs into a "singleton", without incurring
	 * all the drawbacks of the Singleton design anti-pattern.
	 *
	 * @return array<class-string> Array of fully qualified class names.
	 */
	protected function get_shared_instances(): array {
		$shared_instances = [];

		foreach ($this->providers as $provider) {
			/**
			 * @var Provider $provider
			 */
			$provider = new $provider();
			$shared_instances = array_merge(
				$shared_instances,
				$provider->get_shared_instances(),
			);
		}

		return $shared_instances;
	}

	/**
	 * Get the delegations for the dependency injector.
	 *
	 * The delegations array contains a map of <class> => <callable>
	 * mappings.
	 *
	 * The <callable> is basically a factory to provide custom instantiation
	 * logic for the given <class>.
	 *
	 * @return array<class-string,callable> Associative array of callables.
	 */
	protected function get_delegations(): array {
		$delegations = [];

		foreach ($this->providers as $provider) {
			/**
			 * @var Provider $provider
			 */
			$provider = new $provider();
			$delegations = array_merge(
				$delegations,
				$provider->get_delegations(),
			);
		}

		return $delegations;
	}
}
