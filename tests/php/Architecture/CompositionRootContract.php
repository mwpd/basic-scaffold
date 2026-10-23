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

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey\Container;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 * Checks the promises a composition root makes about the services it
 * declares.
 *
 * Static rules see the code, not the object graph the plugin wires. These
 * checks build the real plugin and every service it declares, through the
 * plugin's own injector configuration:
 *
 * - every declaration is a Service, its registration action and
 *   dependencies make sense;
 * - the plugin can build every service from its own wiring;
 * - building a service changes no hooks, not even through a collaborator its
 *   constructor creates.
 *
 * They need Brain Monkey to be set up, and every WordPress function the
 * wiring calls to be stubbed, but no binding the composition root lacks.
 */
final class CompositionRootContract {

	/**
	 * Functions that change hooks but leave no trace in Brain Monkey's storage.
	 */
	private const WATCHED_FUNCTIONS = [
		'remove_action',
		'remove_filter',
		'register_activation_hook',
		'register_deactivation_hook',
	];

	/**
	 * Composition root to check.
	 *
	 * @var class-string<ServiceBasedPlugin>
	 */
	private string $plugin_class;

	/**
	 * Instantiate a CompositionRootContract object.
	 *
	 * @param class-string<ServiceBasedPlugin> $plugin_class Composition root to check.
	 */
	public function __construct( string $plugin_class ) {
		$this->plugin_class = $plugin_class;
	}

	/**
	 * Check every service the composition root declares.
	 *
	 * @return array<string> Problems found.
	 */
	public function check(): array {
		// Composition roots keep the constructor of ServiceBasedPlugin, so the
		// injector it takes is the one the plugin configures.
		$plugin   = ( new ReflectionClass( $this->plugin_class ) )->newInstance( true, new SimpleInjector() );
		$injector = $this->configure_injector( $plugin );
		$declared = self::call( $plugin, 'get_service_classes' );
		$services = is_array( $declared ) ? $declared : [];
		$problems = [];

		foreach ( $services as $id => $declaration ) {
			$id = (string) $id;

			// Resolving a callable declaration may build objects too, so the
			// watch starts before it and lasts through the construction.
			$calls  = [];
			$before = $this->start_watching( $calls );

			try {
				// The plugin resolves every declaration, so a callable given as
				// a string, such as 'Factory::service_class', resolves too.
				$class_name = self::call( $plugin, 'maybe_resolve', $declaration );
			} catch ( Throwable $throwable ) {
				$problems[] = $this->problem(
					$id,
					is_string( $declaration ) ? $declaration : 'callable',
					sprintf(
						'cannot be resolved: %s Make the declaration a class name, or a callable that returns one'
						. ' without needing anything the composition root does not provide.',
						$throwable->getMessage()
					)
				);
				continue;
			}

			if ( ! is_string( $class_name )
				|| ! class_exists( $class_name )
				|| ! is_a( $class_name, Service::class, true ) ) {
				$problems[] = $this->problem(
					$id,
					is_string( $class_name ) ? $class_name : gettype( $class_name ),
					'is not a class implementing Service. Declare a class name, or a callable that returns one,'
					. ' of a class that implements Service.'
				);
				continue;
			}

			$problems = array_merge(
				$problems,
				$this->check_capabilities( $id, $class_name, array_keys( $services ) ),
				$this->check_construction( $id, $class_name, $injector, $calls, $before )
			);
		}

		return $problems;
	}

	/**
	 * Check the answers a service gives before it is built.
	 *
	 * @param string                $id         Identifier of the service.
	 * @param class-string<Service> $class_name Class of the service.
	 * @param array<int|string>     $declared   Identifiers of all services.
	 * @return array<string> Problems found.
	 */
	private function check_capabilities( string $id, string $class_name, array $declared ): array {
		$problems = [];

		if ( is_a( $class_name, Delayed::class, true ) && trim( $class_name::get_registration_action() ) === '' ) {
			$problems[] = $this->problem(
				$id,
				$class_name,
				'is Delayed, but get_registration_action() returns no action. Return the action whose firing the'
				. ' service has to wait for, such as "init" or "wp".'
			);
		}

		if ( is_a( $class_name, HasDependencies::class, true ) ) {
			foreach ( $class_name::get_dependencies() as $dependency ) {
				if ( ! in_array( $dependency, $declared, true ) ) {
					$problems[] = $this->problem(
						$id,
						$class_name,
						sprintf(
							'depends on "%s", which the composition root does not declare. Name the identifier of a'
							. ' service in get_service_classes(), or declare the missing service.',
							$dependency
						)
					);
				}
			}
		}

		return $problems;
	}

	/**
	 * Start watching for hook changes.
	 *
	 * Adding and firing hooks shows in Brain Monkey's hook storage. Removing a
	 * hook that was never added leaves no trace there, so those calls, and the
	 * activation hooks Brain Monkey does not know, are recorded directly.
	 *
	 * @param array<string> $calls Receives the watched functions called.
	 * @return mixed Snapshot of Brain Monkey's hook storage.
	 */
	private function start_watching( array &$calls ) {
		foreach ( self::WATCHED_FUNCTIONS as $function ) {
			Functions\when( $function )->alias(
				static function () use ( &$calls, $function ): bool {
					$calls[] = $function;

					return true;
				}
			);
		}

		return self::get_hooks();
	}

	/**
	 * Build a service and check that nothing since the watch started changed
	 * hooks.
	 *
	 * @param string                $id         Identifier of the service.
	 * @param class-string<Service> $class_name Class of the service.
	 * @param Injector              $injector   Injector the plugin configured.
	 * @param array<string>         $calls      Watched functions called.
	 * @param mixed                 $before     Hook storage when the watch
	 *                                          started.
	 * @return array<string> Problems found.
	 */
	private function check_construction(
		string $id,
		string $class_name,
		Injector $injector,
		array &$calls,
		$before
	): array {
		try {
			$service = $injector->make( $class_name );
		} catch ( Throwable $throwable ) {
			return [
				$this->problem(
					$id,
					$class_name,
					sprintf(
						'cannot be built from the composition root: %s Bind what it needs in get_bindings(),'
						. ' get_arguments() or get_delegations().',
						$throwable->getMessage()
					)
				),
			];
		}

		if ( ! $service instanceof Service ) {
			return [
				$this->problem(
					$id,
					$class_name,
					sprintf(
						'is built as %s, which is not a Service. Correct the binding or delegation for it in the'
						. ' composition root.',
						get_class( $service )
					)
				),
			];
		}

		if ( $calls !== [] || self::get_hooks() !== $before ) {
			return [
				$this->problem(
					$id,
					$class_name,
					'changes hooks while it is being built, directly or through a collaborator its constructor'
					. ' creates. Constructors only store dependencies; add hooks in register().'
				),
			];
		}

		return [];
	}

	/**
	 * Get every hook Brain Monkey has seen added, removed or fired.
	 *
	 * @return mixed Snapshot of Brain Monkey's hook storage.
	 */
	private static function get_hooks() {
		$storage  = Container::instance()->hookStorage();
		$property = new ReflectionProperty( $storage, 'storage' );
		$property->setAccessible( true );

		return $property->getValue( $storage );
	}

	/**
	 * Call one of the composition root's protected methods.
	 *
	 * @param ServiceBasedPlugin $plugin    Composition root.
	 * @param string             $method    Method to call.
	 * @param mixed              ...$values Arguments.
	 * @return mixed What the method returns.
	 */
	private static function call( ServiceBasedPlugin $plugin, string $method, ...$values ) {
		$reflection = new ReflectionMethod( $plugin, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( $plugin, ...$values );
	}

	/**
	 * Configure the plugin's injector the way registering its services does,
	 * without registering anything.
	 *
	 * The plugin keeps the injector that configure_injector() returns, which
	 * may be a new instance, and resolves callables in its configuration with
	 * it, so the check does the same.
	 *
	 * @param ServiceBasedPlugin $plugin Plugin whose injector to configure.
	 * @return Injector Configured injector.
	 */
	private function configure_injector( ServiceBasedPlugin $plugin ): Injector {
		$property = new ReflectionProperty( ServiceBasedPlugin::class, 'injector' );
		$property->setAccessible( true );

		$injector = $property->getValue( $plugin );
		assert( $injector instanceof Injector );

		$configured = self::call( $plugin, 'configure_injector', $injector );
		assert( $configured instanceof Injector );

		$property->setValue( $plugin, $configured );

		return $configured;
	}

	/**
	 * Complete a problem with the service and the rule's explanation.
	 *
	 * @param string $id         Identifier of the service.
	 * @param string $class_name Class the service is declared as.
	 * @param string $problem    What is wrong, and what to do instead.
	 * @return string Complete message.
	 */
	private function problem( string $id, string $class_name, string $problem ): string {
		return sprintf(
			'[%1$s] The service "%2$s" (%3$s) of %4$s %5$s %6$s.',
			RuleManifest::COMPOSITION_ROOT_CONTRACTS,
			$id,
			$class_name,
			$this->plugin_class,
			$problem,
			RuleManifest::get_explanation( RuleManifest::COMPOSITION_ROOT_CONTRACTS )
		);
	}
}
