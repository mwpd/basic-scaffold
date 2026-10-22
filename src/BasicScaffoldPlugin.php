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

use MWPD\BasicScaffold\Exception\FailedToFindPost;
use MWPD\BasicScaffold\SampleSubsystem\SampleBackendService;
use MWPD\BasicScaffold\SampleSubsystem\SampleLoopService;
use MWPD\BasicScaffold\Infrastructure\{
	ServiceBasedPlugin,
	View\TemplatedViewFactory,
	ViewFactory
};
use WP_Post;

/**
 * The BasicScaffoldPlugin class is the composition root of the plugin.
 *
 * In here we assemble our infrastructure, configure it for the specific use
 * case the plugin is meant to solve and then kick off the services so that they
 * can hook themselves into the WordPress lifecycle.
 */
final class BasicScaffoldPlugin extends ServiceBasedPlugin {
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
		return [
			self::SAMPLE_BACKEND_SERVICE_ID => SampleBackendService::class,
			self::SAMPLE_LOOP_SERVICE_ID    => SampleLoopService::class,

			// Add your service definitions here.
		];
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
		return [
			// Map the ViewFactory interface to a concrete implementation.
			ViewFactory::class => TemplatedViewFactory::class,

			// Add your bindings here.
		];
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
		return [

			/*
			 * Example - add a scalar value to an argument for SampleService:
			 * SampleService::class => [ 'argument_name' => 'value' ],
			 */

			// Add your argument mappings here.
		];
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
		return [

			/*
			 * Example - make SampleService be shared amongst instantiations:
			 * SampleService::class,
			 */

			// Add your shared instances here.
		];
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
		return [

			/*
			 * WordPress hands out the current post through get_post(). The
			 * delegation turns that into an object the injector can provide,
			 * so SampleLoopService only declares that it needs a WP_Post.
			 *
			 * get_post() returns null when there is no current post. Rather than
			 * passing that on, the delegation fails with a dedicated exception.
			 */
			WP_Post::class => static function (): WP_Post {
				$post = \get_post();

				if ( ! $post instanceof WP_Post ) {
					throw FailedToFindPost::for_current_request();
				}

				return $post;
			},

			// Add your delegations here.
		];
	}

	/*
	 * --------------------------------------------------------------------------
	 * -- 3. Define prefixes and identifiers for outside access.               --
	 * --------------------------------------------------------------------------
	 */

	/*
	 * Prefixes to use.
	 *
	 * These are provided so that if multiple plugins use the same boilerplate
	 * code, there hooks and service identifiers are scoped and don't clash.
	 */
	protected const HOOK_PREFIX = 'mwpd.basic_scaffold.';

	protected const SERVICE_PREFIX = 'mwpd.basic_scaffold.';

	/*
	 * Service identifiers we know about.
	 *
	 * These can be used from outside code as well to directly refer to a
	 * service when talking to the service container.
	 */
	public const SAMPLE_BACKEND_SERVICE_ID = self::SERVICE_PREFIX . 'sample-subsystem.sample-backend-service';

	public const SAMPLE_LOOP_SERVICE_ID = self::SERVICE_PREFIX . 'sample-subsystem.sample-loop-service';
}
