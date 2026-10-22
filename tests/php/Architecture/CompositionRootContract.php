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
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
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
		$injector = new SimpleInjector();
		// Composition roots keep the constructor of ServiceBasedPlugin, so the
		// injector it takes is the one the plugin configures.
		$plugin   = ( new ReflectionClass( $this->plugin_class ) )->newInstance( true, $injector );
		$declared = self::call( $plugin, 'get_service_classes' );
		$services = is_array( $declared ) ? $declared : [];
		$problems = [];

		foreach ( $services as $id => $declaration ) {
			$id         = (string) $id;
			$class_name = is_string( $declaration )
				? $declaration
				: self::call( $plugin, 'maybe_resolve', $declaration );

			if ( ! is_string( $class_name )
				|| ! class_exists( $class_name )
				|| ! is_a( $class_name, Service::class, true ) ) {
				$problems[] = $this->problem(
					$id,
					sprintf(
						'is declared as %s, which is not a class implementing Service. Declare a class name, or a'
						. ' callable that returns one, of a class that implements Service.',
						is_string( $class_name ) ? $class_name : gettype( $class_name )
					)
				);
				continue;
			}

			$problems = array_merge(
				$problems,
				$this->check_capabilities( $id, $class_name, array_keys( $services ) ),
				$this->check_construction( $id, $class_name, $injector )
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
				'is Delayed, but get_registration_action() returns no action. Return the action whose firing the'
				. ' service has to wait for, such as "init" or "wp".'
			);
		}

		if ( is_a( $class_name, HasDependencies::class, true ) ) {
			foreach ( $class_name::get_dependencies() as $dependency ) {
				if ( ! in_array( $dependency, $declared, true ) ) {
					$problems[] = $this->problem(
						$id,
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
	 * Build a service and check that building it changes no hooks.
	 *
	 * @param string                $id         Identifier of the service.
	 * @param class-string<Service> $class_name Class of the service.
	 * @param SimpleInjector        $injector   Injector the plugin configured.
	 * @return array<string> Problems found.
	 */
	private function check_construction( string $id, string $class_name, SimpleInjector $injector ): array {
		$before = self::get_hooks();

		try {
			$injector->make( $class_name );
		} catch ( Throwable $throwable ) {
			return [
				$this->problem(
					$id,
					sprintf(
						'cannot be built from the composition root: %s Bind what it needs in get_bindings(),'
						. ' get_arguments() or get_delegations().',
						$throwable->getMessage()
					)
				),
			];
		}

		if ( self::get_hooks() !== $before ) {
			return [
				$this->problem(
					$id,
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
	 * Complete a problem with the service and the rule's explanation.
	 *
	 * @param string $id      Identifier of the service.
	 * @param string $problem What is wrong, and what to do instead.
	 * @return string Complete message.
	 */
	private function problem( string $id, string $problem ): string {
		return sprintf(
			'[%1$s] The service "%2$s" of %3$s %4$s %5$s.',
			RuleManifest::COMPOSITION_ROOT_CONTRACTS,
			$id,
			$this->plugin_class,
			$problem,
			RuleManifest::get_explanation( RuleManifest::COMPOSITION_ROOT_CONTRACTS )
		);
	}
}
